<nav class="navbar" role="navigation" aria-label="main navigation">
  <div class="navbar-brand">
    <a class="navbar-item" href="/">
      <img src="/icons/aperture-icon-84.png" alt="Aperture Logo" width="28" height="28">
    </a>
    <div class="navbar-burger burger" data-target="navbar">
      <span></span>
      <span></span>
      <span></span>
    </div>
  </div>

  <div id="navbar" class="navbar-menu">
    <div class="navbar-start">
      @auth
        <a href="{{ route('dashboard') }}" class="navbar-item">Dashboard</a>
      @endauth
    </div>
    <div class="navbar-end">
      @auth
        <a href="{{ route('logout') }}" class="navbar-item">Log Out</a>
      @endauth
    </div>
  </div>

</nav>
