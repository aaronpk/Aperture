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
@endsection
