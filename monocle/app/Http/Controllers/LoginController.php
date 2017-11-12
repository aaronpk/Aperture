<?php
namespace App\Http\Controllers;

use Request, DB;
use Auth;
use IndieAuth;
use App\User;

class LoginController extends Controller
{

  public function login() {
    return view('login/login');
  }

  public function logout() {
    Auth::logout();
    return redirect('/');
  }

  public function start() {
    if(!Request::input('url')) {
      return redirect('login')->with('auth_error', 'invalid url')
        ->with('auth_error_description', 'The URL you entered was not valid');
    }

    // Discover the endpoints
    $url = IndieAuth\Client::normalizeMeURL(Request::input('url'));
    $authorizationEndpoint = IndieAuth\Client::discoverAuthorizationEndpoint($url);

    if(!$authorizationEndpoint) {
      return redirect('login')->with('auth_error', 'missing authorization endpoint')
        ->with('auth_error_description', 'Could not find your authorization endpoint')
        ->with('auth_url', Request::input('url'));
    }

    $state = str_random(32);
    session([
      'state' => $state,
      'authorization_endpoint' => $authorizationEndpoint,
      'indieauth_url' => $url,
    ]);

    $redirect_uri = route('login_callback');
    $client_id = route('index');

    $authorizationURL = IndieAuth\Client::buildAuthorizationURL($authorizationEndpoint, $url, $redirect_uri, $client_id, $state, '');

    return redirect($authorizationURL);
  }

  public function callback() {
    if(!session('state')) {
      return redirect('/');
    }

    if(!Request::input('state')) {
      return view('login/error', [
        'error' => 'missing state',
        'description' => 'No state was provided in the callback. The IndieAuth server may be configured incorrectly.'
      ]);
    }

    if(Request::input('state') != session('state')) {
      return view('login/error', [
        'error' => 'invalid state',
        'description' => 'The state returned in the callback did not match the expected value. The IndieAuth server may be configured incorrectly.'
      ]);
    }

    // Check the authorization code at the endpoint previously discovered
    $auth = IndieAuth\Client::verifyIndieAuthCode(session('authorization_endpoint'), Request::input('code'), null, route('login_callback'), route('index'));

    if(isset($auth['me'])) {

      // Check that the URL returned is on the same domain as the expected URL
      if(parse_url($auth['me'], PHP_URL_HOST) != parse_url(session('indieauth_url'), PHP_URL_HOST)) {
        return view('login/error', [
          'error' => 'invalid user',
          'description' => 'The URL for the user returned did not match the domain of the user initially signing in'
        ]);
      }

      session([
        'state' => false,
        'authorization_endpoint' => false,
        'indieauth_url' => false
      ]);

      // Load or create the user record
      $user = User::where('url', $auth['me'])->first();
      if(!$user) {
        $user = new User();
        $user->url = $auth['me'];
        $user->save();
      }

      Auth::login($user);

      if($r=session('redirect_after_login')) {
        session()->forget('redirect_after_login');
        return redirect($r);
      } else {
        return redirect(route('dashboard'));
      }

    } else {
      return view('login/error', [
        'error' => 'indieauth error',
        'description' => 'The authoriation code was not able to be verified'
      ]);
    }
  }

}
