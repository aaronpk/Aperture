Aperture
========

Aperture is a [Microsub](https://indieweb.org/Microsub) server.

It is currently in early alpha, and it will be changing rapidly over the next few months. It is not recommended to use it at this time.


Setup
-----

New user accounts are not created automatically. You will need to first create a user before you can log in the first time.

From the command line, run this command, passing your home page URL as the argument:

```
php artisan create:user https://example.com/
```

You will need to have a token endpoint set up already in order for Aperture to know how to verify access tokens it receives in Microsub requests. If there is no token endpoint found at that URL, user account creation will fail.



Credits
-------

Aperture logo by Gregor Cresnar from the Noun Project.


