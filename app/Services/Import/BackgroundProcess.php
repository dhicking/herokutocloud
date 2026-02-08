<?php

namespace App\Services\Import;

readonly class BackgroundProcess
{
    public function __construct(
        public string $type,
        public int $processes,
        public string $command,
        public array $config,
    ) {}
}
