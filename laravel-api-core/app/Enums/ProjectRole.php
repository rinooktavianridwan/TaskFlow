<?php

namespace App\Enums;

enum ProjectRole: string
{
    case Owner  = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';
}
