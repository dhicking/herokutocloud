<?php

namespace App\Services\Import;

use App\Services\Cloud\CloudClient;
use Illuminate\Support\Facades\Log;

class Deployer
{
    public function __construct(
        private CloudClient $cloud,
    ) {}

    public function execute(MigrationPlan $plan): DeploymentResult
    {
        $applicationId = null;
        $environmentId = null;
        $deploymentId = null;
        $databaseClusterId = null;
        $databaseSchemaId = null;
        $cacheId = null;
        $domainDnsRecords = [];
        $vanityUrl = null;

        try {
            $appJson = $this->cloud->createApplication(
                $plan->githubRepository,
                $plan->application->name,
                $plan->application->region
            );
            $applicationId = $this->extractId($appJson, 'applications');
            if (! $applicationId) {
                throw new \RuntimeException('Failed to create application: missing id in response');
            }

            $envJson = $this->cloud->createEnvironment($applicationId);
            $environmentId = $this->extractId($envJson, 'environments');
            if (! $environmentId) {
                throw new \RuntimeException('Failed to create environment: missing id in response');
            }

            $this->cloud->updateEnvironment($environmentId, [
                'branch' => $plan->environment->branch,
                'php_version' => $plan->environment->phpVersion,
                'node_version' => $plan->environment->nodeVersion,
                'build_command' => $plan->environment->buildCommand,
                'deploy_command' => $plan->environment->deployCommand,
                'uses_octane' => $plan->environment->usesOctane,
                'timeout' => $plan->environment->timeout,
                'uses_push_to_deploy' => true,
                'uses_vanity_domain' => true,
            ]);

            if ($plan->database) {
                $dbJson = $this->cloud->createDatabaseCluster([
                    'name' => $plan->database->name,
                    'type' => 'serverless-postgres',
                    'region' => $plan->application->region,
                    'min_compute' => $plan->database->minCompute,
                    'max_compute' => $plan->database->maxCompute,
                    'storage' => $plan->database->storageGb,
                ]);
                $databaseClusterId = $this->extractId($dbJson, 'database-clusters');
                if ($databaseClusterId) {
                    $schemaJson = $this->cloud->createDatabase($databaseClusterId, []);
                    $databaseSchemaId = $this->extractId($schemaJson, 'database-schemas');
                }
            }

            if ($plan->cache) {
                $cacheJson = $this->cloud->createCache([
                    'name' => $plan->cache->name,
                    'region' => $plan->application->region,
                    'size' => $plan->cache->size,
                ]);
                $cacheId = $this->extractId($cacheJson, 'caches');
                if ($cacheId) {
                    $this->cloud->updateEnvironment($environmentId, ['cache_id' => $cacheId]);
                }
            }

            $variables = $this->buildVariables($plan);
            $this->cloud->setEnvironmentVariables($environmentId, $variables);

            $instancePayload = [
                'name' => 'web',
                'type' => 'service',
                'size' => $plan->compute->size,
                'scaling_type' => $plan->compute->scalingType,
                'min_replicas' => $plan->compute->minReplicas,
                'max_replicas' => $plan->compute->maxReplicas,
                'uses_scheduler' => $plan->compute->usesScheduler,
            ];
            if (! empty($plan->compute->backgroundProcesses)) {
                $instancePayload['background_processes'] = array_map(fn (BackgroundProcess $bp) => [
                    'type' => $bp->type,
                    'processes' => $bp->processes,
                    'command' => $bp->command,
                    'config' => $bp->config,
                ], $plan->compute->backgroundProcesses);
            }
            $this->cloud->createInstance($environmentId, $instancePayload);

            foreach ($plan->domains as $domain) {
                if (! $domain->enabled) {
                    continue;
                }
                $domainJson = $this->cloud->addDomain($environmentId, $domain->name, $domain->wwwRedirect);
                $dns = $domainJson['data']['attributes']['dns_records'] ?? [];
                foreach ($dns as $record) {
                    $domainDnsRecords[$domain->name] = $record['target'] ?? $record;
                }
            }

            $deployJson = $this->cloud->createDeployment($environmentId);
            $deploymentId = $this->extractId($deployJson, 'deployments');

            $vanityUrl = $plan->application->name.'.laravel.cloud';

            return new DeploymentResult(
                success: true,
                applicationId: $applicationId,
                environmentId: $environmentId,
                deploymentId: $deploymentId,
                databaseClusterId: $databaseClusterId,
                databaseSchemaId: $databaseSchemaId,
                cacheId: $cacheId,
                domainDnsRecords: $domainDnsRecords,
                vanityUrl: $vanityUrl,
            );
        } catch (\Throwable $e) {
            Log::error('Deployer failed', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return new DeploymentResult(
                success: false,
                applicationId: $applicationId,
                environmentId: $environmentId,
                error: $e->getMessage(),
            );
        }
    }

    /** @return array<array{key: string, value: string}> */
    private function buildVariables(MigrationPlan $plan): array
    {
        $vars = [];
        foreach ($plan->variables as $vm) {
            if ($vm->action !== 'import') {
                continue;
            }
            $vars[] = ['key' => $vm->key, 'value' => $vm->value];
        }
        if ($plan->database) {
            $c = $plan->database->herokuCredentials;
            $vars[] = ['key' => 'DB_CONNECTION', 'value' => 'pgsql'];
            $vars[] = ['key' => 'DB_HOST', 'value' => $c->host];
            $vars[] = ['key' => 'DB_PORT', 'value' => (string) $c->port];
            $vars[] = ['key' => 'DB_DATABASE', 'value' => $c->database];
            $vars[] = ['key' => 'DB_USERNAME', 'value' => $c->username];
            $vars[] = ['key' => 'DB_PASSWORD', 'value' => $c->password];
            $vars[] = ['key' => 'DATABASE_URL', 'value' => $c->fullUrl];
        }

        return $vars;
    }

    private function extractId(array $json, string $type): ?string
    {
        $data = $json['data'] ?? null;
        if (! is_array($data)) {
            return null;
        }

        return $data['id'] ?? null;
    }
}
