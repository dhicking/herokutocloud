<?php

namespace App\Services\Import;

readonly class DomainMapping
{
    public function __construct(
        public string $name,
        public string $wwwRedirect,
        public bool $enabled,
    ) {}
}
