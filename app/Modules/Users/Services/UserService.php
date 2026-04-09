<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\User;

class UserService
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }
}
