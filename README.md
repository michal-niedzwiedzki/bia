BIA - API client for AIB Internet Banking
============================================================

BIA is aiming at creating API client to automate AIB Internet Banking operations.
It relies on old-fashioned screen scraping and sending POST requests while
maintaining session through cookies and tokens.

BIA allows creating session with Internet Banking portal without
hard coding sensitive information such as PIN number in you application source code.

Requirements
============

* PHP 5.5
* php-curl
* php-tidy

BIA will eventually work with Composer, however for the time being you will have to
install it manually.

Command Line Tools
==================

BIA comes with handful of command line utilities. Those can be used to initiate
session outside of web application.

bia-init-session
----------------

Usage: ``php bin/bia-init-session.php session-file``

Interactive tool to initialize session with AIB Internet Banking site.
Stores session into `session-file` for further reuse.

bia-execute
-----------

Usage: ``php bin/bia-execute.php session-file api-function params``

Tool to execute API function using existing session file.
Use without args to get full list of API functions and their params.

Best practices
==============

Since hard coding PIN numbers in web application is not the smartest idea,
it is advised to keep login process separate. To achieve that command line
tool `bia-init-session.php` can be used, e.g.

	$ php bin/bia-init-session.php /tmp/session.json
	Enter registration number: 12345678
	Enter 3rd digit of your PIN number: 1
	Enter 1st digit of your PIN number: 2
	Enter 4th digit of your PIN number: 3
	Enter 4 last digits of your phone number: 1234

Then API function `keepAlive` can be used to maintain the session valid.

	$ while true; do php bin/bia-execute.php /tmp/session.json keepAlive; sleep 30; done
	true

This will run indefinitely pinging the server every 30 seconds.
Should session expire, the returned value will be `false`.

Now, since the session is initialized and is being refreshed periodically
web application can tap into it by loading `/tmp/session.json` file:

	$connection = new \Epsi\BIA\RealConnection();
	$session = new \Epsi\BIA\Session();
	$session->load("/tmp/session.json");
	$client = new \Epsi\BIA\Client($connection, $session);
