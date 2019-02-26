<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Aperture</title>

    <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700" rel="stylesheet" type="text/css">

    <link rel="authorization_endpoint" href="{{ env('APP_URL') }}/auth">
    <link rel="token_endpoint" href="{{ env('APP_URL') }}/token">
    <link rel="micropub" href="{{ env('APP_URL') }}/micropub">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('components/favicon')

    <script defer src="/font-awesome/js/fontawesome-all.js"></script>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    <!-- Styles -->
    <style>

    img.screenshot {
      -webkit-box-shadow: 0px 0px 15px 0px rgba(0,0,0,0.6);
      -moz-box-shadow: 0px 0px 15px 0px rgba(0,0,0,0.6);
      box-shadow: 0px 0px 15px 0px rgba(0,0,0,0.6);
    }

    .tagline {
      text-align: center;
      font-size: 2em;
      padding: 2em 0;
    }

    .tile .icon {
      float: left;
      margin-right: 40px;
      margin-bottom: 20px;
      margin-left: 10px;
      margin-top: 20px;
    }

    .hero-body.centered {
      text-align: center;
    }

    </style>
</head>
<body>
  @include('components/header-bar')

  <div class="container">

    <section class="hero">
      <div class="hero-body centered">
        <div class="h-x-app h-app">
          <img src="/icons/aperture.png" alt="Aperture Logo" class="u-logo" width="90" style="margin-bottom: 15px;">
          <h1 class="title p-name">Aperture</h1>
          <a href="/" class="u-url"></a>
        </div>
      </div>
    </section>

    <div class="flex-center position-ref full-height">
      <div class="content">

        <div class="description">

          <div class="tagline">Aperture is the foundation for your <a href="https://aaronparecki.com/2018/04/20/46/indieweb-reader-my-new-home-on-the-internet">new home on the Internet</a>.</div>

          <div class="tile is-ancestor">
            <div class="tile is-parent">
              <article class="tile is-child box">
                <span class="icon">
                  <i class="fas fa-3x fa-rss-square"></i>
                </span>
                <p>You tell Aperture which feeds you want to follow, and it works behind the scenes collecting new posts. Aperture can follow Microformats, JSON Feed, Atom and RSS feeds.</p>
              </article>
            </div>
            <div class="tile is-parent">
              <article class="tile is-child box">
                <span class="icon">
                  <i class="fas fa-3x fa-cogs"></i>
                </span>
                <p>Aperture doesn't have its own interface for actually reading the posts it collects. Instead, it makes the data available via <a href="https://indieweb.org/Microsub">an API</a>, and you can use <a href="https://indieweb.org/Microsub#Clients">a variety of apps</a> to read your feeds!</p>
              </article>
            </div>
            <div class="tile is-parent">
              <article class="tile is-child box">
                <span class="icon">
                  <i class="fas fa-3x fa-sliders-h"></i>
                </span>
                <p>Aperture provides a way to manage subscriptions and show some debugging info, but normally you won't interact with it after you've set it up, you'll use a reader.</p>
              </article>
            </div>
          </div>

          <div style="margin: 40px 0;">
            <div style="max-width: 600px; margin: 0 auto;">
              <img src="/img/aperture-channels.png" class="screenshot" style="max-width: 90%; margin: 0 auto; display: block;">
            </div>
          </div>

          <h3 class="subtitle">Aperture makes it easy to get started using Together, Monocle, and more!</h3>

          <div class="tile is-ancestor">
            <div class="tile is-4 is-vertical is-parent">
              <div class="tile is-child box">
                <a href="https://indieweb.org/Indigenous"><img src="https://indieweb.org/images/2/2a/indigenous-0.3-reader.png" style="max-height: 420px; display: block; margin: 0 auto;"></a>
                <div style="text-align: center;"><a href="https://indieweb.org/Indigenous">Indigenous for iPhone</a></div>
              </div>
              <div class="tile is-child box">
                <a href="https://indieweb.org/Indigenous_for_Android"><img src="https://realize.be/sites/default/files/2018-04/Screenshot_20180411-112042.png" style="max-width: 80%; max-height: 420px; display: block; margin: 0 auto; border: 1px #eee solid;"></a>
                <div style="text-align: center;"><a href="https://indieweb.org/Indigenous_for_Android">Indigenous for Android</a></div>
              </div>
            </div>
            <div class="tile is-8 is-vertical is-parent">
              <div class="tile is-child box">
                <a href="https://indieweb.org/Monocle"><img src="https://aaronparecki.com/2018/04/20/46/indieweb-reader-interface.png" style="width: 100%;"></a>
                <div style="text-align: center;"><a href="https://indieweb.org/Monocle">Monocle</a></div>
              </div>
              <div class="tile is-child box">
                <a href="https://indieweb.org/Together"><img src="/img/together-reader.png" style="width: 100%;"></a>
                <div style="text-align: center;"><a href="https://indieweb.org/Together">Together</a></div>
              </div>
            </div>
          </div>


          <div style="text-align: center; margin-top: 4em;">
            <a class="button is-primary is-large" href="/login">Get Started</a>
          </div>

        </div>

      </div>
    </div>

  </div>

  <section class="section api-docs">
    <div class="container content">
      <h2 class="title">Technical Details</h2>

      <p>Aperture is a <a href="https://indieweb.org/Microsub">Microsub server</a>. Microsub is a spec that provides a standardized way for reader apps to interact with feeds. By splitting feed parsing and displaying posts into separate parts, a reader app can focus on presenting posts to the user instead of also having to parse feeds. A Microsub <i>server</i> manages the list of people you're following and collects their posts, and a Microsub <i>app</i> shows the posts to the user by fetching them from the server.</p>

      <p>Aperture is just one option for getting started in this ecosystem. You can find a list of other <a href="https://indieweb.org/Microsub#Servers">Microsub servers on indieweb.org</a>, many of which are able to be self-hosted.</p>

      <p>If you're so inclined, you can even <a href="https://indieweb.org/Microsub-spec">write your own</a>! As long as your server supports the <a href="https://indieweb.org/Microsub-spec">Microsub spec</a>, you should be able to use any existing Microsub app with it!</p>

      <p>Read the <a href="/docs">documentation</a> for more details on using Microsub and Aperture.</p>

    </div>
  </section>

  @include('components/footer')

  <script src="{{ asset('js/app.js') }}"></script>

</body>
</html>
