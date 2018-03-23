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
                $url = $this->_download($event->entry, $photo);
                $modified = $modified || ($url != $photo);
                $data['photo'][$i] = $url;
            }
        }

        if(isset($data['video'])) {
            if(!is_array($data['video']))
                $data['video'] = [$data['video']];
            foreach($data['video'] as $i=>$video) {
                $url = $this->_download($event->entry, $video);
                $modified = $modified || ($url != $video);
                $data['video'][$i] = $url;
            }
        }

        if(isset($data['audio'])) {
            if(!is_array($data['audio']))
                $data['audio'] = [$data['audio']];
            foreach($data['audio'] as $i=>$audio) {
                $url = $this->_download($event->entry, $audio);
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
                        Log::info('Found img in html: '.$src);
                        $map[$src] = $this->_download($event->entry, $src);
                    }
                }
            }

            foreach($map as $original=>$new) {
                $data['content']['html'] = str_replace($original, $new, $data['content']['html']);
            }
        }


        if(isset($data['author']['photo']) && $data['author']['photo']) {
            $url = $this->_download($event->entry, $data['author']['photo'], 256);
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

    private function _download(Entry $entry, $url, $maxSize=false) {

        $host = parse_url($url, PHP_URL_HOST);

        if($host == parse_url(env('MEDIA_URL'), PHP_URL_HOST))
            return $url;

        $filedata = tempnam(sys_get_temp_dir(), 'aperture');
        $fileheader = tempnam(sys_get_temp_dir(), 'aperture');

        $fd = fopen($filedata, 'w');
        $fh = fopen($fileheader, 'w');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FILE, $fd);
        curl_setopt($ch, CURLOPT_WRITEHEADER, $fh);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 4000);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 10000);
        curl_exec($ch);

        if(curl_errno($ch))
            return $url;

        fclose($fd);
        fclose($fh);

        $hash = hash_file('sha256', $filedata);
        $ext = $this->_file_extension($filedata);

        $filename = $host.'/'.$hash.$ext;
        $path = env('MEDIA_URL').'/'.$filename;
        $storagefilename = 'media/'.$filename;

        // Check if the file exists already
        if(!Storage::exists($storagefilename) || Storage::lastModified($storagefilename) == 0) {
            $media = new Media();
            $media->entry_id = $entry->id;
            $media->original_url = $url;
            $media->filename = $filename;
            $media->hash = $hash;
            $media->bytes = filesize($filedata);

            // Resize and store a copy
            if(in_array($ext, ['.jpg','.png','.gif']) && $maxSize) {
                $fp = fopen($filedata, 'r');
                $im = new Imagick();
                $im->readImageFile($fp);

                $d = $im->getImageGeometry();
                $media->width = $d['width'];
                $media->height = $d['height'];

                switch($ext) {
                  case '.jpg':
                    $im->setImageFormat('jpg');
                    break;
                  case '.gif':
                  case '.png':
                    $im->setImageFormat('png');
                    $ext = '.png';
                    break;
                }
                $im->setImageCompressionQuality(85);

                $im->setGravity(\Imagick::GRAVITY_CENTER);
                $im->cropThumbnailImage($maxSize, $maxSize);

                $resized = tempnam(sys_get_temp_dir(), 'aperture').$ext;
                $im->writeImage($resized);
                $im->destroy();
                $fp = fopen($resized, 'r');
            } else {
                $fp = fopen($filedata, 'r');
            }

            Storage::makeDirectory(dirname($storagefilename));
            Storage::put($storagefilename, $fp);
            Log::info("Entry ".$entry->id.": Stored file at url: $path");

            $media->save();
        }

        unlink($filedata);
        unlink($fileheader);

        return $path;
    }

    private function _file_extension($filename) {
        // Detect the file extension
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimetype = finfo_file($finfo, $filename);

        if($mimetype) {
            if(preg_match('/jpeg/', $mimetype))
                $ext = '.' . str_replace('jpeg','jpg',explode('/', $mimetype)[1]);
            elseif(preg_match('/gif/', $mimetype))
                $ext = '.gif';
            elseif(preg_match('/png/', $mimetype))
                $ext = '.png';
            elseif(preg_match('/svg/', $mimetype))
                $ext = '.svg';
            elseif($mimetype == 'audio/mpeg')
                $ext = '.mp3';
            elseif($mimetype == 'video/mp4')
                $ext = '.mp4';
            else
                $ext = '.'.explode('/', $mimetype)[1];
        } else {
            $ext = '';
        }
        return $ext;
    }
}
