<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Support\FakeDownload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Models\Media;
use App\Services\SafeDownload;

/**
 * Author photos are cropped to a square and scaled down. That works with either
 * Imagick or GD, so the Imagick extension is optional.
 */
class MediaResizeTest extends TestCase
{
  use RefreshDatabase;

  private $download;

  protected function setUp(): void {
    parent::setUp();
    Storage::fake('local');
    config(['aperture.media_url' => 'https://media.aperture.test']);
    $this->download = new FakeDownload(app(\p3k\HTTP\Guard::class));
    $this->app->instance(SafeDownload::class, $this->download);
  }

  protected function tearDown(): void {
    Media::$image_library = null;
    parent::tearDown();
  }

  public static function libraries() {
    return ['GD' => ['gd'], 'Imagick' => ['imagick']];
  }

  private function useLibrary($library) {
    if($library == 'imagick' && !extension_loaded('imagick'))
      $this->markTestSkipped('The Imagick extension is not installed');
    Media::$image_library = $library;
  }

  // Three vertical or horizontal bands: red, green, blue
  private function bands($width, $height, $format, $vertical=true) {
    $image = imagecreatetruecolor($width, $height);
    $colors = [imagecolorallocate($image, 220, 0, 0), imagecolorallocate($image, 0, 200, 0), imagecolorallocate($image, 0, 0, 220)];
    foreach($colors as $i => $color) {
      if($vertical)
        imagefilledrectangle($image, (int)($width * $i / 3), 0, (int)($width * ($i + 1) / 3) - 1, $height - 1, $color);
      else
        imagefilledrectangle($image, 0, (int)($height * $i / 3), $width - 1, (int)($height * ($i + 1) / 3) - 1, $color);
    }
    return $this->encode($image, $format);
  }

  private function encode($image, $format) {
    ob_start();
    if($format == 'jpg') imagejpeg($image, null, 95);
    elseif($format == 'png') imagepng($image);
    else imagegif($image);
    return ob_get_clean();
  }

  private function thumbnail($bytes, $name) {
    $this->download->responses['https://cdn.example/'.$name] = ['code' => 200, 'body' => $bytes];
    $media = Media::createFromURL('https://cdn.example/'.$name, 256);
    if(!$media)
      return [$media, null];
    return [$media, Storage::disk('local')->get('media/'.$media->filename)];
  }

  private function dominant($image, $x, $y) {
    $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
    $max = max($rgb['red'], $rgb['green'], $rgb['blue']);
    return array_search($max, ['red' => $rgb['red'], 'green' => $rgb['green'], 'blue' => $rgb['blue']]);
  }

  #[DataProvider('libraries')]
  public function testWideJpegIsCroppedToACenteredSquare($library) {
    $this->useLibrary($library);
    list($media, $stored) = $this->thumbnail($this->bands(600, 400, 'jpg'), 'wide.jpg');

    // The record keeps the original's dimensions
    $this->assertEquals(600, $media->width);
    $this->assertEquals(400, $media->height);
    $this->assertStringEndsWith('.jpg', $media->filename);

    $this->assertEquals([256, 256], array_slice(getimagesizefromstring($stored), 0, 2));
    $this->assertEquals('image/jpeg', getimagesizefromstring($stored)['mime']);

    // The middle 400 pixels of the 600 survive: a quarter red, half green, a quarter blue
    $image = imagecreatefromstring($stored);
    $this->assertEquals('red', $this->dominant($image, 20, 128));
    $this->assertEquals('green', $this->dominant($image, 128, 128));
    $this->assertEquals('blue', $this->dominant($image, 235, 128));
    $this->assertEquals('green', $this->dominant($image, 128, 5));
    $this->assertEquals('green', $this->dominant($image, 128, 250));
  }

  #[DataProvider('libraries')]
  public function testTallPngIsCroppedToACenteredSquare($library) {
    $this->useLibrary($library);
    list($media, $stored) = $this->thumbnail($this->bands(300, 900, 'png', false), 'tall.png');

    $this->assertEquals(300, $media->width);
    $this->assertEquals(900, $media->height);
    $this->assertEquals('image/png', getimagesizefromstring($stored)['mime']);
    $this->assertEquals([256, 256], array_slice(getimagesizefromstring($stored), 0, 2));

    // The middle third of a 300x900 image is exactly the green band
    $image = imagecreatefromstring($stored);
    foreach([[128, 5], [128, 128], [128, 250], [5, 128], [250, 128]] as $point)
      $this->assertEquals('green', $this->dominant($image, $point[0], $point[1]));
  }

  #[DataProvider('libraries')]
  public function testSmallImageIsScaledUp($library) {
    $this->useLibrary($library);
    list($media, $stored) = $this->thumbnail($this->bands(60, 60, 'jpg'), 'small.jpg');

    $this->assertEquals(60, $media->width);
    $this->assertEquals([256, 256], array_slice(getimagesizefromstring($stored), 0, 2));
  }

  #[DataProvider('libraries')]
  public function testPngKeepsItsTransparency($library) {
    $this->useLibrary($library);
    $source = imagecreatetruecolor(400, 400);
    imagealphablending($source, false);
    imagesavealpha($source, true);
    imagefill($source, 0, 0, imagecolorallocatealpha($source, 0, 0, 0, 127));
    imagefilledrectangle($source, 100, 100, 299, 299, imagecolorallocatealpha($source, 220, 0, 0, 0));

    list($media, $stored) = $this->thumbnail($this->encode($source, 'png'), 'alpha.png');

    $image = imagecreatefromstring($stored);
    $corner = imagecolorsforindex($image, imagecolorat($image, 5, 5));
    $middle = imagecolorsforindex($image, imagecolorat($image, 128, 128));
    $this->assertEquals(127, $corner['alpha']);
    $this->assertEquals(0, $middle['alpha']);
    $this->assertGreaterThan(200, $middle['red']);
  }

  #[DataProvider('libraries')]
  public function testGifIsStoredAsPngData($library) {
    $this->useLibrary($library);
    list($media, $stored) = $this->thumbnail($this->bands(300, 300, 'gif'), 'photo.gif');

    $this->assertNotFalse($media);
    $this->assertEquals('image/png', getimagesizefromstring($stored)['mime']);
    $this->assertEquals([256, 256], array_slice(getimagesizefromstring($stored), 0, 2));
  }

  public function testGdRefusesAnImageTooLargeToDecode() {
    Media::$image_library = 'gd';
    // 42 megapixels, but a tiny file: one flat color in a palette image
    $huge = imagecreate(7000, 6000);
    imagecolorallocate($huge, 10, 10, 10);
    $bytes = $this->encode($huge, 'png');
    unset($huge);

    list($media) = $this->thumbnail($bytes, 'huge.png');

    $this->assertFalse($media);
    $this->assertEquals(0, Media::count());
    $this->assertEquals([], Storage::disk('local')->allFiles());
  }

  public function testGdFailsQuietlyOnACorruptImage() {
    Media::$image_library = 'gd';
    $corrupt = substr($this->bands(600, 400, 'jpg'), 0, 200).str_repeat("\x00garbage", 50);

    list($media) = $this->thumbnail($corrupt, 'corrupt.jpg');

    $this->assertFalse($media);
    $this->assertEquals(0, Media::count());
  }

  public function testFilesAreNotResizedUnlessASizeIsGiven() {
    Media::$image_library = 'gd';
    $bytes = $this->bands(600, 400, 'jpg');
    $this->download->responses['https://cdn.example/full.jpg'] = ['code' => 200, 'body' => $bytes];

    $media = Media::createFromURL('https://cdn.example/full.jpg');

    $this->assertSame($bytes, Storage::disk('local')->get('media/'.$media->filename));
  }
}
