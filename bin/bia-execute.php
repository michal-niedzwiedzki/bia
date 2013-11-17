<?php

/// This script is part of BIA suite - API client for AIB Internet Banking
/// For more details check out https://github.com/stronger/bia
///
/// Usage: php bia-execute.php <session-file> <function-name> <params>
/// Where:
///   <session-file> is target to store session details
///   <function-name> is API function name
///   <params> is space separated list of mandatory function parameters
///
/// Executes API function against AIB Internet Banking portal.
/// Available functions and their parameters are as follows:

require __DIR__ . "/../src/Document.php";
require __DIR__ . "/../src/Connection.php";
require __DIR__ . "/../src/RealConnection.php";
require __DIR__ . "/../src/Session.php";
require __DIR__ . "/../src/Client.php";

// Check for session file parameter and API function
if (!isset($argv[1]) or !isset($argv[2]) or $argv[1] === "-h" or $argv[1] === "--help") {
	fprintf(STDOUT, str_replace("///", "", implode("", array_filter(file(__FILE__), function ($ln) { return 0 === strpos($ln, "///"); }))));
	$rc = new ReflectionClass("\Epsi\BIA\Client");
	foreach ($rc->getMethods() as $rm) {
		if ($rm->isPublic() and 0 !== strpos($rm->getName(), "__")) {
			fprintf(STDOUT, "   {$rm->getName()} ");
			foreach ($rm->getParameters() as $rp) {
				fprintf(STDOUT, " <{$rp->getName()}>");
			}
			fprintf(STDOUT, "\n");
		}
	}
	exit(0);
}

$args = $argv;
define("PROGNAME", array_shift($args));
define("SESSION_FILE", array_shift($args));
define("API_FUNCTION", array_shift($args));

// Inspect API function
$rm = new ReflectionMethod("\Epsi\BIA\Client", API_FUNCTION);
if (0 === strpos(API_FUNCTION, "__")) {
	fprintf(STDERR, PROGNAME . ": Magic methods are not valid API functions");
	exit(1);
}
if (!$rm->isPublic()) {
	fprintf(STDERR, PROGNAME . ": Only public methods are valid API functions");
	exit(2);
}
if (count($args) !== ($n = $rm->getNumberOfRequiredParameters())) {
	fprintf(STDERR, PROGNAME . ": This function requires $n parameters");
	exit(3);
}

// Create session and client
$connection = new \Epsi\BIA\RealConnection();
$session = new \Epsi\BIA\Session();
$session->load(SESSION_FILE);
$client = new \Epsi\BIA\Client($connection, $session);

// Execute API function
try {
	$out = call_user_func_array([$client, API_FUNCTION], $args);
	fprintf(STDOUT, json_encode($out, JSON_PRETTY_PRINT) . "\n");
} catch (Exception $e) {
	fprintf(STDERR, PROGNAME . ": {$e->getMessage()}\n");
}

// Save session
$session->save(SESSION_FILE);
