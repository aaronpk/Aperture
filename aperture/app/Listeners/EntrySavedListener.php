<?php
namespace App\Listeners;

use App\Events\EntrySaved;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Log, Storage, File;
use IMagick;
use App\Entry, App\Media;
use DOMXPath, DOMDocument;

class EntrySavedListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  SourceAdded  $event
     * @return void
     */
    public function handle(EntrySaved $event)
    {
        if(!env('MEDIA_URL'))
            return;

        $modified = false;

        $data = json_decode($event->entry->data, true);

        // Find any external image and video URLs, download a copy, and rewrite the entry

        if(isset($data['photo'])) {
            if(!is_array($data['photo']))
                $data['photo'] = [$data['photo']];
            foreach($data['photo'] as $i=>$photo) {
                $file = $this->_download($event->entry, $photo);
                $url = is_string($file) ? $file : $file->url();
                $modified = $modified || ($url != $photo);
                $data['photo'][$i] = $url;
            }
        }

        if(isset($data['video'])) {
            if(!is_array($data['video']))
                $data['video'] = [$data['video']];
            foreach($data['video'] as $i=>$video) {
                $file = $this->_download($event->entry, $video);
                $url = is_string($file) ? $file : $file->url();
                $modified = $modified || ($url != $video);
                $data['video'][$i] = $url;
            }
        }

        if(isset($data['audio'])) {
            if(!is_array($data['audio']))
                $data['audio'] = [$data['audio']];
            foreach($data['audio'] as $i=>$audio) {
                $file = $this->_download($event->entry, $audio);
                $url = is_string($file) ? $file : $file->url();
                $modified = $modified || ($url != $audio);
                $data['audio'][$i] = $url;
            }
        }

        // TODO: dive into refs and extract URLs from there


        // parse HTML content and find <img> tags
        if(isset($data['content']['html']) && $data['content']['html']) {
            $map = [];

            $doc = new DOMDocument();
            @$doc->loadHTML(self::toHtmlEntities($data['content']['html']));
            if($doc) {
                $xpath = new DOMXPath($doc);
                foreach($xpath->query('//img') as $el) {
                    $src = ''.$el->getAttribute('src');
                    if($src) {
                        #Log::info('Found img in html: '.$src);
                        $file = $this->_download($event->entry, $src);
                        $map[$src] = is_string($file) ? $file : $file->url();
                        $modified = $modified || ($src != $map[$src]);
                    }
                }
            }

            foreach($map as $original=>$new) {
                $data['content']['html'] = str_replace($original, $new, $data['content']['html']);
            }
        }


        if(isset($data['author']['photo']) && $data['author']['photo']) {
            $file = $this->_download($event->entry, $data['author']['photo'], 256);
            $url = is_string($file) ? $file : $file->url();
            $modified = $modified || ($url != $data['author']['photo']);
            $data['author']['photo'] = $url;
        }

        if($modified) {
            $event->entry->data = json_encode($data, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);
            $event->entry->save();
        }
    }

    private static function toHtmlEntities($input) {
      return mb_convert_encoding($input, 'HTML-ENTITIES', mb_detect_encoding($input));
    }

    private function _imageProxy($url) {
      $hex = bin2hex($url);
      if(strlen($hex) > 255)
        return $url;
      $signature = hash_hmac('sha1', $url, env('IMG_PROXY_KEY'));
      $proxy = env('IMG_PROXY_URL').$signature.'/'.$hex;
      return $proxy;
    }

    private function _download(Entry $entry, $url, $maxSize=false) {
      if(!$entry->source->download_images || !env('MEDIA_URL'))
        return $this->_imageProxy($url);

      $media = Media::createFromURL($url, $maxSize);

      if($media && is_object($media)) {
        $entry->media()->attach($media->id);
        return $media;
      } else {
        Log::info('Failed to download file, returning proxy URL instead');
        return $this->_imageProxy($url);
      }
    }
}
