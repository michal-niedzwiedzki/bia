<?php

namespace Epsi\BIA;

use \Exception;

class Session {

	protected $cookieJar;
	protected $isValid = false;
	protected $token = null;

	protected $lastMethod = null;
	protected $lastPage = null;
	protected $lastParams = [ ];

	protected $lastErrno = null;
	protected $lastResponse = null;

	public function __construct($cookieJar, $token = null) {
		$this->cookieJar = $cookieJar;
		$this->token = $token;
	}

	public function isValid() {
		return $this->isValid;
	}

	public function getCookieJar() {
		return $this->cookieJar;
	}

	public function getToken() {
		return $this->token;
	}

	public function clear() {
		$this->isValid = false;
	}

	public function recordRequest($method, $page, array $params) {
		$this->lastMethod = $method;
		$this->lastPage = $page;
		$this->lastParams = $params;
	}

	public function recordResponse($html, $errno) {
		$this->lastResponse = $html;
		$this->lastErrno = $errno;
	}

	public function forgetLastCallDetails() {
		$this->lastMethod = null;
		$this->lastPage = null;
		$this->lastParams = [ ];
		$this->lastErrno = null;
		$this->lastResponse = null;
	}

	public function attachSession(array &$params) {
		$this->token and $params["transactionToken"] = $this->token;
	}

	public function updateSession(Document $document, $requireValidSession) {
		$token = $document->getOne("//input[@name='transactionToken']/@value");
		$token and $this->token = $token;
		$this->isValid = true;
	}

}

class SessionException extends Exception { }