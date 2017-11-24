<?php
namespace App\Http\Controllers;

use Request, Response, DB, Log, Auth;
use App\User, App\Source, App\Channel, App\Entry;
use p3k\XRay;

class MicrosubController extends Controller
{
  private function _verifyScope($expect) {
    return in_array($expect, Request::get('token_data')['scope']);
  }

  private function _verifyAction($action) {
    $actions = [
      'timeline' => 'read',
      'follow' => 'follow',
      'unfollow' => 'follow',
      'mute' => 'mute',
      'unmute' => 'mute',
      'block' => 'block',
      'unblock' => 'block',
      'channels' => 'channels',
      'search' => '',
    ];
    if(!array_key_exists(Request::input('action'), $actions)) {
      return Response::json([
        'error' => 'bad_request', 
        'error_description' => 'This operation is not supported'
      ], 400);
    }
    return true;
  }

  private function _getRequestChannel() {
    $uid = Request::input('channel') ?: 'default';
    $channel = Channel::where('user_id', Auth::user()->id)->where('uid', $uid)->first();
    if(!$channel)
      return Response::json([
        'error' => 'not_found',
        'error_description' => 'Channel not found'
      ], 404);
    else
      return $channel;
  }

  public function get(Request $request) {
    $token_data = Request::get('token_data');

    $verify = $this->_verifyAction(Request::input('action'));
    if($verify !== true)
      return $verify;

    // TODO: Verify the scopes of this token

    $action = Request::input('action');

    if(!method_exists($this, 'get_'.$action))
      return Response::json([
        'error' => 'not_implemented',
        'error_description' => 'This method has not yet been implemented'
      ], 400);

    return $this->{'get_'.$action}();
  }

  public function post(Request $request) {
    $token_data = Request::get('token_data');

    $verify = $this->_verifyAction(Request::input('action'));
    if($verify !== true)
      return $verify;

    $action = Request::input('action');

    if(!method_exists($this, 'post_'.$action))
      return Response::json([
        'error' => 'not_implemented',
        'error_description' => 'This method has not yet been implemented'
      ], 400);
    
    // TODO: Verify the scopes of this token

    return $this->{'post_'.$action}();
  }

  //////////////////////////////////////////////////////////////////////////////////

  private function get_channels() {
    $channels = [];

    foreach(Auth::user()->channels()->get() as $channel) {
      $channels[] = $channel->to_array();
    }

    return [
      'channels' => $channels
    ];
  }

  private function post_search() {
    if(Request::input('channel') == null) {
      // Search for feeds matching the query

      if(!Request::input('query')) {
        return Response::json(['error' => 'invalid_query'], 400);
      }

      // The query might be:
      // * a full URL
      // * a term that could be normalized to a URL (e.g. "example.com")
      // * a generic term

      $url = false;
      $query = Request::input('query');

      if(\p3k\url\is_url($query)) {
        // Directly entered URL including scheme
        $url = \p3k\url\normalize($query);
      } else {
        if(preg_match('/^[a-z][a-z0-9]+$/', $query)) {
          // if just a word was entered, append .com
          $possible_url = $query . '.com';
        } else {
          $possible_url = $query;
        }
        // Possible URL that may require adding a scheme
        $possible_url = \p3k\url\normalize($possible_url);
        // Check if the hostname has at least one dot
        if(strpos(parse_url($possible_url, PHP_URL_HOST), '.') !== false) {
          $url = $possible_url;
        }
      }

      $http = new \p3k\HTTP();
      $http->set_user_agent(env('USER_AGENT'));
      $http->timeout = 4;

      $xray = new \p3k\XRay();
      $xray->http = $http;
      $response = $xray->feeds($url);

      $feeds = [];

      if(!isset($response['error']) && $response['code'] == 200) {
        $feeds = $response['feeds'];
      }

      // TODO: also search existing feeds in the database that may be indexed


      return Response::json([
        'results' => $feeds
      ]);
    } else {
      // TODO: Search within channels for posts matching the query


    }
  }

  private function get_timeline() {
    $channel = $this->_getRequestChannel();

    // Check that the channel exists
    if(get_class($channel) != Channel::class)
      return $channel;

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

    #Log::info('timeline request: before='.Request::input('before').' after='.Request::input('after'));

    $entries = $entries->get();

    $newbefore = false;
    $newafter = false;
    $items = [];
    foreach($entries as $i=>$entry) {
      if($i == 0) // Always include a cursor to be able to return newer entries
        $newbefore = $this->_buildEntryCursor($entry);

      if($i < $limit)
        $items[] = $entry->to_array();
      
      if($i == $limit) // Don't add the last item, but return a cursor for the next page
        $newafter = $this->_buildEntryCursor($entry);
    }

    $response = [
      'items' => $items,
      'limit' => $limit,
      'paging' => []
    ];

    if($newbefore && $newbefore != Request::input('after'))
      $response['paging']['before'] = $newbefore;

    if($newafter)
      $response['paging']['after'] = $newafter;

    #Log::info('new paging: '.json_encode($response['paging']));

    return Response::json($response);
  }

  private function get_follow() {
    $channel = $this->_getRequestChannel();

    if(get_class($channel) != Channel::class)
      return $channel;

    $following = [];

    $sources = $channel->sources()
      ->where('url', '!=', '')
      ->get();

    foreach($sources as $s) {
      $following[] = [
        'url' => $s->url
      ];
    }

    return [
      'following' => $following
    ];
  }

  private function _buildEntryCursor($entry) {
    // if(env('APP_ENV') == 'testing')
    //   return $entry['added_to_channel_at']
    //     .'  '.$entry['published'];
    // else
      return dechex(strtotime($entry['added_to_channel_at']))
        .':'.dechex(strtotime($entry['published']));
  }

  private function _parseEntryCursor($cursor) {
    // if(env('APP_ENV') == 'testing')
    //   if(preg_match('/([0-9\-]{10} [0-9:]{8})  ([0-9\-]{10} [0-9:]{8})/', $cursor, $match)) {
    //     return [$match[1], $match[2]];
    //   }
    // else
      if(preg_match('/([0-9a-f]{8}):([0-9a-f]{8})/', $cursor, $match)) {
        return [date('Y-m-d H:i:s', hexdec($match[1])), date('Y-m-d H:i:s', hexdec($match[2]))];
      }
    return false;
  }
}
