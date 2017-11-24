@extends('layouts/main')

@section('content')
<section class="section">
<div class="container dashboard">

  <div class="buttons is-right">
    <a href="#" id="new-channel" class="button is-primary">New Channel</a>
  </div>

  <h1 class="title">Channels</h1>

  @foreach($channels as $channel)
    <div class="channel">
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

  <hr>

  <div class="helpsection">
    <p>To use Monocle as your Microsub endpoint, add this HTML to your home page.</p>

    <pre><?= htmlspecialchars('<link rel="microsub" href="'.env('APP_URL').'/microsub/'.Auth::user()->id.'">') ?></pre>

    <p>To test API calls, you can generate a token from your token endpoint yourself or by using this <a href="https://gimme-a-token.5eb.nl/">access token tool</a>.</p>

    <p>Monocle has implemented the following actions in the <a href="https://indieweb.org/Microsub-spec">Microsub spec</a>:</p>

    <ul class="methods">
      <li><a href="https://indieweb.org/Microsub-spec#Timelines">GET action=timeline</a> - retrieve the list of items in a channel</li>
      <li><a href="https://indieweb.org/Microsub-spec#Search">POST action=search</a> - search for a new feed to add</li>
      <li><a href="https://indieweb.org/Microsub-spec#Preview">GET action=preview</a> - preview a feed before following it</li>
      <li><a href="https://indieweb.org/Microsub-spec#Following">GET action=follow</a> - retrieve the list of feeds followed in a channel</li>
      <li><a href="https://indieweb.org/Microsub-spec#Following">POST action=follow</a> - follow a new feed in a channel</li>
      <li><a href="https://indieweb.org/Microsub-spec#Unfollowing">POST action=unfollow</a> - unfollow a feed in a channel (existing items from that feed are left in the channel, like IRC/Slack)</li>
      <li><a href="https://indieweb.org/Microsub-spec#Channels_2">GET action=channels</a> - retrieve the list of channels for a user</li>
      <li><a href="https://indieweb.org/Microsub-spec#Channels_2">POST action=channels</a> - create a new channel</li>
    </ul>
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
</style>
@endsection
