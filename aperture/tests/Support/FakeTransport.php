<?php
namespace Tests\Support;

use p3k\HTTP\Transport;

/**
 * Stands in for p3k\HTTP's curl transport so tests never touch the network.
 * Register canned responses with on(), then inspect $requests afterwards.
 */
class FakeTransport implements Transport
{
  public $requests = [];
  private $routes = [];

  public function on($method, $urlPrefix, $code, $body='', $headers='Content-Type: text/html') {
    $this->routes[] = compact('method', 'urlPrefix', 'code', 'body', 'headers');
    return $this;
  }

  public function requestsTo($method, $urlPrefix) {
    return array_values(array_filter($this->requests, function($r) use($method, $urlPrefix) {
      return $r['method'] == $method && strpos($r['url'], $urlPrefix) === 0;
    }));
  }

  public function get($url, $headers=[]) {
    return $this->respond('GET', $url, null, $headers);
  }

  public function post($url, $body, $headers=[]) {
    return $this->respond('POST', $url, $body, $headers);
  }

  public function head($url, $headers=[]) {
    $response = $this->respond('HEAD', $url, null, $headers);
    $response['body'] = false;
    return $response;
  }

  public function set_timeout($timeout) {}

  public function set_max_redirects($max_redirects) {}

  private function respond($method, $url, $body, $headers) {
    $this->requests[] = compact('method', 'url', 'body', 'headers');

    foreach($this->routes as $route) {
      if(($route['method'] == $method || ($method == 'HEAD' && $route['method'] == 'GET'))
        && strpos($url, $route['urlPrefix']) === 0) {
        return [
          'code' => $route['code'],
          'header' => "HTTP/1.1 ".$route['code']."\r\n".$route['headers'],
          'body' => $route['body'],
          'error' => '',
          'error_description' => '',
          'url' => $url,
          'debug' => '',
        ];
      }
    }

    return [
      'code' => 404,
      'header' => "HTTP/1.1 404\r\nContent-Type: text/plain",
      'body' => 'not found',
      'error' => '',
      'error_description' => '',
      'url' => $url,
      'debug' => '',
    ];
  }
}
