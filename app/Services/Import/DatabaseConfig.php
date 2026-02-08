<?php

namespace App\Services\Import;

readonly class DatabaseConfig
{
    public function __construct(
        public string $name,
        public int $storageGb,
        public float $minCompute,
        public float $maxCompute,
        public DatabaseCredentials $herokuCredentials,
    ) {}
}
