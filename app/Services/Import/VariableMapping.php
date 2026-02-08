<?php

namespace App\Services\Import;

readonly class VariableMapping
{
    public function __construct(
        public string $key,
        public string $value,
        public string $action,
        public ?string $reason = null,
    ) {}
}
