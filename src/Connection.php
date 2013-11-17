<?php

namespace Epsi\BIA;

interface Connection {
	
	public function call($method, $url, array $params, &$cookie);

}