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
                margin: 0;
            }

            .full-height {
                height: 95vh;
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


            .signup {
              background: #eee;
            }


            .credits {
              padding: 3em;
            }


            /* Mailchimp Form */
            #mc_embed_signup {
              width: 260px;
              text-align: center;
            }

            .mc-field-group {
              margin-bottom: 0.5em;
            }

            .mc-field-group label {
              display: block;
            }

            #mc_embed_signup input {
              display: block;
              background: #fff;
              color: black;
              border: 2px #444 solid;
              border-radius: 4px;
              padding: 1em 2em;
              font-size: 1em;
              margin-left: auto;
              margin-right: auto;
            }

            #mc_embed_signup .button {
              border: 2px #444 solid;
              border-radius: 4px;
              cursor: pointer;
              background: #ddd;
              color: black;
              text-decoration: none;
              margin-top: 1em;
              margin-left: auto;
              margin-right: auto;
            }
            #mc_embed_signup .button:hover {
              background: #ccc;
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
                <div class="title m-b-md">
                    <img src="/icons/aperture.png" alt="Aperture Logo" class="u-logo" width="90" style="margin-bottom: -15px;">
                    <span class="p-name">Aperture</span>
                </div>
                <a href="/" class="u-url"></a>
            </div>
        </div>

        @if(env('EMAIL_SIGNUP'))
        <div class="flex-center position-ref full-height signup">
          <div class="content">

            <!-- Begin MailChimp Signup Form -->
            <div id="mc_embed_signup">
              <form action="https://nicernet.us12.list-manage.com/subscribe/post?u=3da16cdb35a3696d18f3d5001&amp;id=22aff5733e" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate>
                <div id="mc_embed_signup_scroll">
                  <div class="mc-field-group full-width">
                    <input type="email" value="" name="EMAIL" class="required email" id="mce-EMAIL" placeholder="enter your email">
                  </div>
                  <div id="mce-responses" class="clear">
                    <div class="response" id="mce-error-response" style="display:none"></div>
                    <div class="response" id="mce-success-response" style="display:none"></div>
                  </div>
                  <div style="position: absolute; left: -5000px;" aria-hidden="true"><input type="text" name="b_3da16cdb35a3696d18f3d5001_22aff5733e" tabindex="-1" value=""></div>
                  <div class="clear full-width">
                    <input type="submit" value="Keep me updated!" name="subscribe" id="mc-embedded-subscribe" class="button">
                  </div>
                </div>
                <input type="hidden" name="SOURCE" value="monocle">
              </form>
            </div>
            <!--End mc_embed_signup-->

          </div>
        </div>
        @endif


        <div class="flex-center position-ref credits">
          <div>Aperture is created by <a href="https://aaronparecki.com/">Aaron Parecki</a> and is part of the <a href="https://indieweb.org/">IndieWeb</a>.</div>
        </div>


    </body>
</html>
