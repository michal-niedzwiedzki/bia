<?php

require_once __DIR__ . "/../src/Document.php";
require_once __DIR__ . "/../src/Connection.php";
require_once __DIR__ . "/../src/Session.php";
require_once __DIR__ . "/../src/Client.php";
require_once "PHPUnit/Autoload.php";

use \Epsi\BIA\Document;
use \Epsi\BIA\Connection;
use \Epsi\BIA\Session;
use \Epsi\BIA\Client;

/**
 * Document test
 *
 * @author Michał Rudnicki <michal.rudnicki@epsi.pl>
 */
final class ClientTest extends PHPUnit_Framework_TestCase {

	/**
	 * @test
	 */
	public function logInStep1() {
		$connection = $this->getMock("\Epsi\BIA\Connection");
		$connection->expects($this->at(0))
			->method("call")
			->with(
				$this->equalTo("GET"),
				$this->equalTo("https://aibinternetbanking.aib.ie/inet/roi/login.htm"),
				$this->equalTo([ ]),
				$this->anything()
			)
			->will($this->returnValue(file_get_contents(__DIR__ . "/mocks/login1.htm")));
		$connection->expects($this->at(1))
			->method("call")
			->with(
				$this->equalTo("POST"),
				$this->equalTo("https://aibinternetbanking.aib.ie/inet/roi/login.htm"),
				$this->equalTo([
					"_target1" => "true",
					"jsEnabled" => "TRUE",
					"regNumber" => "12345678",
				]),
				$this->isType("string")
			)
			->will($this->returnValue(file_get_contents(__DIR__ . "/mocks/login2.htm")));

		$session = $this->getMock("\Epsi\BIA\Session");
		$session->expects($this->any())
			->method("attachSession")
			->with($this->isType("array"))
			->will($this->returnValue(null));

		$client = new Client($connection, $session);
		$digits = $client->logInStep1("12345678");
		$this->assertEquals([1, 3, 5], $digits);
	}

}