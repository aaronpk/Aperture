<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ env('APP_NAME') }}</title>

    @include('components/favicon')

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>

    <section class="hero is-light is-fullheight">

    <div class="hero-body">
        <div class="container has-text-centered">
            <div class="column is-4 is-offset-4">
                <h3 class="title has-text-grey">Sign in to Aperture</h3>
                <div class="box">

                    @if(session('auth_error'))
                      <div class="notification is-danger">
                        <strong>{{ session('auth_error') }}</strong> 
                        <p>{{ session('auth_error_description') }}</p>
                      </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        {{ csrf_field() }}

                        <div class="field">
                            <div class="control">
                                <input id="url" type="url" class="input is-large" name="url" value="{{ session('auth_url') }}" required autofocus placeholder="https://example.com">
                            </div>
                        </div>

                        <button type="submit" class="button is-primary">Log In</button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    </section>

<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
