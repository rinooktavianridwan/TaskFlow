<?php

namespace App\Enums;

enum TaskActivityAction: string
{
    case Created       = 'created';
    case StatusChanged = 'status_changed';
    case Assigned      = 'assigned';
    case Updated       = 'updated';
}
