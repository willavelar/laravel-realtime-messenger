<?php

namespace App\Modules\Users\Events;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserConnected
{
    use Dispatchable;

    public function __construct(public User $user) {}
}
