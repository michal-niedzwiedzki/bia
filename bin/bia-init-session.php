<?php

/// This script is part of BIA suite - API client for AIB Internet Banking
/// For more details check out https://github.com/stronger/bia
///
/// Usage: php bia-init-session.php <session-file>
/// Where:
///   <session-file> is target to store session details
///
/// Initiates session with AIB Internet Banking portal.
///
/// Creates session with interactively obtained credentials and stores it in a file.
/// Once created, session file can be picked up by another process and further API calls can be made.
/// For security reasons session file is given read-write access to creator only (0600 octal).
///
/// It is advised to use this script to create session and let web application pick
/// up session file to avoid hardcoding login details directly in web application.
/// To prevent session expiry maintain-session.php script can be used.

require __DIR__ . "/../src/Document.php";
require __DIR__ . "/../src/Connection.php";
require __DIR__ . "/../src/RealConnection.php";
require __DIR__ . "/../src/Session.php";
require __DIR__ . "/../src/Client.php";

// Check for session file parameter
if (!isset($argv[1]) or $argv[1] === "-h" or $argv[1] === "--help") {
	fprintf(STDOUT, str_replace("///", "", implode("", array_filter(file(__FILE__), function ($ln) { return 0 === strpos($ln, "///"); }))));
	exit(0);
}

define("SESSION_FILE", $argv[1]);

// Create session and client
$connection = new \Epsi\BIA\RealConnection();
$session = new \Epsi\BIA\Session();
$client = new \Epsi\BIA\Client($connection, $session);

// Step 1 - Enter registration number
fprintf(STDOUT, "Enter registration number: ");
$registrationNumber = (int)fgets(STDIN);
$indices = $client->logInStep1($registrationNumber);

// Ask for PIN digits and phone number
$nth = [1 => "st", 2 => "nd", 3 => "rd", 4 => "th", 5 => "th"];
$digits = [ ];
for ($i = 1; $i <= 3; ++$i) {
	fprintf(STDOUT, "Enter {$indices[$i - 1]}{$nth[$indices[$i - 1]]} digit of your PIN number: ");
	$digits[$i] = (int)fgets(STDIN);
}
fprintf(STDOUT, "Enter 4 last digits of your phone number: ");
$phoneNumber = (int)fgets(STDIN);

// Step 2 - Enter PIN digits and phone number
$client->logInStep2($digits[1], $digits[2], $digits[3], $phoneNumber);

// Save session
$session->save(SESSION_FILE);
chmod(SESSION_FILE, 0600);
