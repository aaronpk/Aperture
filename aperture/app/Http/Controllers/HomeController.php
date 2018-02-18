<?php
namespace App\Http\Controllers;

use Auth, Gate, Request, DB;
use App\Channel, App\Source;
use App\Events\SourceAdded, App\Events\SourceRemoved;

class HomeController extends Controller
{
  public function __construct() {
    $this->middleware('auth');
  }

  public function dashboard() {
    $channels = Auth::user()->channels()
      ->orderBy('sort')
      ->get();

    return view('dashboard', [
      'channels' => $channels
    ]);
  }

  public function create_channel() {
    $channel = Auth::user()->create_channel(Request::input('name'));

    return redirect(route('dashboard'));
  }

  public function channel(Channel $channel) {
    if(Gate::allows('edit-channel', $channel)) {
      $sources = $channel->sources()
        ->withCount('entries')
        ->get();

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

      if($channel->sources()->where('source_id', $source->id)->count() == 0)
        $channel->sources()->attach($source->id, ['created_at'=>date('Y-m-d H:i:s')]);

      event(new SourceAdded($source, $channel));

      return response()->json([
        'result' => 'ok'
      ]);
    } else {
      abort(401);
    }
  }

  public function save_channel(Channel $channel) {
    if(Gate::allows('edit-channel', $channel)) {

      $channel->name = Request::input('name');
      #$channel->domain = Request::input('domain');
      $channel->save();

      return response()->json([
        'result' => 'ok'
      ]);
    } else {
      abort(401);
    }
  }

  public function remove_source(Channel $channel) {
    if(Gate::allows('edit-channel', $channel)) {

      $source = Source::where('id', Request::input('source_id'))->first();

      if($source) {
        $channel->remove_source($source, (bool)Request::input('remove_entries'));
      }

      return response()->json([
        'result' => 'ok'
      ]);
    } else {
      abort(401);
    }
  }

  public function delete_channel(Channel $channel) {
    if(Gate::allows('edit-channel', $channel)) {

      if(in_array($channel->uid, ['notifications']))
        abort(400);

      $channel->entries()->delete();
      $channel->delete();

      return response()->json([
        'result' => 'ok',
      ]);
    } else {
      abort(401);
    }
  }

  public function set_channel_order() {
    if(!is_array(Request::input('channels')))
      return response()->json(['result'=>'error']);
    
    $sorted = Auth::user()->set_channel_order(Request::input('channels'));
    if($sorted) {
      return response()->json(['result'=>'ok']);
    } else {
      return response()->json(['result'=>'error']);
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
    $http->set_timeout(30);
    $xray = new \p3k\XRay();
    $xray->http = $http;
    $response = $xray->feeds($url);

    $feeds = [];

    if(!isset($response['error']) && $response['code'] == 200) {
      $feeds = $response['feeds'];
    }

    return response()->json([
      'feeds' => $feeds,
      'error' => $response['error'] ?? false,
      'error_description' => $response['error_description'] ?? false,
    ]);
  }

}

