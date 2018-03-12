<?php
namespace App\Listeners;

use App\Events\EntrySaved;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Log, Storage, File;
use IMagick;
use App\Entry, App\Media;

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
        $data = json_decode($event->entry->data, true);

        // Find any external image and video URLs, download a copy, and rewrite the entry

        if(isset($data['photo'])) {
            if(!is_array($data['photo']))
                $data['photo'] = [$data['photo']];
            foreach($data['photo'] as $i=>$photo) {
                $data['photo'][$i] = $this->_download($event->entry, $photo);
            }
        }

        if(isset($data['video'])) {
            if(!is_array($data['video']))
                $data['video'] = [$data['video']];
            foreach($data['video'] as $i=>$video) {
                $data['video'][$i] = $this->_download($event->entry, $video);
            }
        }

        if(isset($data['audio'])) {
            if(!is_array($data['audio']))
                $data['audio'] = [$data['audio']];
            foreach($data['audio'] as $i=>$audio) {
                $data['audio'][$i] = $this->_download($event->entry, $audio);
            }
        }

        if(isset($data['author']['photo']) && $data['author']['photo']) {
            $url = $this->_download($event->entry, $data['author']['photo'], 256);
            $data['author']['photo'] = $url;
        }

        $event->entry->data = json_encode($data, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);
        $event->entry->save();
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
        if(!Storage::exists($storagefilename)) {
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

                $im->setImageFormat('jpg');
                $im->setImageCompressionQuality(85);

                $im->setGravity(\Imagick::GRAVITY_CENTER);
                $im->cropThumbnailImage($maxSize, $maxSize);

                $resized = tempnam(sys_get_temp_dir(), 'aperture').'.jpg';
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
