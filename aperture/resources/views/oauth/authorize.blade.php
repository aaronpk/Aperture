@extends('layouts/main')

@section('content')
<section class="section">
<div class="container">

<div style="max-width: 600px; margin: 0 auto;">
  <form class="" method="post" action="{{ url('/auth/process') }}">
    {!! csrf_field() !!}

    <div class="card">

      <div class="card-content">

        <div class="media">
          @if($client['name'])
            @if($client['icon'])
              <div class="media-left">
                <figure class="image is-48x48">
                  <img src="{{ $client['icon'] }}" style="border-radius: 3px;">
                </figure>
              </div>
            @endif
            <div class="media-content">
              <p class="title is-4"><a href="{{ $client_id }}">{{ $client['name'] }}</a></p>
              <p class="subtitle is-6"><a href="{{ $client_id }}">{{ $client_id }}</a></p>
            </div>
          @else
            <a href="{{ $client_id }}">{{ $client_id }}</a>
          @endif
        </div>

        <div class="content">
          @if($create)
            <p>This app would like to be able to create posts in your Aperture account. Choose a channel that you would like this app's posts to be sent to.</p>
            <div class="field">
              <div class="control">
                <div class="select">
                  <select name="channel">
                    @foreach($channels as $channel)
                      <option value="{{ $channel->id }}"{{ $requested_channel == $channel->id ? ' selected="selected"' : '' }}>{{ $channel->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
          @else
            <div class="idtoken">
              Continue signing in to the application at {{ $client_id }}
            </div>
          @endif

          @if($redirect_uri_warning)
            <div class="notification is-warning">
              <b>Warning:</b> The redirect URL this app is using does not match the domain of the client ID. You should verify that the redirect URL below will take you back to the application you expect.
              <code>{{ $redirect_uri }}</code>
            </div>
          @endif

          <input type="hidden" name="client_id" value="{{ $client_id }}">
          <input type="hidden" name="redirect_uri" value="{{ $redirect_uri }}">
          <input type="hidden" name="state" value="{{ $state }}">
          <input type="hidden" name="scope" value="{{ $scope }}">

          <div class="field is-grouped">
            <p class="control">
              <button class="button is-success" type="submit">
                <span class="icon is-small"><i class="fas fa-check"></i></span>
                <span>Continue</span>
              </button>
            </p>
          </div>
        </div>


      </div>
    </div>


  </form>
</div>

</div>
</section>
@endsection

@section('scripts')

@endsection
