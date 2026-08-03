<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Batch;
use App\Models\User;

trait AuthorizesBatchAccess
{
    /**
     * Determine whether the user can manage the batch.
     */
    protected function canManageBatch(User $user, int $batchId): bool
    {
        $user->loadMissing(['roles', 'teacher:id,user_id']);

        if ($user->hasRole('admin')) {
            return true;
        }

        return Batch::where('id', $batchId)
            ->where('teacher_id', $user->teacher->id ?? 0)
            ->exists();
    }
}
