<?php

require_once __DIR__ . "/../src/Document.php";
require_once __DIR__ . "/../src/Connection.php";
require_once __DIR__ . "/../src/Session.php";
require_once __DIR__ . "/../src/Client.php";
require_once __DIR__ . "/../src/FormattingHelper.php";
require_once "PHPUnit/Autoload.php";

use \Epsi\BIA\Connection;
use \Epsi\BIA\Session;
use \Epsi\BIA\Client;

/**
 * Client test
 *
 * @author Michał Rudnicki <michal.rudnicki@epsi.pl>
 */
final class ClientTest extends PHPUnit_Framework_TestCase {

	const TOKEN = "FAKE_TOKEN";
	const REGNUMBER = "12345678";
	const DIGIT1 = 1;
	const DIGIT2 = 3;
	const DIGIT3 = 5;
	const PHONENUMBER = "0850011222";

	private $session;

	public function setUp() {
		parent::setUp();
		$this->session = new Session();
		$this->session->setToken(self::TOKEN);
	}

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
					"regNumber" => self::REGNUMBER,
					"transactionToken" => self::TOKEN,
				]),
				$this->isType("string")
			)
			->will($this->returnValue(file_get_contents(__DIR__ . "/mocks/login2.htm")));

		$client = new Client($connection, $this->session);
		$digits = $client->logInStep1(self::REGNUMBER);
		$this->assertEquals([self::DIGIT1, self::DIGIT2, self::DIGIT3], $digits);
	}

	/**
	 * @test
	 */
	public function getBalances() {
		$connection = $this->getMock("\Epsi\BIA\Connection");
		$connection->expects($this->once())
			->method("call")
			->with(
				$this->equalTo("POST"),
				$this->equalTo("https://aibinternetbanking.aib.ie/inet/roi/accountoverview.htm"),
				$this->equalTo([
					"isFormButtonClicked" => "true",
					"transactionToken" => self::TOKEN,
				]),
				$this->isType("string")
			)
			->will($this->returnValue(file_get_contents(__DIR__ . "/mocks/accountoverview.htm")));

		$expected = [
			"CURRENT-001" => 1000.00,
			"ONLINE SAVINGS-999" => 2000.00,
		];
		$client = new Client($connection, $this->session);
		$actual = $client->getBalances();
		$this->assertSame($expected, $actual);
	}

	/**
	 * @test
	 */
	public function logInStep2() {
		$connection = $this->getMock("\Epsi\BIA\Connection");
		$connection->expects($this->once())
			->method("call")
			->with(
				$this->equalTo("POST"),
				$this->equalTo("https://aibinternetbanking.aib.ie/inet/roi/login.htm"),
				$this->equalTo([
					"jsEnabled" => "TRUE",
					"pacDetails.pacDigit1" => self::DIGIT1,
					"pacDetails.pacDigit2" => self::DIGIT2,
					"pacDetails.pacDigit3" => self::DIGIT3,
					"challengeDetails.challengeEntered" => substr(self::PHONENUMBER, -4),
					"_finish" => "true",
					"transactionToken" => self::TOKEN,
				]),
				$this->isType("string")
			)
			->will($this->returnValue(file_get_contents(__DIR__ . "/mocks/accountoverview.htm")));

		$client = new Client($connection, $this->session);
		$client->logInStep2(self::DIGIT1, self::DIGIT2, self::DIGIT3, self::PHONENUMBER);
	}

	/**
	 * @test
	 */
	public function topUpStep1() {
		$connection = $this->getMock("\Epsi\BIA\Connection");
		$connection->expects($this->at(0))
			->method("call")
			->with(
				$this->equalTo("POST"),
				$this->equalTo("https://aibinternetbanking.aib.ie/inet/roi/topuponline.htm"),
				$this->equalTo([
					"isFormButtonClicked" => "true",
					"transactionToken" => self::TOKEN,
				]),
				$this->isType("string")
			)
			->will($this->returnValue(file_get_contents(__DIR__ . "/mocks/topuponline1.htm")));
		$connection->expects($this->at(1))
			->method("call")
			->with(
				$this->equalTo("POST"),
				$this->equalTo("https://aibinternetbanking.aib.ie/inet/roi/topuponline.htm"),
				$this->equalTo([
					"accountSelected" => 0,
					"mobileNumber.prefix" => substr(self::PHONENUMBER, 0, 3),
					"mobileNumber.number" => substr(self::PHONENUMBER, 3),
					"confirmMobileNumber.prefix" => substr(self::PHONENUMBER, 0, 3),
					"confirmMobileNumber.number" => substr(self::PHONENUMBER, 3),
					"networkSelected" => 3,
					"topupAmount" => 10,
					"iBankFormSubmission" => "true",
					"_target1" => "true",
					"transactionToken" => self::TOKEN,
				]),
				$this->isType("string")
			)
			->will($this->returnValue(file_get_contents(__DIR__ . "/mocks/topuponline2.htm")));

		$client = new Client($connection, $this->session);
		$digit = $client->topUpStep1(0, 10, self::PHONENUMBER, 3);
		$this->assertEquals(2, $digit);
	}

	/**
	 * @test
	 */
	public function topUpStep2() {
		$connection = $this->getMock("\Epsi\BIA\Connection");
		$connection->expects($this->once())
			->method("call")
			->with(
				$this->equalTo("POST"),
				$this->equalTo("https://aibinternetbanking.aib.ie/inet/roi/topuponline.htm"),
				$this->equalTo([
					"_finish" => "true",
					"_finish.x" => 33,
					"_finish.y" => 8,
					"confirmPac.pacDigit" => 2,
					"iBankFormSubmission" => "true",
					"transactionToken" => self::TOKEN,
				]),
				$this->isType("string")
			)
			->will($this->returnValue(file_get_contents(__DIR__ . "/mocks/topuponline3.htm")));

		$client = new Client($connection, $this->session);
		$ok = $client->topUpStep2(2);
		$this->assertTrue($ok);
	}

}