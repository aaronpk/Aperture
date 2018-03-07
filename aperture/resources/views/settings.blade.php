@extends('layouts/main')

@section('content')
<section class="section">
<div class="container dashboard">

  <h2 class="title">Settings</h2>

  @if(session('settings'))
    <div class="notification is-primary">
      {{ session('settings') }}
    </div>
  @endif

  <form action="{{ route('settings_save') }}" method="post" style="margin: 20px 0;">

    <div class="field">
      <label class="checkbox">
        <input type="checkbox" name="demo_mode_enabled" {{ Auth::user()->demo_mode_enabled ? 'checked="checked"' : '' }}>
        Demo Mode
      </label>
      <p class="help">Enable "Demo Mode" to hide certain channels from the UI and Microsub clients. Choose which channels are hidden in the channel settings.</p>
    </div>

    <div class="control">
      <button class="button is-primary">Save</button>
    </div>

    {{ csrf_field() }}
  </form>

</div>
</section>
@endsection
