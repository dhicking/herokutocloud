<?php

namespace App\Services\Import;

readonly class CacheConfig
{
    public function __construct(
        public string $name,
        public string $size,
        public string $evictionPolicy,
    ) {}
}
