@extends('hosted/layout')

@section('content')
<div class="container">

@foreach($items as $item)

<div class="box h-entry">
  <article class="media">
    <div class="media-left">
      <figure class="image is-64x64">
        <img src="{{ isset($item['data']['author']['photo']) ? $item['data']['author']['photo'] : 'https://bulma.io/images/placeholders/128x128.png' }}">
      </figure>
    </div>
    <div class="media-content">
      <div class="content">
        <div>
          <strong>{{ isset($item['data']['author']['name']) ? $item['data']['author']['name'] : '' }}</strong> 
          <small>{{ isset($item['data']['author']['url']) ? $item['data']['author']['url'] : $item['entry']->source->url }}</small> 
          <small>
            <a href="{{ $item['data']['url'] }}" class="u-url">
              {!! isset($item['data']['published']) ? 
                '<time class="dt-published" datetime="'.$item['data']['published'].'">'.(new DateTime($item['data']['published']))->format('F j, Y g:ia P').'</time>' 
              : 'permalink' !!}
            </a>
          </small>
        </div>
        @if(isset($item['data']['name']))
          <h3 class="p-name">{{ $item['data']['name'] }}</h3>
        @endif
        @if(isset($item['data']['content']['html']))
          <div class="e-content {{ isset($item['data']['name']) ? '' : 'p-name' }}">{!! $item['data']['content']['html'] !!}</div>
        @endif
      </div>
      <nav class="level is-mobile">
        <div class="level-left">
          <a class="level-item">
            <span class="icon is-small"><i class="fa fa-reply"></i></span>
          </a>
          <a class="level-item">
            <span class="icon is-small"><i class="fa fa-retweet"></i></span>
          </a>
          <a class="level-item">
            <span class="icon is-small"><i class="fa fa-heart"></i></span>
          </a>
        </div>
      </nav>
    </div>
  </article>
</div>

@endforeach

</div>
@endsection
