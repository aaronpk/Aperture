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
    <a href="#" id="channel-settings" class="button">Settings</a>
    <a href="#" id="new-apikey" class="button">New API Key</a>
    <a href="#" id="new-source" class="button is-primary">New Source</a>
  </div>

  <h1 class="title">{{ $channel->name }}</h1>

  @foreach($sources as $source)
    <div class="source">
      <div class="buttons is-right">
        <a href="#" class="button is-small remove" data-source="{{ $source->id }}">Remove</a>
      </div>

      <h2>{{ $source->name ?: $source->url }}</h2>

      <div class="source-stats">
        <span>{{ $source->format }}</span>
        <span>{{ $source->entries_count }} entries</span>
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

<div class="modal" id="remove-source-modal">
  <div class="modal-background"></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Remove a Source</p>
      <button class="delete" aria-label="close"></button>
    </header>
    <section class="modal-card-body">

      <div>
        <p class="info-text">Choose whether you like to remove the source and keep existing entries (no new entries from this source will be added), or remove the source and delete everything.</p>
      </div>

    </section>
    <footer class="modal-card-foot">
      <a href="#" class="remove-future button is-primary">Remove and Keep Old Entries</a>
      <a href="#" class="remove-all button is-danger">Delete Everything</a>
    </footer>
  </div>
</div>

<div class="modal" id="channel-settings-modal">
  <div class="modal-background"></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Channel Settings</p>
      <button class="delete" aria-label="close"></button>
    </header>
    <section class="modal-card-body">

      <div id="channel-settings-section">
        <div class="field">
          <div class="control">
            <label class="label">Name</label>
            <input class="input" type="text" name="name" id="channel-name" required="required" value="{{ $channel->name }}">
          </div>
        </div>
      </div>

      <div id="delete-channel-confirm" class="hidden">
        <p class="info-text">Are you sure you want to delete this channel? All data in this channel will be deleted, and all subscriptions in this channel will be removed.</p>
      </div>

    </section>
    <footer class="modal-card-foot">
      <a href="#" class="button save is-primary">Save</a>
      @if(!in_array($channel->uid, ['default','notifications']))
        <div style="float:right;"><a href="#" class="button is-danger delete-prompt">Delete</a></div>
      @endif
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
  $('#channel-settings').click(function(e){
    $('#channel-settings-modal').addClass('is-active');
    e.preventDefault();
  });

  $("#channel-settings-modal .save").click(function(){
    $(this).addClass("is-loading");
    $.post("{{ route('save_channel', $channel) }}", {
      _token: csrf_token(),
      name: $("#channel-name").val(),
      domain: $("#channel-domain").val()
    }, function(response) {
      reload_window();
    });
  });

  $("#channel-settings-modal .delete-prompt").click(function(){
    if($(this).hasClass("delete-confirm")) {
      console.log("Deleting channel");
      $(this).addClass("is-loading");
      $.post("{{ route('delete_channel', $channel) }}", {
        _token: csrf_token()
      }, function(response) {
        window.location = "/dashboard";
      });
    } else {
      $("#channel-settings-section").addClass("hidden");
      $("#channel-settings-modal .save").addClass("hidden");
      $("#delete-channel-confirm").removeClass("hidden");
      $("#channel-settings-modal .delete-prompt")
        .removeClass("delete-prompt")
        .addClass("delete-confirm").text("Confirm Delete");
    }
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

  $(".source .remove").click(function(e){
    $("#remove-source-modal .remove-future").data("source", $(this).data("source"));
    $("#remove-source-modal .remove-all").data("source", $(this).data("source"));
    $("#remove-source-modal").addClass("is-active");
    e.preventDefault();
  });

  $("#remove-source-modal .remove-all").click(function(){
    $(this).addClass("is-loading");
    $.post("{{ route('remove_source', $channel) }}", {
      _token: csrf_token(),
      source_id: $(this).data("source"),
      remove_entries: 1
    }, function(response){
      reload_window();
    });
  });

  $("#remove-source-modal .remove-future").click(function(){
    $(this).addClass("is-loading");
    $.post("{{ route('remove_source', $channel) }}", {
      _token: csrf_token(),
      source_id: $(this).data("source"),
      remove_entries: 0
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
