<?php

namespace App\Services\Import;

readonly class DeploymentResult
{
    public function __construct(
        public bool $success,
        public ?string $applicationId = null,
        public ?string $environmentId = null,
        public ?string $deploymentId = null,
        public ?string $databaseClusterId = null,
        public ?string $databaseSchemaId = null,
        public ?string $cacheId = null,
        /** @var array<string, string> */
        public array $domainDnsRecords = [],
        public ?string $vanityUrl = null,
        public ?string $error = null,
    ) {}
}
