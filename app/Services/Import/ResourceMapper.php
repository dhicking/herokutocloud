<?php

namespace App\Services\Import;

use App\Services\Heroku\HerokuClient;

class ResourceMapper
{
    private const HEROKU_SKIP_VAR_PREFIXES = ['HEROKU_', 'DYNO', 'PORT', 'WEB_CONCURRENCY', 'STACK', 'LOG_LEVEL'];

    private const REGION_MAP = ['us' => 'us-east-2', 'eu' => 'eu-west-1'];

    private const DYNO_SIZE_MAP = [
        'eco' => 'flex.g-1vcpu-512mb',
        'basic' => 'flex.g-1vcpu-512mb',
        'standard-1x' => 'flex.g-1vcpu-512mb',
        'standard-2x' => 'flex.g-2vcpu-1gb',
        'performance-m' => 'pro.g-2vcpu-4gb',
        'performance-l' => 'pro.g-8vcpu-16gb',
    ];

    public function __construct(
        private HerokuClient $heroku,
    ) {}

    public function fetchResources(string $appId): array
    {
        $client = $this->heroku;
        $configVars = $client->getConfigVars($appId);
        $formation = $client->getFormation($appId);
        $addons = $client->getAddons($appId);
        $domains = $client->getDomains($appId);
        $buildpacks = $client->getBuildpackInstallations($appId);
        $slug = $client->getLatestSlug($appId);
        $processTypes = $slug['process_types'] ?? [];

        return [
            'config_vars' => $configVars,
            'formation' => $formation,
            'addons' => $addons,
            'domains' => $domains,
            'buildpacks' => $buildpacks,
            'process_types' => $processTypes,
        ];
    }

    public function fromHerokuApp(array $app, array $resources, string $githubRepository): MigrationPlan
    {
        $appName = $app['name'] ?? 'imported-app';
        $region = $this->mapRegion($app['region']['name'] ?? 'us');
        $addons = $resources['addons'] ?? [];
        $addonNames = array_column(array_map(fn ($a) => $a['addon_service']['name'] ?? '', $addons), 0);
        $hasPostgres = in_array('heroku-postgresql', $addonNames, true);
        $hasRedis = in_array('heroku-redis', $addonNames, true) || in_array('heroku-valkey', $addonNames, true);
        $hasScheduler = in_array('scheduler', $addonNames, true);
        $processTypes = $resources['process_types'] ?? [];
        $hasSchedulerProcess = isset($processTypes['scheduler']) || isset($processTypes['clock']);

        $variables = $this->classifyConfigVars(
            $resources['config_vars'] ?? [],
            $resources['addons'] ?? []
        );
        $dbCredentials = null;
        if ($hasPostgres && isset($resources['config_vars']['DATABASE_URL'])) {
            $dbCredentials = $this->parseDatabaseUrl($resources['config_vars']['DATABASE_URL']);
        }

        $database = null;
        if ($hasPostgres && $dbCredentials) {
            $database = new DatabaseConfig(
                name: $appName.'-db',
                storageGb: 10,
                minCompute: 0.25,
                maxCompute: 1.0,
                herokuCredentials: $dbCredentials,
            );
        }

        $cache = null;
        if ($hasRedis) {
            $cache = new CacheConfig(
                name: $appName.'-cache',
                size: '250mb',
                evictionPolicy: 'allkeys-lru',
            );
        }

        $formation = $resources['formation'] ?? [];
        $compute = $this->mapFormation($formation, $processTypes, $hasScheduler || $hasSchedulerProcess);

        $customDomains = array_values(array_filter($resources['domains'] ?? [], fn ($d) => ($d['kind'] ?? '') === 'custom'));
        $domainMappings = array_map(fn ($d) => new DomainMapping(
            name: $d['hostname'] ?? '',
            wwwRedirect: 'www_to_root',
            enabled: true,
        ), $customDomains);

        $warnings = [];
        if ($hasScheduler) {
            $warnings[] = 'Heroku Scheduler detected — ensure your schedule is defined in routes/console.php.';
        }

        $nodeVersion = $this->detectNodeVersion($resources['buildpacks'] ?? []);
        $buildCommand = $nodeVersion ? 'npm run build' : null;

        return new MigrationPlan(
            herokuAppId: $app['id'],
            herokuAppName: $appName,
            githubRepository: $githubRepository,
            application: new ApplicationConfig(name: $appName, region: $region),
            environment: new EnvironmentConfig(
                branch: 'main',
                phpVersion: '8.4',
                nodeVersion: $nodeVersion,
                buildCommand: $buildCommand,
                deployCommand: 'php artisan migrate --force',
                usesOctane: false,
                timeout: 30,
            ),
            compute: $compute,
            database: $database,
            cache: $cache,
            variables: $variables,
            domains: $domainMappings,
            warnings: $warnings,
        );
    }

    public function parseDatabaseUrl(string $url): DatabaseCredentials
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? 'localhost';
        $port = (int) ($parsed['port'] ?? 5432);
        $database = ltrim($parsed['path'] ?? '/', '/');
        $username = $parsed['user'] ?? '';
        $password = $parsed['pass'] ?? '';

        return new DatabaseCredentials(
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            fullUrl: $url,
        );
    }

    /** @return array<VariableMapping> */
    public function classifyConfigVars(array $configVars, array $addons): array
    {
        $addonVarNames = [];
        foreach ($addons as $addon) {
            foreach ($addon['config_vars'] ?? [] as $var) {
                $addonVarNames[] = $var;
            }
        }
        $skipKeys = ['HEROKU_POSTGRESQL_', 'HEROKU_REDIS_', 'REDIS_URL', 'REDIS_TLS_URL', 'DATABASE_URL'];
        $result = [];
        foreach ($configVars as $key => $value) {
            $skip = false;
            $reason = null;
            foreach (self::HEROKU_SKIP_VAR_PREFIXES as $prefix) {
                if (str_starts_with($key, $prefix) || $key === $prefix) {
                    $skip = true;
                    $reason = 'Heroku-managed';
                    break;
                }
            }
            if ($key === 'DATABASE_URL') {
                $result[] = new VariableMapping($key, $value, 'replace', 'Replaced by DB_* and DATABASE_URL from Heroku');

                continue;
            }
            if (in_array($key, ['REDIS_URL', 'REDIS_TLS_URL'], true) || str_starts_with($key, 'HEROKU_REDIS_')) {
                $skip = true;
                $reason = 'Replaced by Valkey';
            }
            if ($skip && $reason) {
                $result[] = new VariableMapping($key, $value, 'skip', $reason);
            } else {
                $result[] = new VariableMapping($key, (string) $value, 'import', null);
            }
        }

        return $result;
    }

    public function mapRegion(string $herokuRegion): string
    {
        $name = strtolower($herokuRegion);

        return self::REGION_MAP[$name] ?? 'us-east-2';
    }

    public function mapDynoSize(string $herokuSize): string
    {
        $key = strtolower($herokuSize);

        return self::DYNO_SIZE_MAP[$key] ?? 'flex.g-1vcpu-512mb';
    }

    private function mapFormation(array $formation, array $processTypes, bool $usesScheduler): ComputeConfig
    {
        $size = 'flex.g-1vcpu-512mb';
        $minReplicas = 1;
        $maxReplicas = 1;
        $backgroundProcesses = [];
        foreach ($formation as $proc) {
            $type = $proc['type'] ?? 'web';
            if ($type === 'web') {
                $size = $this->mapDynoSize($proc['size'] ?? 'basic');
                $quantity = (int) ($proc['quantity'] ?? 1);
                $maxReplicas = $quantity;
                $minReplicas = min(1, $quantity);
            } else {
                $backgroundProcesses[] = new BackgroundProcess(
                    type: 'worker',
                    processes: (int) ($proc['quantity'] ?? 1),
                    command: $proc['command'] ?? 'php artisan queue:work --tries=3',
                    config: [
                        'connection' => 'redis',
                        'queue' => 'default',
                        'tries' => 3,
                        'timeout' => 60,
                        'sleep' => 3,
                    ],
                );
            }
        }

        return new ComputeConfig(
            size: $size,
            minReplicas: $minReplicas,
            maxReplicas: $maxReplicas,
            scalingType: 'none',
            usesScheduler: $usesScheduler,
            backgroundProcesses: $backgroundProcesses,
        );
    }

    private function detectNodeVersion(array $buildpacks): ?string
    {
        foreach ($buildpacks as $bp) {
            $name = $bp['buildpack']['name'] ?? $bp['buildpack']['url'] ?? '';
            if (stripos($name, 'node') !== false) {
                return '24';
            }
        }

        return null;
    }
}
