BIA - API client for AIB Internet Banking
============================================================

BIA is aiming at creating API client to automate AIB Internet Banking operations.
It relies on old-fashioned screen scraping and sending POST requests while
maintaining session through cookies and tokens.

BIA allows creating session with Internet Banking portal without
disclosing or embedding sensitive information such as PIN number
in you application source code.

Currently BIA supports the following operations:
* logging in
* getting list of accounts
* getting account statement

Requirements
============

* PHP 5.5
* php-curl
* php-tidy

BIA will eventually work with Composer, however for the time being you will have to
install it manually.
