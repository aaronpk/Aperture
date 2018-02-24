<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\User;

class CreateUser extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'create:user {url}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Create a new user to allow them to use Aperture';

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * Execute the console command.
   *
   * @return mixed
   */
  public function handle()
  {
    $url = $this->argument('url');

    if(!\p3k\url\is_url($url)) {
      $this->error($url.' does not appear to be a valid URL');
      return;
    }

    $url = \IndieAuth\Client::normalizeMeURL($url);

    $user = User::where('url', $url)->first();

    if($user) {
      $this->warn('This user already exists');
      return;
    }

    $tokenEndpoint = \IndieAuth\Client::discoverTokenEndpoint($url);

    if(!$tokenEndpoint) {
      $this->error('Could not discover the token endpoint for this user so they will be unable to log in');
      return;
    }

    $this->info('Creating user: '.$url);
    $this->info('Found token endpoint: '.$tokenEndpoint);

    $user = new User();
    $user->url = $url;
    $user->token_endpoint = $tokenEndpoint;
    $user->save();

    $this->info('Done!');
  }
}
