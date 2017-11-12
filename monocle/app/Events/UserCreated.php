<?php

namespace App\Events;

use App\User, App\Channel;

class UserCreated
{
    public function __construct(User $user)
    {
        $channel = new Channel();
        $channel->user_id = $user->id;
        $channel->uid = 'default';
        $channel->name = 'Home';
        $channel->save();

        $channel = new Channel();
        $channel->user_id = $user->id;
        $channel->uid = 'notifications';
        $channel->name = 'Notifications';
        $channel->save();
    }
}
