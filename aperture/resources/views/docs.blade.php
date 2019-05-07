@extends('layouts/main')

@section('content')
<section class="section">
<div class="container">

  <h1 class="title">Documentation</h1>

  <div class="helpsection content">

    <h2 class="subtitle">Configuration</h2>

    <p>In order to use Aperture, or any Microsub app, you'll need to sign in using <a href="https://indieauth.net">IndieAuth</a>.</p>

    <p>If you're running WordPress, you can install the <a href="https://wordpress.org/plugins/indieauth/">WordPress IndieAuth plugin</a> to quickly add support for IndieAuth to your website. Otherwise, you can <a href="https://indieweb.org/IndieAuth">check if your CMS supports IndieAuth</a>, or connect your site to an existing <a href="https://indieweb.org/IndieAuth#Services">IndieAuth service</a> or <a href="https://indieweb.org/authorization-endpoint">write your own</a>.</p>

    <h3 class="subtitle">Set up using IndieAuth.com</h3>

    <p>If your website doesn't already support IndieAuth, the quickest way to get started is to use the hosted <a href="https://indieauth.com">indieauth.com</a> service.</p>

    <p>Add the two HTML tags below to your home page in the <code>&lt;head&gt;</code> section:</p>

    <pre>&lt;link rel="authorization_endpoint" href="https://indieauth.com/auth"&gt;
&lt;link rel="token_endpoint" href="https://tokens.indieauth.com/token"&gt;</pre>

    <p>Next, add a link from your home page to your GitHub profile or email address (or both) like the below:</p>

    <pre>&lt;a rel="me" href="https://github.com/username"&gt;github&lt;/a&gt;
&lt;a rel="me" href="mailto:you@example.com"&gt;email&lt;/a&gt;</pre>

    <p>Now you'll be able to sign in to Aperture, or any other IndieAuth app!</p>


    <br><br>

    <h2 class="subtitle">API Keys</h2>

    <p>Adding an API key to a channel will let you use that API key to write posts directly into the channel rather than following an external feed. This is useful if you're writing your own custom integrations, such as adding an entry to a channel every time you receive a <a href="https://webmention.net">Webmention</a>.</p>

    <p>Once you have an API key, you can use it like a Micropub access token. Aperture's API accepts <a href="https://micropub.net">Micropub requests</a> to create content in channels.</p>

    <pre>POST {{ env('APP_URL') }}/micropub
Authorization: Bearer (CHANNEL API KEY)
Content-type: application/x-www-form-urlencoded

h=entry&content=Hello+World</pre>

    <p>See <a href="https://indieweb.org/Micropub">indieweb.org/Micropub</a> for more details on how to make a Micropub request.</p>


    <br><br>

    <h2 class="subtitle">Microsub Client Development</h2>

    <p>To test API calls, you can generate a token from your token endpoint yourself or by using this <a href="https://gimme-a-token.5eb.nl/">access token tool</a>.</p>

    <p>Aperture has implemented the following actions in the <a href="https://indieweb.org/Microsub-spec">Microsub spec</a>:</p>

    <ul class="methods">
      <li><a href="https://indieweb.org/Microsub-spec#Timelines">GET action=timeline</a> - retrieve the list of items in a channel</li>
      <li><a href="https://indieweb.org/Microsub-spec#Timelines">POST action=timeline</a> - mark entries as read, or remove an entry from a channel</li>
      <li><a href="https://indieweb.org/Microsub-spec#Search">POST action=search</a> - search for a new feed to add</li>
      <li><a href="https://indieweb.org/Microsub-spec#Preview">GET action=preview</a> - preview a feed before following it</li>
      <li><a href="https://indieweb.org/Microsub-spec#Following">GET action=follow</a> - retrieve the list of feeds followed in a channel</li>
      <li><a href="https://indieweb.org/Microsub-spec#Following">POST action=follow</a> - follow a new feed in a channel</li>
      <li><a href="https://indieweb.org/Microsub-spec#Unfollowing">POST action=unfollow</a> - unfollow a feed in a channel (existing items from that feed are left in the channel, like IRC/Slack)</li>
      <li><a href="https://indieweb.org/Microsub-spec#Channels_2">GET action=channels</a> - retrieve the list of channels for a user</li>
      <li><a href="https://indieweb.org/Microsub-spec#Channels_2">POST action=channels</a> - create, update, and delete channels, or set the order of the channels</li>
    </ul>


  </div>

</div>
</section>
@endsection

@section('scripts')
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
