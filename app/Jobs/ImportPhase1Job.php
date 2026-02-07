<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\Heroku\HerokuApi;
use App\Services\LaravelCloud\CloudApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportPhase1Job implements ShouldQueue
{
    use Queueable;

    public function __construct(public Import $import) {}

    public function handle(): void
    {
        $import = $this->import;

        try {
            $import->update(['status' => Import::STATUS_PHASE1_RUNNING]);

            $user = $import->user;
            $herokuApi = new HerokuApi($user->herokuToken);
            $cloudApi = new CloudApi($user->cloudToken);

            // Step 1: Fetch all Heroku app data
            $import->appendPhase1Log('Fetching Heroku app details...');
            $appData = $herokuApi->getApp($import->heroku_app_id);
            $configVars = $herokuApi->getConfigVars($import->heroku_app_id);
            $formation = $herokuApi->getFormation($import->heroku_app_id);
            $addons = $herokuApi->getAddons($import->heroku_app_id);
            $domains = $herokuApi->getDomains($import->heroku_app_id);
            $buildpacks = $herokuApi->getBuildpackInstallations($import->heroku_app_id);

            $import->update(['heroku_app_data' => compact('appData', 'configVars', 'formation', 'addons', 'domains', 'buildpacks')]);
            $import->appendPhase1Log('Heroku app data fetched successfully.');

            // Step 2: Map Heroku region to Cloud region
            $cloudRegion = self::mapRegion($appData['region']['name'] ?? 'us');
            $import->appendPhase1Log("Mapped region: {$cloudRegion}");

            // Step 3: Create Cloud application
            $import->appendPhase1Log('Creating Laravel Cloud application...');
            $cloudApp = $cloudApi->createApplication(
                $import->github_repository,
                $import->heroku_app_name,
                $cloudRegion,
            );
            $cloudAppId = $cloudApp['data']['id'] ?? $cloudApp['id'];
            $import->update(['cloud_application_id' => $cloudAppId]);
            $import->appendPhase1Log("Cloud application created: {$cloudAppId}");

            // Step 4: Create and configure environment
            $import->appendPhase1Log('Creating environment...');
            $environment = $cloudApi->createEnvironment($cloudAppId, 'production');
            $environmentId = $environment['data']['id'] ?? $environment['id'];
            $import->update(['cloud_environment_id' => $environmentId]);

            $phpVersion = '8.4:1';
            $needsNode = false;
            foreach ($buildpacks as $bp) {
                $bpName = $bp['buildpack']['name'] ?? $bp['buildpack']['url'] ?? '';
                if (str_contains($bpName, 'nodejs') || str_contains($bpName, 'node')) {
                    $needsNode = true;
                }
            }

            $envConfig = [
                'php_version' => $phpVersion,
                'build_command' => $needsNode
                    ? 'composer install --no-dev && npm install && npm run build'
                    : 'composer install --no-dev',
                'deploy_command' => 'php artisan migrate --force',
                'uses_vanity_domain' => true,
            ];
            if ($needsNode) {
                $envConfig['node_version'] = '22';
            }

            $cloudApi->updateEnvironment($environmentId, $envConfig);
            $import->appendPhase1Log('Environment configured.');

            // Step 5: Set environment variables from Heroku
            $import->appendPhase1Log('Setting environment variables...');
            $variables = [];
            $herokuManagedPrefixes = ['HEROKU_POSTGRESQL_', 'HEROKU_REDIS_'];
            foreach ($configVars as $key => $value) {
                $isHerokuManaged = false;
                foreach ($herokuManagedPrefixes as $prefix) {
                    if (str_starts_with($key, $prefix)) {
                        $isHerokuManaged = true;
                        break;
                    }
                }
                if (! $isHerokuManaged) {
                    $variables[] = ['key' => $key, 'value' => $value];
                }
            }
            $cloudApi->setEnvironmentVariables($environmentId, $variables);
            $import->appendPhase1Log(count($variables).' environment variables set.');

            // Step 6: Map formation to instances
            $import->appendPhase1Log('Creating compute instances...');
            $webFormation = null;
            $workerProcesses = [];
            foreach ($formation as $process) {
                if ($process['type'] === 'web') {
                    $webFormation = $process;
                } else {
                    $workerProcesses[] = $process;
                }
            }

            $instanceSize = self::mapDynoSize($webFormation['size'] ?? 'basic');

            $backgroundProcesses = [];
            foreach ($workerProcesses as $worker) {
                $backgroundProcesses[] = [
                    'type' => 'worker',
                    'processes' => $worker['quantity'] ?? 1,
                    'command' => $worker['command'],
                ];
            }

            $instanceData = [
                'name' => 'web',
                'type' => 'service',
                'size' => $instanceSize,
                'scaling_type' => 'none',
                'min_replicas' => $webFormation['quantity'] ?? 1,
                'max_replicas' => $webFormation['quantity'] ?? 1,
            ];

            if (! empty($backgroundProcesses)) {
                $instanceData['background_processes'] = $backgroundProcesses;
            }

            $hasScheduler = false;
            foreach ($addons as $addon) {
                if (($addon['addon_service']['name'] ?? '') === 'scheduler') {
                    $hasScheduler = true;
                    break;
                }
            }
            if ($hasScheduler) {
                $instanceData['uses_scheduler'] = true;
            }

            $cloudApi->createInstance($environmentId, $instanceData);
            $import->appendPhase1Log('Compute instance created.');

            // Step 7: Add custom domains
            $customDomains = array_filter($domains, fn ($d) => ($d['kind'] ?? '') === 'custom');
            foreach ($customDomains as $domain) {
                $cloudApi->addDomain($environmentId, $domain['hostname']);
                $import->appendPhase1Log("Domain added: {$domain['hostname']}");
            }

            // Step 8: Trigger deployment
            $import->appendPhase1Log('Triggering deployment...');
            $cloudApi->createDeployment($environmentId);
            $import->appendPhase1Log('Deployment triggered successfully.');

            $import->update(['status' => Import::STATUS_PHASE1_DONE]);
            $import->appendPhase1Log('Phase 1 complete!');

        } catch (\Throwable $e) {
            $import->markFailed($e->getMessage());
            $import->appendPhase1Log('Phase 1 failed: '.$e->getMessage());
            throw $e;
        }
    }

    public static function mapRegion(string $herokuRegion): string
    {
        return match ($herokuRegion) {
            'us' => 'us-east-2',
            'eu' => 'eu-west-2',
            default => 'us-east-2',
        };
    }

    public static function mapDynoSize(string $herokuSize): string
    {
        return match (strtolower($herokuSize)) {
            'eco', 'basic', 'standard-1x' => 'flex.g-1vcpu-512mb',
            'standard-2x' => 'flex.g-2vcpu-1gb',
            'performance-m' => 'pro.g-2vcpu-4gb',
            'performance-l' => 'pro.g-8vcpu-16gb',
            default => 'flex.g-1vcpu-512mb',
        };
    }
}
