<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionGroup extends Model
{
    protected $fillable = ['group_name'];
}
