<?php

namespace App\Console\Commands;

use App\Services\Cloud\CloudClient;
use App\Services\Heroku\HerokuClient;
use App\Services\Import\Deployer;
use App\Services\Import\ResourceMapper;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error as promptError;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class HerokuImportCommand extends Command
{
    protected $signature = 'heroku:import';

    protected $description = 'Import a Heroku app into Laravel Cloud';

    public function handle(): int
    {
        $herokuKey = password('Heroku API key', placeholder: 'Paste from dashboard.heroku.com/account');
        $cloudToken = password('Laravel Cloud API token', placeholder: 'Paste from cloud.laravel.com');
        $herokuClient = new HerokuClient($herokuKey);
        $cloudClient = new CloudClient($cloudToken);

        try {
            $herokuClient->getAccount();
        } catch (\Throwable $e) {
            promptError('Invalid Heroku API key: '.$e->getMessage());

            return 1;
        }
        try {
            $cloudClient->verifyToken();
        } catch (\Throwable $e) {
            promptError('Invalid Laravel Cloud token: '.$e->getMessage());

            return 1;
        }

        $apps = $herokuClient->getApps();
        $phpApps = [];
        foreach ($apps as $app) {
            $buildpackDesc = $app['buildpack_provided_description'] ?? null;
            $isPhp = $buildpackDesc && stripos($buildpackDesc, 'PHP') !== false;
            if (! $isPhp && ! empty($app['id'])) {
                $buildpacks = $herokuClient->getBuildpackInstallations($app['id']);
                foreach ($buildpacks as $bp) {
                    $name = $bp['buildpack']['name'] ?? $bp['buildpack']['url'] ?? '';
                    if (stripos($name, 'php') !== false) {
                        $isPhp = true;
                        break;
                    }
                }
            }
            if ($isPhp) {
                $phpApps[$app['id']] = $app['name'].' ('.($app['region']['name'] ?? 'us').')';
            }
        }
        if (empty($phpApps)) {
            promptError('No PHP apps found in your Heroku account.');

            return 1;
        }

        $appId = select('Which Heroku app?', $phpApps);
        $githubRepo = text('GitHub repository (org/repo)', placeholder: 'owner/repo', required: true);
        if (! preg_match('/^[a-zA-Z0-9._-]+\/[a-zA-Z0-9._-]+$/', $githubRepo)) {
            promptError('Repository must be in owner/repo format.');

            return 1;
        }

        $this->info('Fetching resources...');
        $mapper = new ResourceMapper($herokuClient);
        $resources = $mapper->fetchResources($appId);
        $app = $herokuClient->getApp($appId);
        $plan = $mapper->fromHerokuApp($app, $resources, $githubRepo);

        $varCount = count(array_filter($plan->variables, fn ($v) => $v->action === 'import'));
        $workerCount = count($plan->compute->backgroundProcesses);
        $this->table(
            ['Resource', 'Count'],
            [
                ['Environment variables', (string) $varCount],
                ['Workers', (string) $workerCount],
                ['Database', $plan->database ? 'Yes' : 'No'],
                ['Cache', $plan->cache ? 'Yes' : 'No'],
                ['Custom domains', (string) count($plan->domains)],
                ['Scheduler', $plan->compute->usesScheduler ? 'Yes' : 'No'],
            ]
        );

        if (! confirm('Deploy with these defaults?')) {
            $this->info('Aborted.');

            return 0;
        }

        $this->info('Deploying...');
        $deployer = new Deployer($cloudClient);
        $result = $deployer->execute($plan);

        if (! $result->success) {
            promptError('Deployment failed: '.$result->error);

            return 1;
        }

        info('Created application "'.$plan->application->name.'"');
        info('Environment: '.$result->environmentId);
        info('Vanity URL: https://'.$result->vanityUrl);
        info('Dashboard: https://cloud.laravel.com');
        if (! empty($result->domainDnsRecords)) {
            $this->newLine();
            $this->table(['Domain', 'DNS target'], collect($result->domainDnsRecords)->map(fn ($target, $name) => [$name, $target])->values()->all());
        }
        $this->newLine();
        info('Your app is connected to your Heroku database. Complete the database migration in the Laravel Cloud dashboard when ready.');

        return 0;
    }
}
