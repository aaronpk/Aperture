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

    $input = file_get_contents('php://input');
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

    $entry = new Entry();
    $entry->source_id = $source->id;
    $entry->unique = str_random(32);
    $entry->data = json_encode($item, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);
    if(isset($item['published']))
      $entry->published = date('Y-m-d H:i:s', strtotime($item['published']));
    $entry->save();

    Log::info("Adding entry ".$entry->unique." to channels");
    foreach($source->channels()->get() as $channel) {
      Log::info("  Adding to channel #".$channel->id);
      $channel->entries()->attach($entry->id, ['created_at'=>date('Y-m-d H:i:s')]);
    }

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
