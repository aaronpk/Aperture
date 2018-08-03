<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use IndieAuth;
use Log;
use App\User;

class VerifyAPIRegistration implements ShouldQueue {
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  private $site;
  private $code;
  private $verification_endpoint;
  private $via;

  public function __construct($site, $code, $verification_endpoint, $via)
  {
    $this->site = $site;
    $this->code = $code;
    $this->verification_endpoint = $verification_endpoint;
    $this->via = $via;
  }

  public function handle()
  {
    $challenge = str_random(32);

    $http = new \p3k\HTTP(env('USER_AGENT'));
    $http->set_timeout(30);

    $response = $http->post($this->verification_endpoint, http_build_query([
      'code' => $this->code,
      'challenge' => $challenge,
    ]));

    if(trim($response['body']) != $challenge) {
      Log::info('Server failed to acknowledge challenge. '.$this->site.' '.$this->verification_endpoint);
      Log::info('Sent code '.$this->code);
      Log::info('Received: '.$response['body'].' Expected: '.$challenge);
      return;
    }

    $tokenEndpoint = IndieAuth\Client::discoverTokenEndpoint($this->site);
    $micropubEndpoint = IndieAuth\Client::discoverMicropubEndpoint($this->site);

    if(!$tokenEndpoint) {
      $http->post($this->verification_endpoint, http_build_query([
        'code' => $this->code,
        'error' => 'Aperture could not find your token endpoint',
      ]));
      Log::info('Could not find user\'s token endpoint');
      return;
    }

    $user = User::where('url', $this->site)->first();
    if(!$user) {
      $user = new User();
      $user->url = $this->site;
      $user->created_via = $this->via;
    }
    $user->token_endpoint = $tokenEndpoint;
    $user->save();

    $microsubEndpoint = env('APP_URL').'/microsub/'.$user->id;

    $http->post($this->verification_endpoint, http_build_query([
      'code' => $this->code,
      'microsub' => $microsubEndpoint,
    ]));

    Log::info('Successful automatic user registration for '.$this->site);
  }

}
