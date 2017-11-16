<?php
namespace App\Http\Controllers;

use Request, Response, DB, Log;
use App\User, App\Source, App\Channel, App\Entry;
use p3k\XRay;

class WebSubController extends Controller
{

  public function __construct() {
    $this->middleware('websub');
  }

  public function source_callback($token) {
    Log::info('WebSub callback: '.$token);

    $source = Source::where('token', $token)->first();
    if(!$source) {
      return Response::json(['error'=>'not_found'], 404);
    }

    $content_type = Request::header('Content-Type');
    $body = Request::getContent();

    $xray = new XRay();
    $parsed = $xray->parse($source->url, $body, ['expect'=>'feed']);

    if($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] == 'feed') {

      // Check each entry in the feed to see if we've already seen it
      // Add new entries to any channels that include this source
      foreach($parsed['data']['items'] as $item) {

        // Prefer uid, then url, then hash the content
        if(isset($item['uid']))
          $url = '@'.$item['uid'];
        elseif(isset($item['url']))
          $url = $item['url'];
        else
          $url = '#'.md5(json_encode($item));

        $entry = Entry::where('source_id', $source->id)
          ->where('url', $url)->first();

        if(!$entry) {
          $entry = new Entry;
          $entry->source_id = $source->id;
          $entry->url = $url;
          $is_new = true;
        } else {
          $is_new = false;
        }

        $entry->data = json_encode($item, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);

        // Also cache the published date for sorting
        if($item['published'])
          $entry->published = date('Y-m-d H:i:s', strtotime($item['published']));

        $entry->save();

        if($is_new) {
          Log::info("Adding this entry to channels");
          // Loop through each channel associates with this source and add the entry
          foreach($source->channels()->get() as $channel) {
            Log::info("Adding to channel #".$channel->id);
            $channel->entries()->attach($entry->id, ['created_at'=>date('Y-m-d H:i:s')]);
          }
        } else {
          Log::info("Already seen this item");
        }

      }

    } else {
      Log::error('Error parsing source from '.$source->url);
    }

  }

}
