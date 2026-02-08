<?php

namespace App\Services\Import;

readonly class DeploymentStep
{
    public function __construct(
        public string $id,
        public string $label,
        public string $status,
        public ?string $error = null,
        public ?array $result = null,
    ) {}
}
