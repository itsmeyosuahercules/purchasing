<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Karyawan hanya boleh melihat order miliknya. Admin bebas (ditangani middleware role).
     */
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
