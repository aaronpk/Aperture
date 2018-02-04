<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Aperture</title>

        <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700" rel="stylesheet" type="text/css">

        @include('components/favicon')

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
                    Aperture
                </div>
                <img src="/icons/android-chrome-192x192.png" alt="Aperture Logo" class="u-logo" style="display: none;">
                <a href="" class="u-url"></a>
            </div>
        </div>
    </body>
</html>
