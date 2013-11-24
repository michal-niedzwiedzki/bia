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
	 * Connection client
	 * @var /Epsi/BIA/Connection
	 */
	protected $connection;

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
	public function __construct(Connection $connection, Session $session) {
		$this->connection = $connection;
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
	 * @throws \Epsi\BIA\SessionException
	 */
	protected function call($method, $page, array $params = [ ], $requireValidSession = true) {
		// prepare parameters and record request details
		$method = strtoupper($method);
		$url = static::PROTOCOL . static::HOST . $page;
		$cookie = (string)$this->session->getCookie();
		$this->session->attachSession($params);
		$this->session->recordRequest($method, $page, $params);

		// make call and record response
		$html = $this->connection->call($method, $url, $params, $cookie);
		$this->session->recordResponse($html, $cookie);

		// build document from returned html and update session with token
		$document = new Document($html);
		$this->session->updateSession($document, $requireValidSession);

		return $document;
	}

	/**
	 * Log in to AIB Internet Banking step 1
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
		$digits = $document->getAll("//label[starts-with(@for, 'digit')]/strong/text()[starts-with(., 'Digit')]");
		array_walk($digits, function (&$s) { $s = (int)str_replace("Digit ", "", $s); });
		return $digits;
	}

	/**
	 * Log in to AIB Internet Banking step 2
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
	 * @param string 5-digits long PIN number
	 * @param string $phoneNumber or last four digits
	 * @return bool
	 */
	public function logIn($registrationNumber, $pin, $phoneNumber) {
		$digits = $this->logInStep1($registrationNumber);
		return $this->logInStep2($pin[$digits[0] - 1], $pin[$digits[1] - 1], $pin[$digits[2] - 1], $phoneNumber);
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
	 * Return all balances
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
			$account = FormattingHelper::text($account);
			$balance = FormattingHelper::money($balance);
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
	 * @return int
	 * @throws \Epsi\BIA\ClientException
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
	 * Return account statement
	 *
	 * Resulting array contains hashes with the following fields:
	 * - "date" formatted as DD/MM/YY
	 * - "description" transaction description
	 * - "debit" amount deducted from balance
	 * - "credit" amount added to balance
	 * - "balance" amount after operation
	 *
	 * @param string|int $account name or index (i.e. "CURRENT-001" or 0)
	 * @return array
	 */
	public function getStatement($account) {
		$index = is_int($account) ? $account : $this->getAccountIndex($account);
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
				"TD" === strtoupper($td->nodeName) and $tds[] = trim($td->textContent);
			}
			$last = empty($transactions) ? null : count($transactions) - 1;
			$date = FormattingHelper::date($tds[0]);
			$description = FormattingHelper::text($tds[1]);
			$debit = FormattingHelper::money($tds[2], null);
			$credit = FormattingHelper::money($tds[3], null);
			$balance = FormattingHelper::money($tds[4], null);
			if (null === $last or $debit and $credit or $date !== $transactions[$last]["date"]) {
				$transactions[] = [
					"date" => $date,
					"description" => $description,
					"debit" => $debit,
					"credit" => $credit,
					"balance" => $balance,
				];
			} else {
				$transactions[$last]["description"] .= "\n{$description}";
			}
		}
		return $transactions;
	}

	/**
	 * Mobile top up step 1
	 *
	 * @param string $account name or index (i.e. "CURRENT-001" or 0)
	 * @param int $amount to top up
	 * @param string $phoneNumber comprising 10 digits only, including 08x prefix
	 * @param string $network as defined by NETWORK_* consts
	 * @return int
	 */
	public function topUpStep1($account, $amount, $phoneNumber, $network) {
		$params = [
			"isFormButtonClicked" => "true",
		];
		$this->call("POST", "/inet/roi/topuponline.htm", $params);

		$prefix = (string)substr((string)$phoneNumber, 0, 3);
		$number = (string)substr((string)$phoneNumber, 3);
		$index = is_int($account) ? $account : $this->getAccountIndex($account);
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
		$digit = $document->getOne("//div[@class='aibStyle09']/label[@for='digit']/strong/text()");
		return (int)str_replace("Digit ", "", $digit);
	}

	/**
	 * Mobile top up step 2
	 *
	 * @param int $digit
	 * @return bool
	 */
	public function topUpStep2($digit) {
		$params = [
			"_finish" => "true",
			"_finish.x" => 33,
			"_finish.y" => 8,
			"confirmPac.pacDigit" => $digit,
			"iBankFormSubmission" => "true",
		];
		$document = $this->call("POST", "/inet/roi/topuponline.htm", $params);
		return (boolean)$document->getOne("//h3[text() = 'Your top up request has been accepted.']/text()");
	}

	/**
	 * Mobile top up
	 *
	 * @param string $account name or index (i.e. "CURRENT-001" or 0)
	 * @param int $amount to top up
	 * @param string $phoneNumber comprising 10 digits only, including 08x prefix
	 * @param string $network as defined by NETWORK_* consts
	 * @param int $digit
	 * @return bool
	 */
	public function topUp($account, $amount, $phoneNumber, $network, $pin) {
		$digit = $this->topUpStep1($account, $amount, $phoneNumber, $network);
		return $this->topUpStep2($pin[$digit - 1]);
	}

	/**
	 * Move funds between own accounts and return PIN digit
	 *
	 * @param string $fromAccount
	 * @param string $toAccount
	 * @param float $amount
	 * @param string $fromMessage to appear on sender's statement, default none
	 * @param string $toMessage to appear on recipient's statement, default none
	 * @return int
	 */
	public function moveFundsStep1($fromAccount, $toAccount, $amount, $fromMessage = "", $toMessage = "") {
		$params = [
			"isFormButtonClicked" => "true",
		];
		$this->call("POST", "/inet/roi/transfersandpaymentslanding.htm", $params);

		$params = [
			"iBankFormSubmission" => "true",
			"selectedPaymentType" => 1,
		];
		$this->call("POST", "/inet/roi/transfersandpaymentslanding.htm", $params);

		$params = [
			"_target1.x" => 57,
			"_target1.y" => 8,
			"ccAccounts" => "[]",
			"iBankFormSubmission" => "true",
			"selectedFromAccountIndex" => $fromIndex,
			"selectedToAccountIndex" => $toIndex,
			"senderReference" => $fromMessage,
			"receiverReference" => $toMessage,
			"transferAmount.euro" => floor($amount),
			"transferAmount.cent" => $amount - floor($amount),
		];
		$document = $this->call("POST", "/inet/roi/transfersandpaymentslanding.htm", $params);
		$digit = $document->getOne("//div[@class='aibStyle09']/label[@for='digit']/strong/text()");
		return (int)str_replace("Digit ", "", $digit);
	}

}

class ClientException extends Exception { }