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

	protected $session;

	/**
	 * Constructor
	 *
	 * @param \Epsi\BIA\Session $session injected from outside
	 */
	public function __construct(Session $session) {
		$this->session = $session;
		$this->ch = curl_init();
		$ch = $this->ch;
		curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->session->getCookieJar());
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->session->getCookieJar());
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; U; Linux x86_64; en-US; rv:1.9.2.13) Gecko/20101206 Ubuntu/10.10 (maverick) Firefox/3.6.13");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
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

		// make http request and record output
		$query = http_build_query($params);
		$url = static::PROTOCOL . static::HOST . $page;
		if ($method === "POST") {
			curl_setopt($this->ch, CURLOPT_POST, 1);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $query);
		} else {
			$query and $url .= "?{$query}";
			curl_setopt($this->ch, CURLOPT_HTTPGET, true);
		}
		curl_setopt($this->ch, CURLOPT_URL, $url);
//		curl_setopt($this->ch, CURLOPT_REFERER, static::PROTOCOL . static::HOST . $page);
		$html = curl_exec($this->ch);
		$errno = curl_errno($this->ch);
		$this->session->recordResponse($html, $errno);
		if ($errno) {
			throw new ClientException("Error requesting HTTP $method $page: " . curl_error($this->ch), $errno);
		}

		// build document from returned html and update session
		$document = new Document($html);
		$this->session->updateSession($document, $requireValidSession); // throws \Epsi\BIA\SessionException

		return $document;
	}

	/**
	 * Log in to AIB Internet Banking and start session
	 *
	 * @param int $registrationNumber
	 * @param string $phoneNumber or last four digits
	 * @param string 5-digits long PIN number
	 * @return bool
	 */
	public function logIn($registrationNumber, $phoneNumber, $pin) {
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

		// make it through pin page
		$indices = $document->getAll("//label[starts-with(@for, 'digit')]/strong/text()[starts-with(., 'Digit')]");
		array_walk($indices, function (&$s) { $s = (int)str_replace("Digit ", "", $s) - 1; });
		$params = [
			"jsEnabled" => "TRUE",
			"pacDetails.pacDigit1" => $pin[$indices[0]],
			"pacDetails.pacDigit2" => $pin[$indices[1]],
			"pacDetails.pacDigit3" => $pin[$indices[2]],
			"challengeDetails.challengeEntered" => substr($phoneNumber, -4),
			"_finish" => "true",
		];
		$this->call("POST", "/inet/roi/login.htm", $params, false);
		$this->session->forgetLastCallDetails();

		// return if session valid
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
		return isset($balances[$account]) ? $balances[$account] : null;
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
		$accounts = array_keys($this->getBalances());
		$index = array_search($account, $accounts);
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

}

class ClientException extends Exception { }