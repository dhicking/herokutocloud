<?php

namespace App\Services\Import;

readonly class MigrationPlan
{
    public function __construct(
        public string $herokuAppId,
        public string $herokuAppName,
        public string $githubRepository,
        public ApplicationConfig $application,
        public EnvironmentConfig $environment,
        public ComputeConfig $compute,
        public ?DatabaseConfig $database,
        public ?CacheConfig $cache,
        /** @var array<VariableMapping> */
        public array $variables,
        /** @var array<DomainMapping> */
        public array $domains,
        /** @var array<string> */
        public array $warnings,
    ) {}
}
