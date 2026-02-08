<?php

namespace App\Services\Import;

readonly class ComputeConfig
{
    public function __construct(
        public string $size,
        public int $minReplicas,
        public int $maxReplicas,
        public string $scalingType,
        public bool $usesScheduler,
        /** @var array<BackgroundProcess> */
        public array $backgroundProcesses,
    ) {}
}
