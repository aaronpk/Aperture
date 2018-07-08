@extends('layouts/main')

@section('headtags')

    <link rel="authorization_endpoint" href="{{ env('APP_URL') }}/auth">
    <link rel="token_endpoint" href="{{ env('APP_URL') }}/token">
    <link rel="micropub" href="{{ env('APP_URL') }}/micropub">
@endsection

@section('content')
<section class="section">
<div class="container">

<div class="content" style="max-width: 600px; margin: 0 auto; text-align: center;">

  @if(Auth::user() && Auth::user()->id == $user->id)
    <h2>{{ $channel->name }}</h2>

    <p>This is the IndieAuth profile for your <b><a href="{{ route('channel', $channel) }}">{{ $channel->name }}</a></b> channel.</p>
    <p>You can use this URL to sign in to Micropub apps and any content created by that app will be posted to this channel.</p>
  @else
    <p>This is the IndieAuth profile URL for a user's channel. The channel name is only shown if you are logged in.</p>
  @endif

</div>

</div>
</section>
@endsection
