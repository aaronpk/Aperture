<?php

namespace App\Events;

use App\Models\User, App\Models\Channel;

class UserCreated
{
    public function __construct(User $user)
    {
        $channel = new Channel();
        $channel->user_id = $user->id;
        $channel->uid = 'notifications';
        $channel->name = 'Notifications';
        $channel->sort = 0;
        $channel->save();

        $channel = new Channel();
        $channel->user_id = $user->id;
        $channel->uid = \Illuminate\Support\Str::random(24);
        $channel->name = 'Home';
        $channel->sort = 1;
        $channel->save();
    }
}
