<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use App\Models\User, App\Models\Source, App\Models\Channel, App\Models\Entry;
use Tests\Support\FakeTransport, Tests\Support\PinnedTransportHTTP;

abstract class TestCase extends BaseTestCase
{
    /** @var FakeTransport */
    protected $http;

    protected function setUp(): void
    {
        parent::setUp();

        // Every p3k\HTTP resolved from the container will use this transport
        $this->http = new FakeTransport();
        $this->app->bind(\p3k\HTTP::class, function() {
            return new PinnedTransportHTTP(null, $this->http);
        });
    }

    // These helpers deliberately use plain Eloquent instead of model factories
    // so the tests run unchanged across Laravel versions.

    protected function createUser(array $attrs=[]) {
        $user = new User();
        $user->forceFill(array_merge([
            'url' => 'https://user.example/',
            'token_endpoint' => 'https://user.example/token',
        ], $attrs));
        $user->save();
        return $user;
    }

    protected function homeChannel(User $user) {
        return $user->channels()->where('uid', '!=', 'notifications')->first();
    }

    protected function createSource(User $user, array $attrs=[]) {
        $source = new Source();
        $source->forceFill(array_merge([
            'token' => 'src'.bin2hex(random_bytes(8)),
            'url' => 'https://feed.example/'.bin2hex(random_bytes(4)),
            'format' => 'microformats',
            'created_by' => $user->id,
            'is_new' => false,
        ], $attrs));
        $source->save();
        return $source;
    }

    protected function createEntry(Source $source, array $data=[], array $attrs=[]) {
        $data = array_merge([
            'type' => 'entry',
            'url' => 'https://feed.example/post/'.bin2hex(random_bytes(4)),
            'published' => '2018-01-01T12:00:00+00:00',
            'content' => ['text' => 'Hello world', 'html' => '<p>Hello world</p>'],
        ], $data);
        $entry = new Entry();
        $entry->forceFill(array_merge([
            'source_id' => $source->id,
            'unique' => $data['url'],
            'data' => json_encode($data, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES),
            'published' => date('Y-m-d H:i:s', strtotime($data['published'])),
        ], $attrs));
        $entry->save();
        return $entry;
    }

    protected function addToChannel(Channel $channel, Entry $entry, $created_at='2018-01-01 12:00:00', $batch_order=0, $seen=0) {
        $channel->entries()->attach($entry->id, [
            'created_at' => $created_at,
            'batch_order' => $batch_order,
            'seen' => $seen,
        ]);
    }

    // Pre-seed the token cache the way VerifyIndieAuthAccessToken does after verifying a token
    protected function microsubToken(User $user, array $scope=['read', 'follow', 'channels']) {
        $token = 'tok'.bin2hex(random_bytes(8));
        Cache::forever('token:'.$token, json_encode([
            'me' => $user->url,
            'scope' => $scope,
            'type' => 'indieauth',
        ]));
        return $token;
    }

    protected function microsubGet(User $user, $token, array $params) {
        return $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/microsub/'.$user->id.'?'.http_build_query($params));
    }

    protected function microsubPost(User $user, $token, array $params) {
        return $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->post('/microsub/'.$user->id, $params);
    }
}
