@extends('layouts/main')

@section('content')
<section class="section">
<div class="container dashboard">

  <div class="notification is-info">
    Thanks for trying out this beta version of Aperture! This instance will keep posts for 7 days. You should feel free to bookmark or favorite things so that they get permanently saved to your own site!
    Feedback is always appreciated! If you have any problems, you can file an issue on GitHub, or get in touch with aaronpk via the <a href="https://indieweb.org/discuss">IndieWeb chat</a>!
  </div>

  <div class="buttons is-right">
    <a href="#" id="new-channel" class="button is-primary">New Channel</a>
  </div>

  <h1 class="title">Channels</h1>

  <div class="channels">
  <?php $numChannels = count($channels); ?>
  @foreach($channels as $i=>$channel)
    <div class="channel" data-uid="{{ $channel->uid }}">
      @if($channel->uid != 'notifications')
        <div class="sort">
          <a href="#" data-dir="up" {!! $i > 1 ? '' : 'class="disabled"' !!}><i class="fas fa-caret-up"></i></a>
          <a href="#" data-dir="down" {!! $i < $numChannels-1 ? '' : 'class="disabled"' !!}><i class="fas fa-caret-down"></i></a>
        </div>
      @endif

      <h2><a href="{{ route('channel', $channel) }}">{{ $channel->name }}</a></h2>

      <!-- sparkline -->

      <div class="channel-stats">
        @if( ($count=$channel->sources()->count()) > 0 )
          <span>{{ $count }} Sources</span>
        @endif
        @if( $channel->last_entry_at )
          <span>Last item {n} minutes ago</span>
        @endif
      </div>
    </div>
  @endforeach
  </div>

  @if(count($archived))
    <div style="margin-top: 2em;">
      <h2 class="subtitle">Archived Channels</h2>
      <ul>
      @foreach($archived as $channel)
        <li><a href="{{ route('channel', $channel) }}">{{ $channel->name }}</a></li>
      @endforeach
      </ul>
    </div>
  @endif

  <hr>

  <div class="helpsection">
    <h3 class="subtitle">Get Started</h3>

    <p>To use Aperture as your Microsub endpoint, add this HTML to your home page.</p>

    <pre><?= htmlspecialchars('<link rel="microsub" href="'.env('APP_URL').'/microsub/'.Auth::user()->id.'">') ?></pre>

    <p>Then choose a <a href="https://indieweb.org/Microsub#Clients">reader</a> and log in, and the reader will find your subscriptions and data in Aperture.</p>
  </div>

</div>
</section>

<div class="modal" id="new-channel-modal">
  <form action="{{ route('create_channel') }}" method="POST">
    {{ csrf_field() }}
    <div class="modal-background"></div>
    <div class="modal-card">
      <header class="modal-card-head">
        <p class="modal-card-title">Create a Channel</p>
        <button class="delete" aria-label="close"></button>
      </header>
      <section class="modal-card-body">

        <input class="input" type="text" placeholder="Name" name="name" required="required">

      </section>
      <footer class="modal-card-foot">
        <button class="button is-primary" type="submit">Create</button>
      </footer>
    </div>
  </form>
</div>
@endsection

@section('scripts')
<script>
$(function(){
  $('#new-channel').click(function(e){
    $('#new-channel-modal').addClass('is-active');
    e.preventDefault();
  });

  $('.channel .sort a').click(function(e){
    e.preventDefault();
    if($(this).hasClass("disabled")) { return; }

    var newOrder;

    if($(this).data("dir") == "up") {
      var thisChannel = $($(this).parents(".channel")[0]).data("uid");
      var prevChannel = $(".channel[data-uid="+thisChannel+"]").prev().data("uid");
      newOrder = [thisChannel, prevChannel];
    } else {
      var thisChannel = $($(this).parents(".channel")[0]).data("uid");
      var nextChannel = $(".channel[data-uid="+thisChannel+"]").next().data("uid");
      newOrder = [nextChannel, thisChannel];
    }

    $.post("/channel/set_order", {
      channels: newOrder,
      _token: csrf_token()
    }, function(){
      window.location.reload();
    })
  });
});
</script>
<style>
.helpsection p {
  margin: 1em 0;
}
.helpsection ul.methods {
  list-style-type: disc;
  margin-left: 1em;
}
.channels .sort {
  float: right;
}
.channels .sort a {
  font-size: 1.1em;
}
.channels .sort a.disabled {
  cursor: auto;
  color: #ccc;
}
</style>
@endsection
