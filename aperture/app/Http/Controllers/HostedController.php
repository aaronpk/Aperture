<?php
/*
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

    $limit = ((int)Request::input('limit')) ?: 20;

    $entries = $channel->entries()
      ->select('entries.*', 'channel_entry.created_at AS added_to_channel_at')
      ->orderByDesc('channel_entry.created_at')
      ->orderByDesc('entries.published')
      ->limit($limit+1); // fetch 1 more than the limit so we know if we've reached the end

    if(Request::input('before')) {
      if(!($before=$this->_parseEntryCursor(Request::input('before')))) {
        return Response::json(['error' => 'invalid_cursor'], 400);
      }

      $entries = $entries->where('channel_entry.created_at', '>', $before[0])
        ->where('entries.published', '>', $before[1]);
    }

    if(Request::input('after')) {
      if(!($after=$this->_parseEntryCursor(Request::input('after')))) {
        return Response::json(['error' => 'invalid_cursor'], 400);
      }

      $entries = $entries->where('channel_entry.created_at', '<=', $after[0])
        ->where('entries.published', '<=', $after[1]);
    }

    $entries = $entries->get();

    $newbefore = false;
    $newafter = false;
    $items = [];
    foreach($entries as $i=>$entry) {
      if($i == 0) // Always include a cursor to be able to return newer entries
        $newbefore = $this->_buildEntryCursor($entry);

      if($i < $limit)
        $items[] = [
          'entry' => $entry,
          'data' => $entry->to_array()
        ];
      
      if($i == $limit) // Don't add the last item, but return a cursor for the next page
        $newafter = $this->_buildEntryCursor($entry);
    }

    $paging = [];

    if($newbefore && $newbefore != Request::input('after'))
      $paging['before'] = $newbefore;

    if($newafter)
      $paging['after'] = $newafter;

    return view('hosted/timeline', [
      'channel' => $channel,
      'items' => $items, 
      'paging' => $paging
    ]);
  }

}
*/
