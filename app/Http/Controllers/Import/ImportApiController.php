<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\Cloud\CloudClient;
use App\Services\Heroku\HerokuClient;
use App\Services\Import\Deployer;
use App\Services\Import\MigrationPlan;
use App\Services\Import\ResourceMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportApiController extends Controller
{
    public function generatePlan(Request $request): JsonResponse
    {
        $request->validate([
            'heroku_app_id' => ['required', 'string'],
            'github_repository' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+\/[a-zA-Z0-9._-]+$/'],
        ]);
        $herokuToken = $request->session()->get('heroku_access_token');
        if (! $herokuToken) {
            return response()->json(['message' => 'Heroku not connected.'], 403);
        }
        $client = new HerokuClient($herokuToken);
        $app = $client->getApp($request->input('heroku_app_id'));
        $mapper = new ResourceMapper($client);
        $resources = $mapper->fetchResources($request->input('heroku_app_id'));
        $plan = $mapper->fromHerokuApp($app, $resources, $request->input('github_repository'));
        $fullPlanArray = $this->planToArray($plan, true);
        $request->session()->put('import_plan', $fullPlanArray);

        return response()->json($this->planToArray($plan, false));
    }

    public function executePlan(Request $request): JsonResponse
    {
        $cloudToken = $request->session()->get('cloud_api_token');
        if (! $cloudToken) {
            return response()->json(['message' => 'Laravel Cloud not connected.'], 403);
        }
        $stored = $request->session()->get('import_plan');
        if (! $stored) {
            return response()->json(['message' => 'No import plan. Go back to Configure and generate a plan first.'], 400);
        }
        $plan = $this->arrayToPlan($stored);
        $request->session()->forget('import_plan');
        $cloud = new CloudClient($cloudToken);
        $deployer = new Deployer($cloud);
        $result = $deployer->execute($plan);
        $request->session()->put('import_deploy_result', [
            'success' => $result->success,
            'application_id' => $result->applicationId,
            'environment_id' => $result->environmentId,
            'deployment_id' => $result->deploymentId,
            'database_cluster_id' => $result->databaseClusterId,
            'database_schema_id' => $result->databaseSchemaId,
            'cache_id' => $result->cacheId,
            'domain_dns_records' => $result->domainDnsRecords,
            'vanity_url' => $result->vanityUrl,
            'error' => $result->error,
        ]);

        return response()->json([
            'success' => $result->success,
            'vanity_url' => $result->vanityUrl,
            'error' => $result->error,
        ]);
    }

    private function planToArray(MigrationPlan $plan, bool $includeCredentials): array
    {
        $db = null;
        if ($plan->database) {
            $db = [
                'name' => $plan->database->name,
                'storage_gb' => $plan->database->storageGb,
                'min_compute' => $plan->database->minCompute,
                'max_compute' => $plan->database->maxCompute,
            ];
        }
        $variables = array_map(fn ($v) => [
            'key' => $v->key,
            'value' => $includeCredentials ? $v->value : (str_starts_with(strtolower($v->key), 'password') || $v->key === 'DB_PASSWORD' ? '***' : $v->value),
            'action' => $v->action,
            'reason' => $v->reason,
        ], $plan->variables);

        return [
            'heroku_app_id' => $plan->herokuAppId,
            'heroku_app_name' => $plan->herokuAppName,
            'github_repository' => $plan->githubRepository,
            'application' => [
                'name' => $plan->application->name,
                'region' => $plan->application->region,
            ],
            'environment' => [
                'branch' => $plan->environment->branch,
                'php_version' => $plan->environment->phpVersion,
                'node_version' => $plan->environment->nodeVersion,
                'build_command' => $plan->environment->buildCommand,
                'deploy_command' => $plan->environment->deployCommand,
                'uses_octane' => $plan->environment->usesOctane,
                'timeout' => $plan->environment->timeout,
            ],
            'compute' => [
                'size' => $plan->compute->size,
                'min_replicas' => $plan->compute->minReplicas,
                'max_replicas' => $plan->compute->maxReplicas,
                'scaling_type' => $plan->compute->scalingType,
                'uses_scheduler' => $plan->compute->usesScheduler,
                'background_processes' => array_map(fn ($bp) => [
                    'type' => $bp->type,
                    'processes' => $bp->processes,
                    'command' => $bp->command,
                    'config' => $bp->config,
                ], $plan->compute->backgroundProcesses),
            ],
            'database' => $db,
            'database_credentials' => $includeCredentials && $plan->database ? [
                'host' => $plan->database->herokuCredentials->host,
                'port' => $plan->database->herokuCredentials->port,
                'database' => $plan->database->herokuCredentials->database,
                'username' => $plan->database->herokuCredentials->username,
                'password' => $plan->database->herokuCredentials->password,
                'full_url' => $plan->database->herokuCredentials->fullUrl,
            ] : null,
            'cache' => $plan->cache ? [
                'name' => $plan->cache->name,
                'size' => $plan->cache->size,
                'eviction_policy' => $plan->cache->evictionPolicy,
            ] : null,
            'variables' => $variables,
            'domains' => array_map(fn ($d) => [
                'name' => $d->name,
                'www_redirect' => $d->wwwRedirect,
                'enabled' => $d->enabled,
            ], $plan->domains),
            'warnings' => $plan->warnings,
        ];
    }

    private function arrayToPlan(array $data): MigrationPlan
    {
        $app = new \App\Services\Import\ApplicationConfig(
            $data['application']['name'],
            $data['application']['region'],
        );
        $env = new \App\Services\Import\EnvironmentConfig(
            $data['environment']['branch'],
            $data['environment']['php_version'],
            $data['environment']['node_version'] ?? null,
            $data['environment']['build_command'] ?? null,
            $data['environment']['deploy_command'],
            $data['environment']['uses_octane'] ?? false,
            $data['environment']['timeout'] ?? 30,
        );
        $processes = [];
        foreach ($data['compute']['background_processes'] ?? [] as $bp) {
            $processes[] = new \App\Services\Import\BackgroundProcess(
                $bp['type'],
                $bp['processes'],
                $bp['command'],
                $bp['config'] ?? [],
            );
        }
        $compute = new \App\Services\Import\ComputeConfig(
            $data['compute']['size'],
            $data['compute']['min_replicas'],
            $data['compute']['max_replicas'],
            $data['compute']['scaling_type'] ?? 'none',
            $data['compute']['uses_scheduler'] ?? false,
            $processes,
        );
        $database = null;
        if (! empty($data['database']) && ! empty($data['database_credentials'])) {
            $c = $data['database_credentials'];
            $creds = new \App\Services\Import\DatabaseCredentials(
                $c['host'],
                $c['port'],
                $c['database'],
                $c['username'],
                $c['password'],
                $c['full_url'],
            );
            $database = new \App\Services\Import\DatabaseConfig(
                $data['database']['name'],
                $data['database']['storage_gb'],
                $data['database']['min_compute'],
                $data['database']['max_compute'],
                $creds,
            );
        }
        $cache = null;
        if (! empty($data['cache'])) {
            $cache = new \App\Services\Import\CacheConfig(
                $data['cache']['name'],
                $data['cache']['size'],
                $data['cache']['eviction_policy'],
            );
        }
        $variables = [];
        foreach ($data['variables'] ?? [] as $v) {
            $variables[] = new \App\Services\Import\VariableMapping(
                $v['key'],
                $v['value'],
                $v['action'],
                $v['reason'] ?? null,
            );
        }
        $domains = [];
        foreach ($data['domains'] ?? [] as $d) {
            $domains[] = new \App\Services\Import\DomainMapping(
                $d['name'],
                $d['www_redirect'],
                $d['enabled'],
            );
        }

        return new MigrationPlan(
            $data['heroku_app_id'],
            $data['heroku_app_name'],
            $data['github_repository'],
            $app,
            $env,
            $compute,
            $database,
            $cache,
            $variables,
            $domains,
            $data['warnings'] ?? [],
        );
    }
}
