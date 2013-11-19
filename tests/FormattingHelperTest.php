<?php

require_once __DIR__ . "/../src/FormattingHelper.php";
require_once "PHPUnit/Autoload.php";

use \Epsi\BIA\FormattingHelper;

/**
 * Formating helper test
 *
 * @author Michał Rudnicki <michal.rudnicki@epsi.pl>
 */
final class FormattingHelperTest extends PHPUnit_Framework_TestCase {

	/**
	 * @test
	 */
	public function text() {
		$dirty = "\tSingle\r\nline\r\nof\r\ntext\r\n111\t";
		$expected = "Single line of text 111";
		$actual = FormattingHelper::text($dirty);
		$this->assertSame($expected, $actual);
	}

	/**
	 * @test
	 */
	public function date() {
		$dirty = "\t01/\\02/\\03\t";
		$expected = "01/02/03";
		$actual = FormattingHelper::date($dirty);
		$this->assertSame($expected, $actual);
	}

	/**
	 * @test
	 */
	public function money() {
		$dirty = "  1,200.00\t\\u1000";
		$expected = 1200.00;
		$actual = FormattingHelper::money($dirty);
		$this->assertSame($expected, $actual);
	}

}