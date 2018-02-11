<?php

namespace App\Events;

use App\User, App\Channel;

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
        $channel->uid = str_random(32);
        $channel->name = 'Home';
        $channel->sort = 1;
        $channel->save();
    }
}
