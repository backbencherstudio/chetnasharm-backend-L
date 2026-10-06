<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'permission_name',
    'permission_type',
    'group_name',
    'guard_name',
])]
class Permission extends Model {}
