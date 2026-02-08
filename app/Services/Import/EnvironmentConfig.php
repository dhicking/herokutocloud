<?php

namespace App\Services\Import;

readonly class EnvironmentConfig
{
    public function __construct(
        public string $branch,
        public string $phpVersion,
        public ?string $nodeVersion,
        public ?string $buildCommand,
        public string $deployCommand,
        public bool $usesOctane,
        public int $timeout,
    ) {}
}
