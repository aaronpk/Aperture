<?php
namespace App\Http\Controllers;

use Auth, Gate, Request, DB;
use App\Models\Channel, App\Models\Source;
use Illuminate\Routing\Controllers\HasMiddleware;

class SettingsController extends Controller implements HasMiddleware
{
  public static function middleware(): array {
    return ['auth'];
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
