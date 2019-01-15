<?php
namespace App\Http\Controllers;

use Request, DB, Cache;
use Auth;
use IndieAuth;
use App\User, App\Channel, App\Source;

class IndieAuthController extends Controller
{

  public function auth_get() {

    $client_id = Request::input('client_id');
    $client_name = false;
    $client_icon = false;

    if(!$client_id) {
      return response(view('oauth/error', [
        'error' => 'missing client_id',
        'description' => 'No client_id was found in the request. Apps use the client_id parameter so that you know which application you are authorizing.'
      ]), 400);
    }

    if(!\p3k\url\is_url($client_id)) {
      return response(view('oauth/error', [
        'error' => 'invalid client_id',
        'description' => 'The client_id provided was not a URL. IndieAuth client IDs must be a full URL.'
      ]), 400);
    }

    $redirect_uri = Request::input('redirect_uri');
    if(!$redirect_uri) {
      return response(view('oauth/error', [
        'error' => 'missing redirect_uri',
        'description' => 'No redirect_uri was found in the request. Apps use the redirect_uri parameter to indicate where to return your browser after you approve the request.'
      ]), 400);
    }

    if(!\p3k\url\is_url($redirect_uri)) {
      return response(view('oauth/error', [
        'error' => 'invalid redirect_uri',
        'description' => 'The redirect_uri provided was not a URL.'
      ]), 400);
    }

    if(Request::get('response_type') != 'code') {
      return response(view('oauth/error', [
        'error' => 'bad response type',
        'description' => 'Aperture cannot be used to just log in to apps, it can only be used with Micropub apps to create posts in your channels.'
      ]), 400);
    }

    if(!Auth::user()) {
      $oauthurl = Request::fullUrl();
      $redirect = '/login?return='.urlencode($oauthurl);
      return redirect($redirect, 302);
    }

    $client_info = self::_client_info($client_id);
    if($client_info) {
      if(isset($client_info['data']['type']) && $client_info['data']['type'] == 'app') {
        $client_name = $client_info['data']['name'] ?? null;
        $client_icon = $client_info['data']['logo'] ?? null;
      }
    }

    if(parse_url($client_id, PHP_URL_HOST) == parse_url($redirect_uri, PHP_URL_HOST)) {
      $redirect_uri_warning = false;
    } else {
      $redirect_uri_warning = true;
      if($client_name && $client_info) {
        if(isset($client_info['data']['redirect-uri'])) {
          if(in_array($redirect_uri, $client_info['data']['redirect-uri']))
            $redirect_uri_warning = false;
        }
      }
    }

    $state = Request::input('state');

    $create = false;
    $scope = explode(' ', Request::input('scope'));
    // If the app requests "post", convert to "create"
    foreach($scope as $i=>$s) {
      if(in_array($s, ['post','create','save']))
        $create = true;
    }

    if(!$create) {
      return response(view('oauth/error', [
        'error' => 'invalid scope',
        'description' => 'The application requested a scope that is not valid. Aperture can only be use with applications that create posts.'
      ]), 400);
    }

    // If they entered a specific IndieAuth URL in the login form, select that channel in the dropdown
    $requested_channel = false;
    if($me = Request::input('me')) {
      if(preg_match('/\/([a-zA-Z0-9_]+)-([a-zA-Z0-9_]+)-([a-zA-Z0-9_]+)/', $me, $match)) {
        $user_id = \p3k\b60to10($match[1]);
        $channel_id = \p3k\b60to10($match[2]);

        if($user_id != Auth::user()->id) {
          return response(view('oauth/error', [
            'error' => 'invalid URL',
            'description' => 'The URL you entered does not belong to your Aperture account ('.$match[1].').'
          ]), 400);
        }

        $channel = Auth::user()->channels()->where('id', $channel_id)->first();
        if(!$channel) {
          return response(view('oauth/error', [
            'error' => 'invalid URL',
            'description' => 'The URL you entered does not belong to a channel in your Aperture account.'
          ]), 400);
        }

        $requested_channel = $channel->id;
      }
    }

    $channels = Auth::user()->channels()->get();

    return view('oauth/authorize', [
      'client' => [
        'name' => $client_name,
        'icon' => $client_icon
      ],
      'client_id' => $client_id,
      'redirect_uri' => $redirect_uri,
      'redirect_uri_warning' => $redirect_uri_warning,
      'scope' => Request::input('scope'),
      'state' => $state,
      'create' => $create,
      'channels' => $channels,
      'requested_channel' => $requested_channel,
    ]);
  }

  public function auth_process() {
    if(!Auth::user())
      return redirect('login');

    // Check that the selected channel exists and belongs to this user
    $channel = Channel::where('id', Request::input('channel'))->first();
    if(!$channel)
      return redirect('login');

    if($channel->user_id != Auth::user()->id)
      return redirect('login');

    $data = [
      'scope' => Request::input('scope'),
      'client_id' => Request::input('client_id'),
      'redirect_uri' => Request::input('redirect_uri'),
    ];

    $code = self::generateAuthCode(Auth::user(), $channel, $data);

    $redirect = Request::input('redirect_uri');

    $params = ['code' => $code];
    if($s=Request::input('state'))
      $params['state'] = $s;

    $redirect = \p3k\url\add_query_params_to_url($redirect, $params);
    return redirect($redirect);
  }

  private static function generateAuthCode(\App\User $user, \App\Channel $channel, $authData) {
    $codeData = array(
      'iat' => time(),
      'exp' => time()+60,
      'user_id' => $user->id,
      'channel_id' => $channel->id,
      'client_id' => $authData['client_id'],
      'redirect_uri' => $authData['redirect_uri'],
      'scope' => $authData['scope'],
    );
    $code = 'a.'.str_random(60);
    Cache::put($code, json_encode($codeData), 1);
    return $code;
  }

  private static function verifyAuthCode($code) {
    $str = Cache::get($code);
    if($str) {
      $data = json_decode($str, true);
      if(time() > $data['exp']) {
        return false;
      } else {
        return $data;
      }
    }
    return false;
  }

  public function auth_post() {
    // We don't support response_type=id, so leave this unimplemented
    return response()->json([
      'error' => 'invalid_request',
      'error_description' => 'Aperture does not support verifying authorization codes',
    ], 400);
  }

  private function _me(User $user, Channel $channel, Source $source) {
    return env('APP_URL').'/'.\p3k\b10to60($user->id).'-'.\p3k\b10to60($channel->id).'-'.\p3k\b10to60($source->id);
  }

  public function token_get() {
    // Verify access tokens
    // This is called with the ChannelAPIKey middleware which handles parsing the header and verifying the token
    $td = Request::get('token_data');
    if(isset($td['type']) && $td['type'] == 'source') {
      $source = Source::where('id', $td['source_id'])->first();
      $user = User::where('id', $td['user_id'])->first();
      if(isset($td['channel_id'])) {
        $channel = Channel::where('id', $td['channel_id'])->first();
        return response()->json([
          'me' => $this->_me($user, $channel, $source)
        ]);
      } else {
        return response()->json([
          'error' => 'not_found',
          'error_description' => 'Channel not found'
        ], 404);
      }
    } else {
      return response()->json([
        'error' => 'not_found',
        'error_description' => 'Token not found'
      ], 404);
    }
  }

  public function token_post() {

    $data = self::verifyAuthCode(Request::input('code'));

    if(!$data) {
      return response()->json([
        'error' => 'invalid_grant',
        'error_description' => 'The authorization code provided was invalid',
      ], 400);
    }

    $user = User::where('id', $data['user_id'])->first();
    $channel = Channel::where('id', $data['channel_id'])->first();

    if(!$user || !$channel) {
      return response()->json([
        'error' => 'invalid_grant',
        'error_description' => 'The authorization code provided was invalid',
      ], 400);
    }

    // Verify the client ID and redirect URI match
    if($data['client_id'] != Request::input('client_id')) {
      return response()->json([
        'error' => 'invalid_request',
        'error_description' => 'The client_id in the request did not match the client_id the code was issued for',
      ], 400);
    }

    if($data['redirect_uri'] != Request::input('redirect_uri')) {
      return response()->json([
        'error' => 'invalid_request',
        'error_description' => 'The redirect_uri in the request did not match the redirect_uri the code was issued for',
      ], 400);
    }

    // Override scope to either create or save
    $scope = explode(' ', $data['scope']);
    if(in_array('save', $scope))
      $scope = 'save';
    else
      $scope = 'create';

    // Everything is ready, create a new source (API key) and return it as an access token
    // Reuse the same API key if there is already a source for this client_id
    $source = Source::where('created_by', $user->id)->where('name', $data['client_id'])->first();
    if(!$source) {
      $source = new Source();
      $source->token = str_random(32);
      $source->name = $data['client_id'];
      $source->format = 'apikey';
      $source->created_by = $user->id;
      $source->is_new = true;
      // always download images when creating posts via micropub
      $source->download_images = true;
      $source->save();
    }

    if($channel->sources()->where('source_id', $source->id)->count() == 0)
      $channel->sources()->attach($source->id, ['created_at'=>date('Y-m-d H:i:s')]);

    $me = $this->_me($user, $channel, $source);

    return response()->json([
      'access_token' => $source->token,
      'scope' => $scope,
      'me' => $me,
    ]);
  }

  public function profile($user_id, $channel_id, $source_id) {
    $user_id = \p3k\b60to10($user_id);
    $channel_id = \p3k\b60to10($channel_id);
    $source_id = \p3k\b60to10($source_id);

    $user = User::where('id', $user_id)->first();
    $channel = Channel::where('user_id', $user_id)->where('id', $channel_id)->first();

    if(!$user || !$channel)
      abort(404);

    return view('oauth/profile', [
      'user' => $user,
      'channel' => $channel
    ]);
  }

  private static function _client_info($client_id) {
    $http = new \p3k\HTTP(env('USER_AGENT'));
    $http->set_timeout(30);
    $xray = new \p3k\XRay();
    $xray->http = $http;
    $result = $xray->parse($client_id);
    return $result;
  }

}
