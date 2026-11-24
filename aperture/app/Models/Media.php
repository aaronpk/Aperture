<?php
namespace App\Models;

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
    return $this->belongsToMany('\App\Models\Entry');
  }

  public function url() {
    return config('aperture.media_url').'/'.$this->filename;
  }

  public static function createFromURL($url, $maxSize=false) {

    $host = parse_url($url, PHP_URL_HOST);

    if($host == parse_url(config('aperture.media_url'), PHP_URL_HOST))
      return $url;

    @mkdir(sys_get_temp_dir().'/aperture', 0755);
    $filedata = tempnam(sys_get_temp_dir().'/aperture', 'file-data');

    // The URL comes from a feed or a Micropub post. Whatever it returns is stored and
    // served publicly, so it must not be able to reach anything on a private network.
    $response = app(\App\Services\SafeDownload::class)->toFile($url, $filedata, 4000, 10000);

    $media = false;

    if(!$response['error'] && $response['code'] == 200) {
      $media = self::_createFromFile($url, $filedata, $maxSize);
    } else {
      Log::info('Media file '.$url.' returned '.$response['code'].' '.$response['error']);
    }

    unlink($filedata);

    return $media;
  }

  public static function createFromUpload($filedata, $maxSize=false) {
    $hash = hash_file('sha256', $filedata);
    $media = self::_createFromFile(config('app.url').'/media/'.$hash, $filedata, $maxSize);
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

  /**
   * Whether a file is one of the image types that are safe to open with Imagick.
   * Other formats it understands, such as SVG and MVG, can refer to other files and URLs.
   */
  public static function is_bitmap_image($filename) {
    return in_array(self::_file_extension($filename), ['.jpg', '.png', '.gif', '.webp']);
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
