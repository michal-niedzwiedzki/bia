<?php

namespace Epsi\BIA;

/**
 * Formatting helper
 *
 * @author Michał Rudnicki <michal.rudnicki@epsi.pl>
 */
class FormattingHelper {

	public static function text($s) {
		return str_replace(["\r", "\n", "\t"], ["", " ", " "], trim($s, " \t\r\n"));
		//return preg_replace("/[^[[:alnum:]][[:punct:]] ]*/", "", $s);
	}

	public static function date($s) {
		return str_replace("\\", "", trim($s, " \t\r\n"));
		//return preg_replace("/^([[:digit:]]\/)/", "", $s);
	}

	public static function money($s) {
		return (float)str_replace(",", "", trim($s, " \t\r\n"));
	}

}