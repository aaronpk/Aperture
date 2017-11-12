@extends('layouts/main')

@section('content')
<section class="section">
<div class="container channel">

  <nav class="breadcrumb" aria-label="breadcrumbs">
    <ul>
      <li><a href="{{ route('dashboard') }}">Channels</a></li>
      <li class="is-active"><a href="#" aria-current="page">{{ $channel->name }}</a></li>
    </ul>
  </nav>

  <div class="buttons is-right">
    <a href="#" id="new-apikey" class="button">New API Key</a>
    <a href="#" id="new-source" class="button is-primary">New Source</a>
  </div>

  <h1 class="title">{{ $channel->name }}</h1>

  @foreach($sources as $source)
    <div class="source">
      <div class="buttons is-right">
        <a href="#" class="button is-small unfollow" data-source="{{ $source->id }}">Remove</a>
      </div>

      <h2>{{ $source->name ?: $source->url }}</h2>

      <div class="source-stats">
        <span>{{ $source->format }}</span>
        @if($source->websub)
          <span>websub</span>
        @endif
      </div>

      @if($source->format == 'apikey')
        <div><code>{{ $source->token }}</code></div>
        <p class="help">Use this API key to create entries in this channel with a POST request. See <a href="/docs">the documentation</a> for more details.</p>
      @endif
    </div>
  @endforeach  

</div>
</section>

<div class="modal" id="new-source-modal">
  <div class="modal-background"></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Add a Source</p>
      <button class="delete" aria-label="close"></button>
    </header>
    <section class="modal-card-body">

      <div class="field">
        <div class="control">
          <input class="input" type="url" placeholder="https://example.com/" id="source-url" required="required">
        </div>
        <p class="help">Enter a URL to follow (HTML, JSONFeed, Atom, RSS)</p>
      </div>
      <button class="button is-primary" type="submit" id="new-source-find-feeds-btn">Find Feeds</button>

      <div id="new-source-feeds" class="hidden">
        <p class="info-text"></p>
        <ul></ul>
      </div>

    </section>
    <footer class="modal-card-foot">
    </footer>
  </div>
</div>

<div class="modal" id="new-apikey-modal">
  <div class="modal-background"></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Add an API Key</p>
      <button class="delete" aria-label="close"></button>
    </header>
    <section class="modal-card-body">

      <div class="field">
        <div class="control">
          <label class="label">Name</label>
          <input class="input" type="text" name="name" id="new-apikey-name" required="required">
          <p class="help">For your informational purposes only.</p>
        </div>
      </div>

    </section>
    <footer class="modal-card-foot">
      <button class="button is-primary" type="submit" id="new-apikey-btn">Create</button>
    </footer>
  </div>
</div>

@endsection

@section('scripts')
<script>
var channel_id = {{ $channel->id }};

$(function(){
  $('#new-source').click(function(e){
    $('#new-source-modal').addClass('is-active');
    e.preventDefault();
  });
  $('#new-apikey').click(function(e){
    $('#new-apikey-modal').addClass('is-active');
    e.preventDefault();
  });

  $("#new-source-find-feeds-btn").click(function(){
    $(this).addClass("is-loading");
    $.post("{{ route('find_feeds') }}", {
      _token: csrf_token(),
      url: $("#source-url").val()
    }, function(response){
      $("#new-source-find-feeds-btn").removeClass("is-loading").removeClass("is-primary");
      $("#new-source-feeds ul").empty();
      for(var i in response.feeds) {
        var feed = response.feeds[i];
        $("#new-source-feeds ul").append('<li><h3>'+feed.type+'</h3><div class="url">'+feed.url+'</div><button class="button is-primary" data-url="'+feed.url+'" data-format="'+feed.type+'">Follow</button></li>');
      }
      if(response.feeds.length == 0) {
        $("#new-source-feeds ul").append('<li>No feeds were found at the given URL</li>');
      }
      bind_follow_buttons();
      $("#new-source-feeds").removeClass("hidden");
    })
  });

  $(".source .unfollow").click(function(){
    $(this).addClass("is-loading");
    $.post("/channel/"+channel_id+"/remove_source", {
      _token: csrf_token(),
      source_id: $(this).data("source")
    }, function(response){
      reload_window();
    });
  });

  $("#new-apikey-btn").click(function(){
    $(this).addClass("is-loading");
    $.post("/channel/"+channel_id+"/add_apikey", {
      _token: csrf_token(),
      name: $("#new-apikey-name").val()
    }, function(response){
      reload_window();
    });
  });

});

function bind_follow_buttons() {
  $("#new-source-feeds button").unbind("click").bind("click", function(){
    $(this).addClass("is-loading");
    $.post("/channel/"+channel_id+"/add_source", {
      _token: csrf_token(),
      url: $(this).data("url"),
      format: $(this).data("format")
    }, function(response){
      reload_window();
    });
  });
}
</script>
@endsection
