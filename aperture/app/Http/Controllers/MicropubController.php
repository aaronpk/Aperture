<?php
namespace App\Http\Controllers;

use Request, Response, DB, Log, Auth;
use App\User, App\Source, App\Channel, App\Entry, App\Media, App\ChannelToken;
use App\Events\EntrySaved;
use p3k\XRay;

class MicropubController extends Controller
{
  private function _getRequestSource() {
    $td = Request::get('token_data');
    if(isset($td['type']) && $td['type'] == 'source') {
      return Source::where('id', $td['source_id'])->first();
    } else {
      return Response::json([
        'error' => 'not_found',
        'error_description' => 'Channel not found'
      ], 404);
    }
  }

  public function get() {
    if(Request::input('q') == 'config') {
      return response()->json([
        'media-endpoint' => env('APP_URL').'/micropub/media'
      ]);
    }
  }

  public function post(Request $request) {
    $source = $this->_getRequestSource();

    if(get_class($source) == 'Illuminate\Http\JsonResponse')
      return $source;

    $input = file_get_contents('php://input');

    #Log::info('raw input: '.$input);

    // If the content type is application/jf2+json then accept the JSON directly.
    // This is probably dangerous and we should validate the jf2 document first,
    // but that is more work than I want to do right now so we'll deal with that later.
    if(Request::header('Content-Type') == 'application/jf2+json') {

      $item = json_decode($input, true);

      if(!$item || !isset($item['type'])) {
        return Response::json([
          'error' => 'invalid_input',
          'error_description' => 'The jf2 input was invalid',
        ], 400);
      }
    } else {
      $micropub = \p3k\Micropub\Request::createFromString($input);

      #Log::info('micropub request: '.json_encode($micropub->toMf2()));

      if($micropub->error) {
        return Response::json([
          'error' => $micropub->error,
          'error_property' => $micropub->error_property,
          'error_description' => $micropub->error_description,
        ], 400);
      }

      $mf2 = ['items' => [$micropub->toMf2()]];

      $xray = new XRay();
      $parsed = $xray->process(false, $mf2);
      $item = $parsed['data'];
    }

    $entry = false;
    $new = false;

    if(isset($item['url'])) {
      $entry = Entry::where('source_id', $source->id)->where('unique', md5($item['url']))->first();
    }

    if(!$entry) {
      $entry = new Entry();
      $entry->source_id = $source->id;
      $entry->unique = isset($item['url']) ? md5($item['url']) : str_random(32);
      $new = true;
    }

    if(!isset($item['published']))
      $item['published'] = date('c');

    $entry->data = json_encode($item, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);

    $entry->published = date('Y-m-d H:i:s', strtotime($item['published']));

    $entry->save();

    event(new EntrySaved($entry));

    if($new) {
      Log::info("Adding entry ".$entry->unique." to channels");
      foreach($source->channels()->get() as $channel) {
        Log::info("  Adding to channel #".$channel->id);
        $channel->entries()->attach($entry->id, ['created_at'=>date('Y-m-d H:i:s')]);
        // TODO: send websub notification for the channel
        // try to wait until after the EntryCreated listener is done downloading images
      }
    }

    #Log::info(json_encode($item, JSON_PRETTY_PRINT));

    return Response::json([
      'url' => $entry->permalink()
    ], 201)->header('Location', $entry->permalink());
  }

  public function media() {
    $source = $this->_getRequestSource();

    if(get_class($source) == 'Illuminate\Http\JsonResponse')
      return $source;

    if(!Request::hasFile('file')) {
      return response()->json([
        'error' => 'invalid_request',
        'error_description' => 'No file was in the request'
      ], 400);
    }

    $file = Request::file('file');

    $media = Media::createFromUpload($file->path());

    if(!$media) {
      return response()->json([
        'error' => 'invalid_request',
        'error_description' => 'There was a problem uploading the file'
      ], 400);
    }

    $url = $media->url();

    return response()->json([
      'url' => $url,
    ], 201)->header('Location', $url);
  }

  public function entry(Request $request, $source_id, $unique) {
    $entry = Entry::where('source_id', $source_id)->where('unique', $unique)->first();

    // TODO: Should this require authentication?
    return Response::json($entry->to_array());
  }

}
