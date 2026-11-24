<?php
namespace Tests\Support;

use App\Services\SafeDownload;

/**
 * SafeDownload with the network taken out: the guard and the redirect handling are
 * the real ones, and each single request is answered from $responses.
 */
class FakeDownload extends SafeDownload
{
  /** @var array url => ['code' => int, 'location' => string|null, 'body' => string] */
  public $responses = [];

  /** @var array every request that got past the guard */
  public $hops = [];

  protected function request($url, array $resolve, $path, $connect_timeout_ms, $timeout_ms) {
    $this->hops[] = ['url' => $url, 'resolve' => $resolve];
    $response = $this->responses[$url] ?? ['code' => 404];
    file_put_contents($path, $response['body'] ?? '');
    return ['code' => $response['code'], 'location' => $response['location'] ?? null, 'error' => ''];
  }
}
