<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
  use AuthorizesRequests, DispatchesJobs, ValidatesRequests;


  protected function _buildEntryCursor($entry) {
    // if(env('APP_ENV') == 'testing')
    //   return $entry['added_to_channel_at']
    //     .'  '.$entry['published'];
    // else
      return \p3k\b10to60(strtotime($entry['added_to_channel_at']))
        .':'.\p3k\b10to60(strtotime($entry['published']));
  }

  protected function _parseEntryCursor($cursor) {
    // if(env('APP_ENV') == 'testing')
    //   if(preg_match('/([0-9\-]{10} [0-9:]{8})  ([0-9\-]{10} [0-9:]{8})/', $cursor, $match)) {
    //     return [$match[1], $match[2]];
    //   }
    // else
      if(preg_match('/([0-9a-zA-Z_]{6}):([0-9a-zA-Z_]{6})/', $cursor, $match)) {
        return [date('Y-m-d H:i:s', \p3k\b60to10($match[1])), date('Y-m-d H:i:s', \p3k\b60to10($match[2]))];
      }
    return false;
  }
}
