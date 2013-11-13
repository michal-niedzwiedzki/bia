<?php

namespace Epsi\BIA;

use \Exception;

/**
 * AIB API client
 *
 * Screen scraping API client for AIB Internet Banking.
 * Depends on session injected from outside to allow initialization with credentials
 * without disclosign PIN and phone number in application code. See \Epsi\BIA\Session for more.
 *
 * @author Michał Rudnicki <michal.rudnicki@epsi.pl>
 */
class Client {

	const PROTOCOL = "https://";
	const HOST = "aibinternetbanking.aib.ie";

	const NETWORK_VODAFONE = 1;
	const NETWORK_O2 = 2;
	const NETWORK_METEOR = 3;
	const NETWORK_THREE = 4;
	const NETWORK_EMOBILE = 5;
	const NETWORK_TESCO = 6;

	/**
	 * Session container
	 * @var /Epsi/BIA/Session
	 */
	protected $session;

	/**
	 * Cached list of accounts mapped to their index
	 * @var <string>int[]
	 */
	protected $accounts = [ ];

	/**
	 * Constructor
	 *
	 * @param \Epsi\BIA\Session $session injected from outside
	 */
	public function __construct(Session $session) {
		$this->session = $session;
	}

	/**
	 * Make a HTTP call to AIB Internet Banking and return resulting document
	 *
	 * @param string $method can be "GET" or "POST"
	 * @param string $page document relative to server root
	 * @param array $params array of key-value post parameters
	 * @param bool $requireValidSession
	 * @return \Epsi\BIA\Document
	 * @throws \Epsi\BIA\ClientException
	 * @throws \Epsi\BIA\SessionException
	 */
	protected function call($method, $page, array $params = [ ], $requireValidSession = true) {
		// prepare parameters and record request details
		$method = strtoupper($method);
		$this->session->attachSession($params);
		$this->session->recordRequest($method, $page, $params);

		// configure request options
		$ch = curl_init();
		$url = static::PROTOCOL . static::HOST . $page;
		$query = http_build_query($params);
		$cookie = $this->session->getCookie();
		if ($method === "POST") {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
		} else {
			$query and $url .= "?{$query}";
			curl_setopt($ch, CURLOPT_HTTPGET, true);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
//		curl_setopt($ch, CURLOPT_REFERER, static::PROTOCOL . static::HOST . $page);
		$cookie or curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; U; Linux x86_64; en-US; rv:1.9.2.13) Gecko/20101206 Ubuntu/10.10 (maverick) Firefox/3.6.13");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		// configure cookies
		$cookieJar = tempnam(sys_get_temp_dir(), "bia-");
		file_put_contents($cookieJar, $cookie);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);

		// make http request and record output
		$html = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		curl_close($ch);

		$cookie = file_get_contents($cookieJar);
		unlink($cookieJar);

		$this->session->recordResponse($html, $errno);
		if ($errno) {
			throw new ClientException("Error requesting HTTP $method $page: $error", $errno);
		}

		// build document from returned html and update session with token and cookie
		$document = new Document($html);
		$this->session->updateSession($document, $cookie, $requireValidSession); // throws \Epsi\BIA\SessionException

		return $document;
	}

	/**
	 * Start AIB Internet Banking session with registration number and return pin number indices
	 *
	 * Returned pin number indices are between 1 and 5, as on AIB Internet Banking web page.
	 *
	 * @param int $registrationNumber
	 * @return int[]
	 */
	public function logInStep1($registrationNumber) {
		// clear session and make the initial call
		$this->session->clear();
		$document = $this->call("GET", "/inet/roi/login.htm", [ ], false);

		// enter registration code
		$params = [
			"_target1" => "true",
			"jsEnabled" => "TRUE",
			"regNumber" => $registrationNumber,
		];
		$document = $this->call("POST", "/inet/roi/login.htm", $params, false);
		$this->session->forgetLastCallDetails();

		// extract pin number indices
		$indices = $document->getAll("//label[starts-with(@for, 'digit')]/strong/text()[starts-with(., 'Digit')]");
		array_walk($indices, function (&$s) { $s = (int)str_replace("Digit ", "", $s); });
		return [$indices[0], $indices[1], $indices[2]];
	}

	/**
	 * Complete AIB Internet Banking login with pin digits and phone number
	 *
	 * Pin number indices must match output returned by \Epsi\BIA\Client::logInStep1()
	 *
	 * @param int $digit1
	 * @param int $digit2
	 * @param int $digit3
	 * @param string $phoneNumber or last four digits
	 * @return bool
	 */
	public function logInStep2($digit1, $digit2, $digit3, $phoneNumber) {
		$params = [
			"jsEnabled" => "TRUE",
			"pacDetails.pacDigit1" => $digit1,
			"pacDetails.pacDigit2" => $digit2,
			"pacDetails.pacDigit3" => $digit3,
			"challengeDetails.challengeEntered" => substr($phoneNumber, -4),
			"_finish" => "true",
		];
		$this->call("POST", "/inet/roi/login.htm", $params, false);
		$this->session->forgetLastCallDetails();

		// return if session valid
		return $this->session->isValid();
	}

	/**
	 * Log in to AIB Internet Banking
	 *
	 * Warning: IT IS NOT ADVISED TO USE THIS METHOD FOR LOGGING IN.
	 * With this method you are exposing your account details to risk of theft in case of break-in.
	 *
	 * @param int $registrationNumber
	 * @param string $phoneNumber or last four digits
	 * @param string 5-digits long PIN number
	 * @return bool
	 */
	public function logIn($registrationNumber, $phoneNumber, $pin) {
		$incides = $this->logInStep1($registrationNumber);
		return $this->logInStep2($pin[$indices[0] - 1], $pin[$indices[1] - 1], $pin[$indices[2] - 1], $phoneNumber);
	}

	/**
	 * Refreshes session and returns whether alive
	 *
	 * @return bool
	 */
	public function keepAlive() {
		$params = [
			"isFormButtonClicked" => "true",
		];
		$document = $this->call("POST", "/inet/roi/accountoverview.htm", $params);
		return $this->session->isValid();
	}

	/**
	 * Return all accounts balances
	 *
	 * @return array
	 */
	public function getBalances() {
		// fetch balances
		$params = [
			"isFormButtonClicked" => "true",
		];
		$document = $this->call("POST", "/inet/roi/accountoverview.htm", $params);
		$b = $document->getAllPairs(
			"//div[@class='acountOverviewLink']/button/span/text()",
			"//div[@class='aibBoxStyle04']//h3/text()"
		);

		// clean up strings and floats
		$balances = [ ];
		foreach ($b as $account => $balance) {
			$account = strtr($account, ["\r" => "", "\n" => "", "\t" => ""]);
			$balance = (float)strtr($balance, ["," => "", " " => "", "\r" => "", "\n" => "", "\t" => ""]);
			$balances[$account] = $balance;
		}
		$this->accounts = array_keys($balances);

		return $balances;
	}

	/**
	 * Return account balance
	 *
	 * @param string $account name (i.e. "CURRENT-001")
	 * @return float|null
	 */
	public function getBalance($account) {
		$balances = $this->getBalances();
		$this->getAccountIndex($account);
		return $balances[$account];
	}

	/**
	 * Return index for given account name
	 *
	 * @param string $account name (i.e. "CURRENT-001")
	 * @return int|null
	 */
	public function getAccountIndex($account) {
		empty($this->accounts) and $this->getBalances();
		$index = array_search($account, $this->accounts);
		if (false === $index) {
			throw new ClientException("Account not found ($account)");
		}
		return $index;
	}

	/**
	 * Return statement for account
	 *
	 * Resulting array contains hashes with the following fields:
	 * - "date" formatted as DD/MM/YY
	 * - "description" transaction description
	 * - "debit" amount deducted from balance
	 * - "credit" amount added to balance
	 * - "balance" amount after operation
	 *
	 * @param string $account name (i.e. "CURRENT-001")
	 * @return array
	 */
	public function getStatement($account) {
		$index = $this->getAccountIndex($account);
		$params = [
			"index" => $index,
			"viewAllRecentTransactions" => "recent transactions",
		];
		$document = $this->call("POST", "/inet/roi/statement.htm", $params);
		$list = $document->xpath("//table[@class='aibtableStyle01']//tr[@class='jext01']");
		$transactions = [ ];
		foreach ($list as $node) {
			$tds = [ ];
			foreach ($node->childNodes as $td) {
				"td" === $td->nodeName and $tds[] = trim($td->textContent);
			}
			$transactions[] = [
				"date" => $tds[0],
				"description" => $tds[1],
				"debit" => $tds[2],
				"credit" => $tds[3],
				"balance" => $tds[4],
			];
		}
		return $transactions;
	}

	/**
	 * Prepare for mobile top up
	 *
	 * @param string $account name (i.e. "CURRENT-001")
	 * @param int $amount to top up
	 * @param string $phoneNumber comprising 10 digits only, including 08x prefix
	 * @param string $network as defined by NETWORK_* consts
	 * @return int
	 */
	public function topUpStep1($account, $amount, $phoneNumber, $network) {
		$prefix = substr($phoneNumber, 0, 3);
		$number = substr($phoneNumber, 3);
		$index = $this->getAccountIndex($account);
		$params = [
			"accountSelected" => $index,
			"mobileNumber.prefix" => $prefix,
			"mobileNumber.number" => $number,
			"confirmMobileNumber.prefix" => $prefix,
			"confirmMobileNumber.number" => $number,
			"networkSelected" => $network,
			"topupAmount" => $amount,
			"iBankFormSubmission" => "true",
			"_target1" => "true",
		];
		$document = $this->call("POST", "/inet/roi/topuponline.htm", $params);
		return $document->getOne("//div[@class='aibStyle09']/label[@for='digit']/strong/text()");
	}

	/**
	 * Complete mobile top up
	 *
	 * @param int $digit
	 * @return bool
	 */
	public function topUpStep2($digit) {
		// TODO
	}

}

class ClientException extends Exception { }