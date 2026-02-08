<?php

namespace App\Services\Import;

readonly class DatabaseCredentials
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
        public string $fullUrl,
    ) {}
}
