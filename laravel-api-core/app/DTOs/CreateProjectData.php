<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

class CreateProjectData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {}
}
