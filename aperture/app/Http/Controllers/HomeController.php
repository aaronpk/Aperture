<?php
namespace App\Http\Controllers;

use Auth, Gate, Request, DB;
use App\Models\Channel, App\Models\Source;
use App\Events\SourceAdded, App\Events\SourceRemoved;
use Illuminate\Routing\Controllers\HasMiddleware;

class HomeController extends Controller implements HasMiddleware
{
  public static function middleware(): array {
    return ['auth'];
  }

  public function dashboard() {
    $channels = Auth::user()->channels()->get();
    $archived = Auth::user()->archived_channels()->get();

    return view('dashboard', [
      'channels' => $channels,
      'archived' => $archived
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
        $source->token = \Illuminate\Support\Str::random(32);
        $source->is_new = true;
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

      $channel->include_only = Request::input('include_only') ?: '';
      $channel->exclude_types = Request::input('exclude_types') ?: '';

      $keywords = preg_split('/[ ,]+/', (string)Request::input('include_keywords'));
      $channel->include_keywords = implode(' ', $keywords);

      $keywords = preg_split('/[ ,]+/', (string)Request::input('exclude_keywords'));
      $channel->exclude_keywords = implode(' ', $keywords);

      $channel->read_tracking_mode = Request::input('read_tracking_mode') ?: 'counts';

      $channel->hide_in_demo_mode = Request::input('hide_in_demo_mode') ? 1 : 0;
      $channel->archived = Request::input('archived') ? 1 : 0;

      $channel->default_destination = Request::input('default_destination') ?: '';

      // Users with an unlimited retention policy can override per channel
      if(Auth::user()->retention_days == 0) {
        $channel->retention_days = Request::input('retention_days') ?: 0;
      }

      $channel->save();

      return response()->json([
        'result' => 'ok'
      ]);
    } else {
      abort(401);
    }
  }

  public function save_source(Channel $channel, Source $source) {
    if(Gate::allows('edit-channel', $channel)) {

      $subscription = DB::table('channel_source')
        ->where('channel_id', $channel->id)
        ->where('source_id', $source->id)
        ->update([
          'name' => Request::input('name')
        ]);

      return response()->json([
        'result' => 'ok'
      ]);
    } else {
      abort(401);
    }
  }

  // Try a source again after it was stopped for failing for too long
  public function retry_source(Channel $channel, Source $source) {
    if(Gate::allows('edit-channel', $channel)) {

      if($channel->sources()->where('source_id', $source->id)->count() == 0)
        abort(404);

      if($source->stopped_at)
        $source->resume();

      // With a sync queue this fetches right away, so the result below is the outcome.
      // Otherwise it reports the source as it was, and the fetch happens shortly.
      \App\Jobs\FetchSource::dispatch($source->id);
      $source->refresh();

      return response()->json([
        'result' => 'ok',
        'stopped' => (bool)$source->stopped_at,
        'failing' => $source->consecutive_failures > 0,
        'last_http_status' => $source->last_http_status,
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
      $source->token = \Illuminate\Support\Str::random(32);
      $source->name = Request::input('name') ?: '';
      $source->format = 'apikey';
      $source->created_by = Auth::user()->id;
      // always download images when creating posts via micropub
      $source->download_images = true;
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

    $http = app(\p3k\HTTP::class);
    $http->set_user_agent(config('aperture.user_agent'));
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

