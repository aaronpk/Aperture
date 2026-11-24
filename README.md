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


Private Networks
----------------

Aperture fetches URLs that come from its users and from the feeds they follow, so it only connects to addresses on the public internet. Requests to localhost, private ranges such as `10.0.0.0/8` and `192.168.0.0/16`, link-local addresses and the like are refused, including when a public URL redirects to one.

If you want to follow feeds on your own network, or your token endpoint is on it, list the hostnames, IP addresses or CIDR ranges to allow in `.env`:

```
HTTP_ALLOW_PRIVATE=192.168.1.0/24,feeds.lan
```

When working on Aperture itself, make requests with `app(\p3k\HTTP::class)` or `App\Services\SafeDownload` so these checks apply. A test fails if code in `app/` builds its own HTTP client or calls curl directly.


Monitoring
----------

Aperture reports statistics in [Munin](https://munin-monitoring.org/)'s format at `/stats`: users, sources, entries, the rate of new entries, the size of entries and media, and feed polling (sources per polling tier, fetches per minute, failing sources, and the polling backlog). The backlog is the one to alert on: it grows if the scheduler or the `feeds` queue workers stop. Set `MUNIN_TOKEN` in `.env` to a secret; requests have to send it, either as `Authorization: Bearer <token>` or as a `token` query parameter.

`munin/aperture_` is a Munin plugin that graphs them. On the machine running munin-node, copy that file to Munin's plugin directory (usually `/usr/share/munin/plugins/`), make it executable, and then:

```
# /etc/munin/plugin-conf.d/aperture
[aperture_*]
env.url https://aperture.example.com
env.token your-MUNIN_TOKEN
```

```
munin-node-configure --shell --families=manual | grep aperture_ | sh
service munin-node restart
```

That creates one `aperture_<name>` link in `/etc/munin/plugins` for each statistic `/stats` lists. You can also create the links by hand, for example `aperture_users`.


Credits
-------

Aperture logo by Gregor Cresnar from the Noun Project.


License
-------

Copyright 2018 by Aaron parecki.

Available under the Apache 2.0 license.
