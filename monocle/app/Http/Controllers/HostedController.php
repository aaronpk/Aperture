<?php
namespace App\Http\Controllers;

use Request, Response, DB, Log, Route;
use App\User, App\Source, App\Channel, App\Entry;

class HostedController extends Controller
{

  public function index() {
    $domain = Request::getHost();
    $channel = Channel::where('domain', $domain)->first();
    if(!$channel) {
      return response()->view('hosted/domain-not-found', [], 404);
    }

    return $domain;
  }

}
