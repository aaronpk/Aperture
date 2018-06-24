<?php
namespace App\Http\Controllers;

use Request, Response, DB, Log;
use App\User, App\Source, App\Channel, App\Entry, App\Media;
use Illuminate\Support\Facades\Redis;

class StatsController extends Controller
{

  public function __construct() {
    $this->middleware('munin');
  }

  private function _text($text) {
    return response($text)->header('Content-type', 'text/plain');
  }

  public function users() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Users
graph_info Total number of Aperture user accounts
graph_vlabel Users
graph_category aperture
graph_args --lower-limit 0
graph_scale yes

users.label Users
users.type GAUGE
users.min 0
";
    } else {
      $users = User::count();
      $response = 'users.value '.$users;
    }
    return $this->_text($response);
  }

  public function new_entries() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture New Entries
graph_info The rate of new entries being added
graph_vlabel Entries per minute
graph_category aperture
graph_args --lower-limit 0
graph_scale yes
graph_period minute

new_entries.label Entries added per minute
new_entries.type DERIVE
new_entries.min 0
";
    } else {
      $entries = DB::SELECT('SELECT AUTO_INCREMENT FROM information_schema.tables WHERE table_schema="'.env("DB_DATABASE").'" AND table_name="entries"');
      $response = 'new_entries.value '.$entries[0]->AUTO_INCREMENT;
    }

    return $this->_text($response);
  }

  public function entries() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Entries
graph_info The total entries currently stored
graph_vlabel Entries
graph_category aperture
graph_args --lower-limit 0
graph_scale yes

entries.label Entries
entries.type GAUGE
entries.min 0
";
    } else {
      $entries = Redis::get(env('APP_URL').'::entries');
      $response = 'entries.value '.$entries;
    }

    return $this->_text($response);
  }

  public function entries_size() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Entries
graph_info The size on disk of entries
graph_vlabel Entries
graph_category aperture
graph_args --lower-limit 0 --base 1024
graph_scale yes

size.label Size on Disk
size.type GAUGE
size.min 0
";
    } else {
      $size = DB::SELECT('SELECT data_length - data_free AS bytes FROM information_schema.tables WHERE table_schema="aperture" AND table_name="entries"');
      $response = "size.value ".$size[0]->bytes;
    }
    return $this->_text($response);
  }

  public function sources() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Sources
graph_info Total number of active sources in Aperture
graph_vlabel Sources
graph_category aperture
graph_args --lower-limit 0
graph_scale yes

sources.label Sources
sources.type GAUGE
sources.min 0
";
    } else {
      $sources = DB::SELECT('SELECT COUNT(*) AS num FROM (SELECT COUNT(*) FROM `sources` INNER JOIN `channel_source` ON (`channel_source`.`source_id` = `sources`.`id`) GROUP BY sources.id) AS tmp');
      $response = 'sources.value '.$sources[0]->num;
    }
    return $this->_text($response);
  }

  public function media_size() {
    if(Request::input('mode') == 'config') {
      $response = "graph_title Aperture Media
graph_info Total size of media stored in Aperture
graph_vlabel Bytes
graph_category aperture
graph_args --lower-limit 0 --base 1024
graph_scale yes

known.label Bytes Known
known.type GAUGE
known.min 0
disk.label Bytes on Disk
disk.type GAUGE
disk.min 0
";
    } else {
      $known = Media::sum('bytes');
      $du = shell_exec('du -sk ../storage/app/media');
      if(preg_match('/(\d+)/', $du, $match)) {
        $disk = ((int)$match[1])*1024;
      } else {
        $disk = null;
      }
      $response = 'known.value '.$known."\n";
      $response .= 'disk.value '.$disk;
    }
    return $this->_text($response);
  }

}
