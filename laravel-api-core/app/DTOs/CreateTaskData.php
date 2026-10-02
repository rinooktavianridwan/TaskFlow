<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

class CreateTaskData extends Data
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?int $assignedTo,
        public ?string $dueDate,
    ) {}
}
