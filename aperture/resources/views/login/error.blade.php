@extends('layouts.main')

@section('content')

<div class="container">
  <br>

  <div class="notification is-danger">
    <strong>{{ $error }}</strong>
    <p>{{ $description }}</p>
  </div>

</div>

@endsection
