<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Monocle</title>

        <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700" rel="stylesheet" type="text/css">

        <!-- Styles -->
        <style>
            html, body {
                background-color: #fff;
                color: #636b6f;
                font-family: 'Lato', sans-serif;
                font-weight: 100;
                height: 100vh;
                margin: 0;
            }

            .full-height {
                height: 100vh;
            }

            .flex-center {
                align-items: center;
                display: flex;
                justify-content: center;
            }

            .position-ref {
                position: relative;
            }

            .top-right {
                position: absolute;
                right: 10px;
                top: 18px;
            }

            .content {
                text-align: center;
            }

            .title {
                font-size: 84px;
            }

            .links > a {
                color: #636b6f;
                padding: 0 25px;
                font-size: 12px;
                font-weight: 600;
                letter-spacing: .1rem;
                text-decoration: none;
                text-transform: uppercase;
            }

            .m-b-md {
                margin-bottom: 30px;
            }

            .blog {
                max-width: 500px;
                margin: 0 auto;
                padding-bottom: 100px;
            }

            .blog .published {
                font-size: 0.7em;
                padding: 10px 0;
            }

            .blog img {
                margin: 0 auto;
                width: 360px;
                display: block;
            }
        </style>
    </head>
    <body>
        <div class="flex-center position-ref full-height h-x-app">
            @if (Route::has('login'))
                <div class="top-right links">
                    @auth
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}">Login</a>
                    @endauth
                </div>
            @endif

            <div class="content">
                <div class="title m-b-md p-name">
                    Monocle
                </div>
                <a href="" class="u-url"></a>
            </div>
        </div>

        <div class="blog h-entry">
            <h3 class="p-name">Monocle is Offline</h3>
            <div class="e-content text">
                <p>When I first built Monocle, I modeled it after the "timeline" or "stream" view now common in social networks, but made it possible to subscribe to h-entry feeds as well. The main UI showed a stream of posts, with the full post contents rendered inline, along with the author info and favorite/repost/reply buttons.</p>
                
                <img src="https://aaronparecki.com/uploads/Screen-Shot-2016-04-26-07-49-21.png" style="width: 360px;">
                
                <p>While this sounds good in theory, it turns out that I found myself not actually using it regularly, likely for the same reasons I don't often read my Twitter home timeline. Instead, I continued to primarily follow content using my IRC channels.</p>
                <p>Last year, I wrote up details on <a href="https://aaronparecki.com/2015/08/29/8/why-i-live-in-irc">"why I live in IRC"</a>, as a way to capture why that interface is more compelling to me than a stream of posts like Monocle and Twitter.</p>
                <p>Since I wasn't using it as my primary reader, I wasn't regularly giving it attention, and several bugs have appeared that have been left unfixed. So for now, I feel it's best to officially shut down Monocle, rather than have it be a partially functional example of an <a href="http://indiewebcamp.com/reader">IndieWeb reader</a>.</p>
                <p>You can expect to see development of Monocle resume in the future, but it will take on a very different form than what it previously was.</p>
            </div>
            
            <a href="https://aaronparecki.com/" class="p-author h-card">Aaron Parecki</a>
            <div class="published">Originally published on <a href="https://aaronparecki.com/2016/04/26/3/monocle" class="u-repost-of">aaronparecki.com</a> at <time class="dt-published" datetime="2016-04-26T08:00:00-0700">Apr 26, 2016</time></div>
        </div>

    </body>
</html>
