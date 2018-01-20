<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $channel->name }}</title>

    <link href="{{ env('APP_URL').'/css/monocle.css' }}" rel="stylesheet">

    @yield('meta')
</head>
<body>

    @yield('content')

    @yield('scripts')

</body>
</html>
