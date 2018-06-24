Aperture
========

Aperture is a [Microsub](https://indieweb.org/Microsub) server.

It is currently in beta, and although has been pretty stable for the last several months. You can try out the hosted service at [aperture.p3k.io](https://aperture.p3k.io), which keeps data for 7 days, or you can host your own copy to customize it.


Setup
-----

By default, new user accounts are not created automatically. You will need to first create a user before you can log in.

From the command line, run this command, passing your home page URL as the argument:

```
php artisan create:user https://example.com/
```

You will need to have a token endpoint set up already in order for Aperture to know how to verify access tokens it receives in Microsub requests. If there is no token endpoint found at that URL, user account creation will fail.

You can enable a config option to allow anyone to sign in to your instance. Set `PUBLIC_ACCESS=true` in `.env`.


Dependencies
------------

Aperture relies on a few external services to work.

[Watchtower](https://github.com/aaronpk/Watchtower) handles actually subscribing to feeds and delivering content to Aperture. Aperture is only responsible for parsing the content, but contains no feed polling/fetching logic.

Aperture uses an image proxy to rewrite all image URLs as https URLs. It expects to use a service that matches GitHub's camo API. You can use either [camo](https://github.com/atmos/camo) itself, or an API-compatible project such as [go-camo](https://github.com/cactus/go-camo). You'll need to define the base URL `IMG_PROXY_URL` and the signing secret `IMG_PROXY_KEY` in the `.env` file.



Credits
-------

Aperture logo by Gregor Cresnar from the Noun Project.


License
-------

Copyright 2018 by Aaron parecki.

Available under the Apache 2.0 license.
