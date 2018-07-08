@extends('layouts/main')

@section('content')
<section class="section">
<div class="container">


    <div class="notification is-danger content">
      <p><b>error: {{ $error }}</b></p>
      <p>{{ $description }}</p>
    </div>


</div>
</section>
@endsection
