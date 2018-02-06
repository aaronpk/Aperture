<?php

namespace App\Http\Middleware;

use Closure, Log, Response, Auth, Cache;
use App\User, App\ChannelToken;
use p3k\HTTP;

class VerifyIndieAuthAccessToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(!preg_match('/microsub\/(\d+)/', $request->path(), $match)) {
            return Response::json(['error'=>'not_found'], 404);
        }

        // Check if this user exists
        $user = User::where('id', $match[1])->first();
        if(!$user) {
            return Response::json(['error'=>'not_found'], 404);
        }

        // Check the given access token at the token endpoint
        $authorization = $request->header('Authorization');

        if(!$authorization) {
            return Response::json(['error'=>'unauthorized'], 401);
        }

        if(!preg_match('/Bearer (.+)/', $authorization, $match)) {
            return Response::json(['error'=>'unauthorized'], 401);
        }
        $token = $match[1];

        // Check the cache
        if($cache_data=Cache::get('token:'.$token)) {
            // Log::info("Token data from cache");
            // Log::info($cache_data);
            $token_data = json_decode($cache_data, true);
        } else {
            // Check the local token database for read tokens
            // Used for other apps to read content from channels without going through IndieAuth
            $channel_token = ChannelToken::where('token', $token)->first();
            if($channel_token) {
                if($channel_token->channel->user_id == $user->id) {
                    $token_data = [
                        'type' => 'channel',
                        'scope' => $channel_token->scopes(),
                        'channel_id' => $channel_token->channel->id
                    ];
                } else {
                    return Response::json(['error'=>'forbidden'], 403);
                }
            } else {
                $http = new HTTP();
                $token_response = $http->get($user->token_endpoint, [
                    'Authorization: Bearer '.$token,
                    'Accept: application/json'
                ]);

                // The token endpoint returns 200 for a valid token
                if($token_response['code'] != 200) {
                    return Response::json(['error'=>'forbidden'], 403);
                }

                // Check that the user in the token matches what we expect
                $token_data = json_decode($token_response['body'], true);

                if(!$token_data) {
                  // Parse as form-encoded for fallback support
                  $token_data = [];
                  parse_str($token_response['body'], $token_data);
                }

                if(!$token_data || !isset($token_data['me'])) {
                    return Response::json(['error'=>'invalid_token_response'], 400);
                }

                if(\IndieAuth\Client::normalizeMeURL($token_data['me']) != \IndieAuth\Client::normalizeMeURL($user->url)) {
                    return Response::json(['error'=>'invalid_user'], 403);
                }

                $token_data['type'] = 'indieauth';

                if(is_string($token_data['scope']))
                    $token_data['scope'] = [$token_data['scope']];
            }

            Cache::set('token:'.$token, json_encode($token_data), 300);
        }

        $request->attributes->set('token_data', $token_data);

        // Activate the login for this user for the request
        Auth::login($user);

        return $next($request);
    }
}
