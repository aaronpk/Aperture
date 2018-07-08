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
    @yield('headtags')

    <script defer src="/font-awesome/js/fontawesome-all.js"></script>

    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>

    @include('components/header-bar')

    @yield('content')

    @include('components/footer')

    <script src="{{ asset('js/app.js') }}"></script>
    @yield('scripts')

</body>
</html>
