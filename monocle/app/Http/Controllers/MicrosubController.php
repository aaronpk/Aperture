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
      'channels' => 'channels'
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

    if(Request::input('cursor')) {
      if(!($cursor=$this->_parseEntryCursor(Request::input('cursor')))) {
        return Response::json(['error' => 'invalid_cursor'], 400);
      }

      $entries = $entries->where('channel_entry.created_at', '<=', $cursor[0])
        ->where('entries.published', '<=', $cursor[1]);
    }

    $entries = $entries->get();

    $cursor = false;
    $items = [];
    foreach($entries as $i=>$entry) {
      if($i < $limit)
        $items[] = $entry->to_array();
      else
        $cursor = $this->_buildEntryCursor($entry);
    }

    return Response::json([
      'channel' => $channel,
      'items' => $items,
      'cursor' => $cursor,
    ]);
  }

  private function _buildEntryCursor($entry) {
    return dechex(strtotime($entry['added_to_channel_at']))
      .':'.dechex(strtotime($entry['created_at']));
  }

  private function _parseEntryCursor($cursor) {
    if(preg_match('/([0-9a-f]{8}):([0-9a-f]{8})/', $cursor, $match)) {
      return [date('Y-m-d H:i:s', hexdec($match[1])), date('Y-m-d H:i:s', hexdec($match[2]))];
    }
    return false;
  }
}
