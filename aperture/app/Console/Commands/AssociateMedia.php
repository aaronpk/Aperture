<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\User, App\Channel, App\Source, App\Entry, App\Media;
use DB;

class AssociateMedia extends Command
{
  protected $signature = 'data:linkmedia';
  protected $description = 'Go through all entries and add associated media records';

  public function handle()
  {
    Entry::where('id','>',120000)->limit(10)->chunk(100, function($entries){
      foreach($entries as $entry) {
        $json = json_decode($entry->data, true);

        #$this->info($entry->data);

        $media = [];

        if(!empty($json['author']['photo']))
          $media[] = $json['author']['photo'];

        if(isset($json['photo']) && is_array($json['photo'])) {
          $media = array_merge($media, $json['photo']);
        }

        if(isset($json['video']) && is_array($json['video'])) {
          $media = array_merge($media, $json['video']);
        }

        if(isset($json['audio']) && is_array($json['audio'])) {
          $media = array_merge($media, $json['audio']);
        }

        $media = array_map(function($url){
          return str_replace(env('MEDIA_URL').'/', '', $url);
        }, $media);

        if(count($media)) {
          $this->info($entry->id." ".$entry->unique);
          $this->info(implode("\n", $media));

          foreach($media as $filename) {
            if(!preg_match('/^http/', $filename)) {
              $file = Media::where('filename', $filename)->first();
              if($file) {
                $entry->media()->attach($file->id);
              }
            }
          }
        }

      }
    });
  }

}
