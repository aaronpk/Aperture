<?php
namespace Tests\Support;

use p3k\HTTP;

/**
 * XRay's Fetcher replaces the transport with a real curl transport before
 * fetching, so tests need an HTTP client that keeps the fake one in place.
 */
class PinnedTransportHTTP extends HTTP
{
  private $pinned = false;

  public function set_transport(HTTP\Transport $transport) {
    if(!$this->pinned) {
      parent::set_transport($transport);
      $this->pinned = true;
    }
  }
}
