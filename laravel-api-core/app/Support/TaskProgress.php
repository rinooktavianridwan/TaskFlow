<?php

namespace App\Support;

use App\Enums\TaskStatus;

final class TaskProgress
{
    /**
     * Status `done` selalu 100%. Selain itu dihitung dari checklist (0% bila tanpa checklist).
     */
    public static function percent(string $status, int $total, int $done): int
    {
        if ($status === TaskStatus::Done->value) {
            return 100;
        }

        if ($total === 0) {
            return 0;
        }

        return (int) round($done / $total * 100);
    }
}
