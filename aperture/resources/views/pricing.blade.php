@extends('layouts/main')

@section('content')
<section class="section">
<div class="container content">

  <h1 class="title">Pricing</h1>

  <p><b>Aperture is free while in beta.</b> It is currently limited to keep only the most recent <b>7 days</b> of data.</p>

  <p>You are encouraged to bookmark and favorite content that you see in a reader so that you can save the content to your own website!</p>

  @if(env('EMAIL_SIGNUP'))
    <p>In the future, we may introduce paid plans that will store data for longer. If you're interested in that, please sign up for the mailing list to be notified when there are any updates.</p>

    <!-- Begin MailChimp Signup Form -->
    <div id="mc_embed_signup">
      <form action="https://nicernet.us12.list-manage.com/subscribe/post?u=3da16cdb35a3696d18f3d5001&amp;id=22aff5733e" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate>
        <div id="mc_embed_signup_scroll">
          <div class="mc-field-group full-width">
            <input type="email" value="" name="EMAIL" class="required email" id="mce-EMAIL" placeholder="enter your email">
          </div>
          <div id="mce-responses" class="clear">
            <div class="response" id="mce-error-response" style="display:none"></div>
            <div class="response" id="mce-success-response" style="display:none"></div>
          </div>
          <div style="position: absolute; left: -5000px;" aria-hidden="true"><input type="text" name="b_3da16cdb35a3696d18f3d5001_22aff5733e" tabindex="-1" value=""></div>
          <div class="clear full-width">
            <input type="submit" value="Keep me updated!" name="subscribe" id="mc-embedded-subscribe" class="button">
          </div>
        </div>
        <input type="hidden" name="SOURCE" value="aperture">
      </form>
    </div>
    <!--End mc_embed_signup-->
  @endif

  <p>If you've enjoyed using Aperture, you can <a href="https://aaronparecki.com/tip/5">buy Aaron a beer</a>, or even better, <a href="https://opencollective.com/indieweb">become a montly supporter of the IndieWeb</a> on Open Collective.</p>

</div>
</section>
@endsection
@section('scripts')
<style>
  /* Mailchimp Form */
  #mc_embed_signup {
    width: 360px;
    text-align: center;
    margin: 2em auto;
  }

  .mc-field-group {
    margin-bottom: 0.5em;
  }

  .mc-field-group label {
    display: block;
  }

  #mc_embed_signup input {
    display: block;
    background: #fff;
    color: black;
    border: 2px #ccc solid;
    border-radius: 4px;
    padding: 1em 2em;
    font-size: 1em;
    margin-left: auto;
    margin-right: auto;
  }

  #mc_embed_signup .button {
    height: auto;
    border: 2px #ccc solid;
    border-radius: 4px;
    cursor: pointer;
    background: #eee;
    color: black;
    text-decoration: none;
    margin-top: 1em;
    margin-left: auto;
    margin-right: auto;
  }
  #mc_embed_signup .button:hover {
    background: #ccc;
  }
</style>
@endsection
