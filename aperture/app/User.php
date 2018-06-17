<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Events\UserCreated;
use DB;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'remember_token'
    ];

    protected $dispatchesEvents = [
        'created' => UserCreated::class
    ];

    public function channels() {
        $channels = $this->hasMany('App\Channel')
          ->where('archived', 0)
          ->orderBy('sort');
        if($this->demo_mode_enabled) {
          $channels = $channels->where('hide_in_demo_mode', 0);
        }
        return $channels;
    }

    public function archived_channels() {
        $channels = $this->hasMany('App\Channel')
          ->where('archived', 1)
          ->orderBy('sort');
        if($this->demo_mode_enabled) {
          $channels = $channels->where('hide_in_demo_mode', 0);
        }
        return $channels;
    }

    public function all_channels() {
        $channels = $this->hasMany('App\Channel')
          ->orderBy('sort');
        return $channels;
    }

    public function create_channel($name) {
        // Move the sort order of existing channels out of the way for the new channel
        DB::table('channels')
          ->where('user_id', $this->id)
          ->where('sort', '>', 0)
          ->increment('sort');

        $channel = new Channel;
        $channel->user_id = $this->id;
        $channel->name = $name;
        $channel->uid = str_random(24);
        $channel->sort = 1;
        $channel->save();

        return $channel;
    }

    public function set_channel_order(array $channelUIDs) {
        // Don't allow the notifications channel to be moved
        $channelUIDs = array_values(array_diff($channelUIDs, ['notifications']));

        // This returns the channels in the current order
        $channels = $this->channels()->whereIn('uid', $channelUIDs)->get();

        if(count($channels) != count($channelUIDs)) {
            return false;
        }

        $currentSortValues = [];
        foreach($channels as $channel) {
            $currentSortValues[] = $channel->sort;
        }

        $newSortValues = [];
        foreach($channelUIDs as $i=>$ch) {
            $newSortValues[$ch] = $currentSortValues[$i];
        }

        foreach($channels as $channel) {
            $channel->sort = $newSortValues[$channel->uid];
            $channel->save();
        }

        return $channelUIDs;
    }

    public function reload_micropub_config($token) {
      if(!$this->micropub_endpoint)
        return;

      $config = false;

      $http = new \p3k\HTTP();
      $response = $http->get(\p3k\url\add_query_params_to_url($this->micropub_endpoint, ['q'=>'config']), [
          'Authorization: Bearer '.$token,
          'Accept: application/json'
      ]);

      if($response['body']) {
        $config = json_decode($response['body']);
      }

      if($config)
        $this->micropub_config = json_encode($config, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);
    }

    public function get_micropub_config($key) {
      if(!$this->micropub_config) return false;
      $config = json_decode($this->micropub_config, true);
      if(isset($config[$key]))
        return $config[$key];
      return false;
    }

}
