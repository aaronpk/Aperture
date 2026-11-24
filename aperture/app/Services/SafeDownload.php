<?php
namespace App\Services;

use p3k\HTTP\Guard;

/**
 * Downloads a URL to a file, for media that is too big to hold in memory.
 *
 * The URLs come from feeds and Micropub posts, so this applies the same rules as the
 * HTTP client's safe mode: only http and https, only hosts whose addresses the guard
 * accepts, every redirect checked before it is followed, and each connection held to
 * the addresses that were checked.
 */
class SafeDownload
{
  const MAX_REDIRECTS = 8;

  private $guard;

  public function __construct(Guard $guard) {
    $this->guard = $guard;
  }

  /**
   * @return array ['code' => int, 'error' => string, 'error_description' => string, 'url' => string]
   *               error is '' when the request completed, whatever its status code
   */
  public function toFile($url, $path, $connect_timeout_ms=4000, $timeout_ms=10000) {
    $redirects = 0;

    while(true) {
      $check = $this->guard->check($url);
      if(isset($check['error'])) {
        // Make sure nothing from an earlier hop is left behind
        file_put_contents($path, '');
        return ['code' => 0, 'error' => $check['error'], 'error_description' => $check['error_description'], 'url' => $url];
      }

      // One entry listing every address, the same way p3k\HTTP pins them
      $resolve = [$check['host'].':'.$check['port'].':'.implode(',', array_map(function($address) {
        return strpos($address, ':') !== false ? '['.$address.']' : $address;
      }, $check['addresses']))];

      $hop = $this->request($url, $resolve, $path, $connect_timeout_ms, $timeout_ms);

      if(!in_array((int)$hop['code'], [301, 302, 303, 307, 308], true) || !$hop['location'])
        return ['code' => (int)$hop['code'], 'error' => $hop['error'], 'error_description' => '', 'url' => $url];

      if(++$redirects > self::MAX_REDIRECTS) {
        file_put_contents($path, '');
        return ['code' => (int)$hop['code'], 'error' => 'too_many_redirects', 'error_description' => 'Stopped after '.self::MAX_REDIRECTS.' redirects', 'url' => $url];
      }

      $url = \Mf2\resolveUrl($url, $hop['location']);
    }
  }

  /**
   * Makes one request, without following redirects, writing the body to $path.
   *
   * @return array ['code' => int, 'location' => string|null, 'error' => string]
   */
  protected function request($url, array $resolve, $path, $connect_timeout_ms, $timeout_ms) {
    $fp = fopen($path, 'w');
    $location = null;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    if(defined('CURLOPT_PROTOCOLS_STR')) {
      curl_setopt($ch, CURLOPT_PROTOCOLS_STR, 'http,https');
    } else {
      curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }
    curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $connect_timeout_ms);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout_ms);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use(&$location) {
      if(preg_match('/^location:\s*(.+?)\s*$/i', $header, $match))
        $location = $match[1];
      return strlen($header);
    });
    curl_exec($ch);
    fclose($fp);

    $error = curl_errno($ch) ? (\p3k\HTTP\Curl::error_string_from_code(curl_errno($ch)) ?: 'error') : '';

    return [
      'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
      'location' => $location,
      'error' => $error,
    ];
  }
}
