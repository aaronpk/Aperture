<?php

namespace App\Http\Middleware;

use Closure, Log, Request, Response, Auth, Cache;
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
            return Response::json([
              'error' => 'not_found',
              'error_description' => 'This user ID does not exist'
            ], 404);
        }

        // Check the given access token at the token endpoint
        $authorization = $request->header('Authorization');
        if($authorization) {
            if(!preg_match('/Bearer (.+)/', $authorization, $match)) {
                // Check for a plain token and reject with a specific error
                if(preg_match('/^[^ ]+$/', $authorization)) {
                    $error_description = 'The Authorization header did not contain a Bearer token';
                } else {
                    $error_description = 'The Authorization header was invalid';
                }
                return Response::json([
                  'error' => 'unauthorized',
                  'error_description' => $error_description
                ], 401);
            }
            $token = $match[1];
        } else {
            $token = Request::input('access_token');
        }

        if(!$token) {
            return Response::json([
              'error' => 'unauthorized',
              'error_description' => 'There was no access token in the request'
            ], 401);
        }

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
                    return Response::json([
                        'error' => 'forbidden',
                        'error_description' => 'This token was issued to a different user'
                    ], 403);
                }
            } else {
                $http = new HTTP();
                $token_response = $http->get($user->token_endpoint, [
                    'Authorization: Bearer '.$token,
                    'Accept: application/json'
                ]);

                // The token endpoint should return 200 for a valid token
                if($token_response['code'] != 200) {
                    $body = $token_response['body'];
                    $decoded = @json_decode($body);
                    if($decoded) $body = $decoded;

                    return Response::json([
                        'error' => 'forbidden',
                        'error_description' => 'The token endpoint could not verify this access token',
                        'token_endpoint' => [
                            'url' => $user->token_endpoint,
                            'code' => $token_response['code'],
                            'response' => $body,
                        ]
                    ], 403);
                }

                // Check that the user in the token matches what we expect
                $token_data = json_decode($token_response['body'], true);

                if(!$token_data) {
                  // Parse as form-encoded for fallback support
                  $token_data = [];
                  parse_str($token_response['body'], $token_data);
                }

                if(!$token_data) {
                    return Response::json([
                      'error' => 'invalid_token_response',
                      'error_description' => 'The token endpoint did not return valid token data'
                    ], 400);
                }

                if(!isset($token_data['me'])) {
                    return Response::json([
                      'error' => 'invalid_token_response',
                      'error_description' => 'The token endpoint did not return a "me" URL for the token'
                    ], 400);
                }

                if(\IndieAuth\Client::normalizeMeURL($token_data['me']) != \IndieAuth\Client::normalizeMeURL($user->url)) {
                    return Response::json([
                      'error' => 'invalid_user',
                      'error_description' => 'This token was issued to a different user',
                      'debug' => [
                        'expected' => \IndieAuth\Client::normalizeMeURL($user->url),
                        'from_token' => $token_data['me'],
                      ]
                    ], 403);
                }

                $token_data['type'] = 'indieauth';

                if(is_string($token_data['scope']))
                    $token_data['scope'] = explode(" ", $token_data['scope']);
            }

            Cache::set('token:'.$token, json_encode($token_data), 300);
        }

        $request->attributes->set('token_data', $token_data);

        // Activate the login for this user for the request
        Auth::login($user);

        return $next($request);
    }
}
