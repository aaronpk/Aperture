<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use Storage, Log;
use App\Events\MediaDeleting;
use IMagick;

class Media extends Model {

  protected $fillable = [
    'entry_id', 'filename'
  ];

  protected $dispatchesEvents = [
    'deleting' => MediaDeleting::class
  ];

  public function entries() {
    return $this->belongsToMany('\App\Entry');
  }

  public function url() {
    return env('MEDIA_URL').'/'.$this->filename;
  }

  public static function createFromURL($url, $maxSize=false) {

    $host = parse_url($url, PHP_URL_HOST);

    if($host == parse_url(env('MEDIA_URL'), PHP_URL_HOST))
      return $url;

    @mkdir(sys_get_temp_dir().'/aperture', 0755);
    $filedata = tempnam(sys_get_temp_dir().'/aperture', 'file-data');
    $fileheader = tempnam(sys_get_temp_dir().'/aperture', 'file-header');

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

    fclose($fd);
    fclose($fh);

    $media = false;
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if(!curl_errno($ch) && $code == 200) {
      $media = self::_createFromFile($url, $filedata, $maxSize);
    } else {
      Log::info('Media file '.$url.' returned '.$code);
    }

    unlink($filedata);
    unlink($fileheader);

    return $media;
  }

  public static function createFromUpload($filedata, $maxSize=false) {
    $hash = hash_file('sha256', $filedata);
    $media = self::_createFromFile(env('APP_URL').'/media/'.$hash, $filedata, $maxSize);
    return $media;
  }

  private static function _createFromFile($url, $filedata, $maxSize=false) {
    $host = parse_url($url, PHP_URL_HOST);

    $hash = hash_file('sha256', $filedata);
    $ext = self::_file_extension($filedata);

    $filename = $host.'/'.$hash.$ext;
    $storagefilename = 'media/'.$filename;

    // Check if the file exists already
    $media = Media::where('filename', $filename)->first();
    if(!$media) {
      $media = new Media();
      $media->original_url = substr($url, 0, 1024);
      $media->filename = $filename;
      $media->hash = $hash;
      $media->bytes = filesize($filedata);

      $resized = false;

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

          $resized = tempnam(sys_get_temp_dir().'/aperture', 'resized-').$ext;
          $im->writeImage($resized);
          $im->destroy();
          $fp = fopen($resized, 'r');
      } else {
          $fp = fopen($filedata, 'r');
      }

      Storage::makeDirectory(dirname($storagefilename));
      Storage::put($storagefilename, $fp);
      fclose($fp);

      if($resized)
        unlink($resized);

      $media->save();
      Log::info("Stored file ".$url." at url: ".$media->url());
    }
    return $media;
  }

  private static function _file_extension($filename) {
      // Detect the file extension
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      $mimetype = finfo_file($finfo, $filename);

      if($mimetype) {
          if(preg_match('/jpeg/', $mimetype))
              $ext = '.jpg';
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
