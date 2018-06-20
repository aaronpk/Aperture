<?php
namespace App\Http\Controllers;

use Request, Response, DB, Log;
use App\User, App\Source, App\Channel, App\Entry;
use p3k\XRay;
use App\Events\EntrySaved;

class WebSubReceiverController extends Controller
{

  public function __construct() {
    $this->middleware('websub');
  }

  public function source_callback($token) {
    Log::info('WebSub callback: '.$token);

    $source = Source::where('token', $token)->first();
    if(!$source) {
      Log::warning('Source not found');
      return Response::json(['error'=>'not_found'], 404);
    }

    if($source->channels()->count() == 0) {
      Log::warning('Source:'.$source->id.' ('.parse_url($source->url,PHP_URL_HOST).') is not associated with any channels, skipping and unsubscribing');
      \App\Jobs\UnsubscribeSource::dispatch($source);
      return Response::json(['result'=>'empty'], 200);
    }

    $source_is_empty = $source->entries()->count() == 0;

    $content_type = Request::header('Content-Type');
    $body = Request::getContent();

    $xray = new XRay();
    $parsed = $xray->parse($source->url, $body, ['expect'=>'feed']);

    if($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] == 'feed') {

      // Check each entry in the feed to see if we've already seen it
      // Add new entries to any channels that include this source
      foreach($parsed['data']['items'] as $i=>$item) {

        // Prefer uid, then url, then hash the content
        if(isset($item['uid']))
          $unique = '@'.$item['uid'];
        elseif(isset($item['url']))
          $unique = $item['url'];
        else
          $unique = '#'.md5(json_encode($item));

        // TODO: If the entry reports a URL that is different from the domain that the feed is from,
        // kick off a job to fetch the original post and process it rather than using the data from the feed.

        $data = json_encode($item, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);

        $entry = Entry::where('source_id', $source->id)
          ->where('unique', $unique)->first();

        if(!$entry) {
          $entry = new Entry;
          $entry->source_id = $source->id;
          $entry->unique = $unique;
          $is_new = true;
        } else {
          $is_new = false;
          $hash = md5($data);
        }

        $entry->data = $data;

        // Also cache the published date for sorting
        if(isset($item['published']))
          $entry->published = date('Y-m-d H:i:s', strtotime($item['published']));

        if($is_new || md5($entry->data) != $hash) {
          $entry->save();
          event(new EntrySaved($entry));
        }

        if($is_new) {
          Log::info("Adding entry ".$entry->unique." to channels");
          // Loop through each channel associated with this source and add the entry
          foreach($source->channels()->get() as $channel) {

            $shouldAdd = $channel->should_add_entry($entry);

            if($shouldAdd) {
              Log::info("  Adding to channel '".$channel->name." #".$channel->id);
              // If the source was previously empty, use the published date on the entry in
              // order to avoid flooding the channel with new posts
              // TODO: it's possible that this will create a conflicting record based on the published_date and batch_order.
              // To really solve this, we'd need to first query the channel_entry table to find any records that match
              // the `created_at` we're about to use, and increment the `batch_order` higher than any that were found.
              // This is likely rare enough that I'm not going to worry about it for now.
              $created_at = ($source_is_empty && $entry->published ? $entry->published : date('Y-m-d H:i:s'));
              if(strtotime($created_at) <= 0) $created_at = '1970-01-01 00:00:01';

              $channel->entries()->attach($entry->id, [
                'created_at' => $created_at,
                'seen' => ($channel->read_tracking_mode == 'disabled' || $source_is_empty ? 1 : 0),
                'batch_order' => $i,
              ]);
            } else {
              Log::info("  Skipping channel '".$channel->name." #".$channel->id.' due to filter');
            }
          }
        } else {
          #Log::info("Already seen this item");
        }

      }

    } else {
      Log::error('Error parsing source from '.$source->url);
    }

  }

}
