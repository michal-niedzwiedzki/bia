<?php

namespace Epsi\BIA;

use \Exception;

/**
 * Session container for client calls
 *
 * Maintains session details between subsequent API calls.
 * Updates and validates session details.
 *
 * @author Michał Rudnicki <michal.rudnicki@epsi.pl>
 */
class Session {

	protected $cookie = null;
	protected $token = null;

	protected $isValid = false;

	protected $lastMethod = null;
	protected $lastPage = null;
	protected $lastParams = [ ];

	protected $lastErrno = null;
	protected $lastResponse = null;

	/**
	 * Constructor
	 */
	public function __construct() { }

	/**
	 * Indicate if the session was valid at the last call
	 *
	 * @return bool
	 */
	public function isValid() {
		return $this->isValid;
	}

	/**
	 * Return cookie
	 *
	 * @return string
	 */
	public function getCookie() {
		return $this->cookie;
	}

	/**
	 * Return token
	 *
	 * @return string
	 */
	public function getToken() {
		return $this->token;
	}

	/**
	 * Invalidate the session
	 *
	 * @return \Epsi\BIA\Session
	 */
	public function clear() {
		$this->isValid = false;
		$this->cookie = null;
		$this->token = null;
		return $this;
	}

	/**
	 * Store request details
	 *
	 * @param string $method
	 * @param string $page
	 * @param array $params
	 * @return \Epsi\BIA\Session
	 */
	public function recordRequest($method, $page, array $params) {
		$this->lastMethod = $method;
		$this->lastPage = $page;
		$this->lastParams = $params;
		return $this;
	}

	/**
	 * Store response details
	 *
	 * @param string $html
	 * @param int $errno
	 * @return \Epsi\BIA\Session
	 */
	public function recordResponse($html, $errno) {
		$this->lastResponse = $html;
		$this->lastErrno = $errno;
		return $this;
	}

	/**
	 * Erase recorded call details
	 *
	 * @return \Epsi\BIA\Session
	 */
	public function forgetLastCallDetails() {
		$this->lastMethod = null;
		$this->lastPage = null;
		$this->lastParams = [ ];
		$this->lastErrno = null;
		$this->lastResponse = null;
		return $this;
	}

	/**
	 * Attaches session details to parameters array
	 *
	 * @param &array $params
	 * @return \Epsi\BIA\Session
	 */
	public function attachSession(array &$params) {
		$this->token and $params["transactionToken"] = $this->token;
		return $this;
	}

	/**
	 * Update session details extracted from document
	 *
	 * @param \Epsi\BIA\Document $document
	 * @param string $cookie
	 * @param bool $requireValidSession and throw exception if not valid
	 * @return \Epsi\BIA\Session
	 * @throws \Epsi\BIA\SessionException
	 */
	public function updateSession(Document $document, $cookie, $requireValidSession) {
		$token = $document->getOne("//input[@name='transactionToken']/@value");
		$token and $this->token = $token;
		$this->cookie = $cookie;
		$this->isValid = true; // FIXME: dude...
		// FIXME: throwing exception
		return $this;
	}

	/**
	 * Persist session in a file
	 *
	 * @param string $file
	 * @return \Epsi\BIA\Session
	 */
	public function save($file) {
		$a = [
			"cookie" => $this->cookie,
			"token" => $this->token,
		];
		file_put_contents($file, json_encode($a, JSON_PRETTY_PRINT) . "\n");
		return $this;
	}

	/**
	 * Load session from file
	 *
	 * @return \Epsi\BIA\Session
	 */
	public function load($file) {
		$a = json_decode(file_get_contents($file), true);
		$this->cookie = $a["cookie"];
		$this->token = $a["token"];
		return $this;
	}

}

class SessionException extends Exception { }