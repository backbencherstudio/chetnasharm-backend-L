<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'permission_name',
        'permission_type',
        'group_name',
        'guard_name',
    ];
}
