<?php
namespace App\Http\Controllers;

use Auth, Gate, Request, DB;
use App\Channel, App\Source;

class SettingsController extends Controller
{
  public function __construct() {
    $this->middleware('auth');
  }

  public function index() {
    $channels = Auth::user()->channels()
      ->orderBy('sort')
      ->get();

    return view('settings', [
      'demo_mode_enabled' => Auth::user()->demo_mode_enabled
    ]);
  }

  public function save() {
    $user = Auth::user();

    if(Request::input('demo_mode_enabled') == 'on') {
      $user->demo_mode_enabled = 1;
    } else {
      $user->demo_mode_enabled = 0;
    }

    $user->save();

    session()->flash('settings', 'Settings were saved');
    return redirect('settings');
  }

  public function reload_micropub_config() {
    if(session('access_token')) {
      $user = Auth::user();
      $user->reload_micropub_config(session('access_token'));
      $user->save();
    }
    return response()->json(['json' => $user->micropub_config]);
  }

}
