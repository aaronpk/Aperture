<?php
namespace App\Http\Controllers;

use Auth, Gate, Request, DB;
use App\Podcast, App\Channel, App\Source;
use App\Events\SourceAdded, App\Events\SourceRemoved;

class HomeController extends Controller
{
  public function __construct() {
    $this->middleware('auth');
  }

  public function index() {
    $channels = Auth::user()->channels()
      ->orderByDesc(DB::raw('uid = "default"'))
      ->orderByDesc(DB::raw('uid = "notifications"'))
      ->orderBy('name')
      ->get();

    return view('dashboard', [
      'channels' => $channels
    ]);
  }

  public function create_channel() {
    $channel = new Channel();
    $channel->user_id = Auth::user()->id;
    $channel->name = Request::input('name');
    $channel->uid = str_random(32);
    $channel->save();

    return redirect(route('dashboard'));
  }

  public function channel(Channel $channel) {
    if(Gate::allows('edit-channel', $channel)) {
      $sources = $channel->sources()->get();

      return view('channel', [
        'channel' => $channel,
        'sources' => $sources
      ]);
    } else {
      abort(401);
    }
  }

  public function add_source(Channel $channel) {
    if(Gate::allows('edit-channel', $channel)) {

      // Create or load the source
      $source = Source::where('url', Request::input('url'))->first();
      if(!$source) {
        $source = new Source();
        $source->created_by = Auth::user()->id;
        $source->url = Request::input('url');
        $source->format = Request::input('format');
        $source->token = str_random(32);
        $source->save();
      }

      event(new SourceAdded($source, $channel));

      if($channel->sources()->where('source_id', $source->id)->count() == 0)
        $channel->sources()->attach($source->id, ['created_at'=>date('Y-m-d H:i:s')]);

      return response()->json([
        'result' => 'ok'
      ]);
    } else {
      abort(401);
    }
  }

  public function remove_source(Channel $channel) {
    if(Gate::allows('edit-channel', $channel)) {

      $channel->sources()->detach(Request::input('source_id'));

      $source = Source::where('id', Request::input('source_id'))->first();

      event(new SourceRemoved($source, $channel));

      return response()->json([
        'result' => 'ok'
      ]);
    } else {
      abort(401);
    }
  }

  public function add_apikey(Channel $channel) {
    if(Gate::allows('edit-channel', $channel)) {

      // Create a new source for this API key
      $source = new Source();
      $source->token = str_random(32);
      $source->name = Request::input('name') ?: '';
      $source->format = 'apikey';
      $source->created_by = Auth::user()->id;
      $source->save();

      $channel->sources()->attach($source->id, ['created_at'=>date('Y-m-d H:i:s')]);

      return response()->json([
        'result' => 'ok'
      ]);
    } else {
      abort(401);
    }    
  }

  public function find_feeds() {

    $url = Request::input('url');
    if(preg_match('/^[a-z][a-z0-9]+$/', $url)) {
      $url = $url . '.com';
    }
    $url = \p3k\url\normalize($url);

    $http = new \p3k\HTTP(env('USER_AGENT'));
    $http->timeout = 10;
    $xray = new \p3k\XRay();
    $xray->http = $http;
    $response = $xray->feeds($url);

    $feeds = [];

    if(!isset($response['error']) && $response['code'] == 200) {
      $feeds = $response['feeds'];
    }

    return response()->json([
      'feeds' => $feeds,
    ]);
  }

}

