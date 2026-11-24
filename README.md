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


Feed Polling
------------

Aperture fetches the feeds that channels follow. Two things need to be running for that to work.

Run Laravel's scheduler from cron every minute. It queues a fetch of each feed that is due:

```
* * * * * cd /path/to/aperture && php artisan schedule:run >> /dev/null 2>&1
```

Run at least one queue worker for the `feeds` queue, which is where the fetch jobs go:

```
php artisan queue:work --queue=feeds
```

Run the workers and the cron job as the same user as PHP-FPM, since they share the `storage` folder and a temporary folder for downloaded images. If you change the queue connection's `retry_after`, keep it above 75 seconds, which is how long a fetch job is allowed to run.

How often a feed is checked depends on how often it has new entries, from every 5 minutes for feeds with 10 or more new entries a day, down to once a day for feeds that haven't had anything new in over a year. The tiers are defined in `app/Services/PollingSchedule.php`. `POLL_MAX_PER_RUN` (default 120) and `POLL_MAX_PER_HOST` (default 10) in `.env` limit how many fetches are queued each minute, in total and for any one hostname.


Dependencies
------------

Aperture relies on an external service to work.

Aperture uses an image proxy to rewrite all image URLs as https URLs. It expects to use a service that matches GitHub's camo API. You can use either [camo](https://github.com/atmos/camo) itself, or an API-compatible project such as [go-camo](https://github.com/cactus/go-camo). You'll need to define the base URL `IMG_PROXY_URL` and the signing secret `IMG_PROXY_KEY` in the `.env` file.



Credits
-------

Aperture logo by Gregor Cresnar from the Noun Project.


License
-------

Copyright 2018 by Aaron parecki.

Available under the Apache 2.0 license.
