<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Storage, Log;
use App\Events\MediaDeleting;
use Imagick;

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
      if(in_array($ext, ['.jpg','.png','.gif']) && $maxSize && !self::_use_imagick()) {
          // Imagick isn't installed, so use GD
          $thumbnail = self::_thumbnail_with_gd($filedata, $ext, $maxSize);
          if(!$thumbnail) {
            Log::info('Could not resize '.$url);
            return false;
          }
          $media->width = $thumbnail['width'];
          $media->height = $thumbnail['height'];
          $resized = $thumbnail['path'];
          $fp = fopen($resized, 'r');
      } elseif(in_array($ext, ['.jpg','.png','.gif']) && $maxSize) {
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
   * Which library resizes images: null picks Imagick when the extension is installed
   * and GD otherwise. Tests set it to 'imagick' or 'gd' to exercise each one.
   */
  public static $image_library = null;

  // GD decodes the whole image into PHP's memory, so refuse anything unreasonably large
  const MAX_GD_PIXELS = 40000000;

  private static function _use_imagick() {
    if(self::$image_library)
      return self::$image_library == 'imagick';
    return extension_loaded('imagick');
  }

  /**
   * Does what Imagick's cropThumbnailImage does: scales the image to fill a square of
   * $size pixels and crops the overflow evenly from both sides. JPEGs stay JPEGs at
   * quality 85; PNGs and GIFs are written as PNG, keeping transparency.
   *
   * @return array|false ['width' => original width, 'height' => original height, 'path' => the thumbnail]
   */
  private static function _thumbnail_with_gd($filedata, $ext, $size) {
    $dimensions = @getimagesize($filedata);
    if(!$dimensions || $dimensions[0] < 1 || $dimensions[1] < 1)
      return false;

    list($width, $height) = $dimensions;
    if($width * $height > self::MAX_GD_PIXELS)
      return false;

    switch($ext) {
      case '.jpg': $source = @imagecreatefromjpeg($filedata); break;
      case '.png': $source = @imagecreatefrompng($filedata); break;
      case '.gif': $source = @imagecreatefromgif($filedata); break;
      default: $source = false;
    }
    if(!$source)
      return false;

    // The largest centered square of the original
    $side = min($width, $height);
    $x = intdiv($width - $side, 2);
    $y = intdiv($height - $side, 2);

    $thumbnail = imagecreatetruecolor($size, $size);
    if($ext != '.jpg') {
      imagealphablending($thumbnail, false);
      imagesavealpha($thumbnail, true);
      imagefill($thumbnail, 0, 0, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
    }
    imagecopyresampled($thumbnail, $source, 0, 0, $x, $y, $size, $size, $side, $side);

    $path = tempnam(sys_get_temp_dir().'/aperture', 'resized-');
    $written = $ext == '.jpg' ? imagejpeg($thumbnail, $path, 85) : imagepng($thumbnail, $path);

    if(!$written) {
      @unlink($path);
      return false;
    }

    return ['width' => $width, 'height' => $height, 'path' => $path];
  }

  /**
   * Whether a file is a plain bitmap image. Formats such as SVG and MVG can refer to
   * other files and URLs, so they are never opened to read their dimensions.
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
