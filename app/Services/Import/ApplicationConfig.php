<?php

namespace App\Services\Import;

readonly class ApplicationConfig
{
    public function __construct(
        public string $name,
        public string $region,
    ) {}
}
