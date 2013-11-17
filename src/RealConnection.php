<?php

namespace Epsi\BIA;

use \Exception;

class RealConnection implements Connection {

	public function call($method, $url, array $params, &$cookie) {
		// check method
		if ("GET" !== $method and "POST" !== $method) {
			throw new RealConnectionException("Illegal method: GET or POST allowed only");
		}

		// configure curl
		$query = http_build_query($params);
		$ch = curl_init();
		if ($method === "POST") {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
		} else {
			$query and $url .= "?{$query}";
			curl_setopt($ch, CURLOPT_HTTPGET, true);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
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

		// make http request
		$html = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		curl_close($ch);

		// save cookies
		$cookie = file_get_contents($cookieJar);
		unlink($cookieJar);

		if ($errno) {
			throw new RealConnectionException("Error requesting HTTP $method $page: $error", $errno);
		}

		return $html;
	}

}

class RealConnectionException extends Exception { }