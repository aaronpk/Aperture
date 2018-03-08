<?php
namespace App\Http\Controllers;

use Request, Response, DB, Log, Auth;
use App\User, App\Source, App\Channel, App\Entry, App\ChannelToken;
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

  public function post(Request $request) {
    $source = $this->_getRequestSource();

    if(get_class($source) == 'Illuminate\Http\JsonResponse')
      return $source;

    $input = file_get_contents('php://input');

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

    $entry->data = json_encode($item, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);
    if(isset($item['published']))
      $entry->published = date('Y-m-d H:i:s', strtotime($item['published']));
    $entry->save();

    if($new) {
      Log::info("Adding entry ".$entry->unique." to channels");
      foreach($source->channels()->get() as $channel) {
        Log::info("  Adding to channel #".$channel->id);
        $channel->entries()->attach($entry->id, ['created_at'=>date('Y-m-d H:i:s')]);
      }
    }

    Log::info(json_encode($item, JSON_PRETTY_PRINT));

    return Response::json([
      'url' => $entry->permalink()
    ], 201)->header('Location', $entry->permalink());
  }

  public function entry(Request $request, $source_id, $unique) {
    // TODO: Should this require authentication?

    $entry = Entry::where('source_id', $source_id)->where('unique', $unique)->first();

    return Response::json($entry->to_array());
  }

}
