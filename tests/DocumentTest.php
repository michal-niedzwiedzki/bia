<?php

require_once __DIR__ . "/../src/Document.php";
require_once "PHPUnit/Autoload.php";

use \Epsi\BIA\Document;

/**
 * Document test
 *
 * @author Michał Rudnicki <michal.rudnicki@epsi.pl>
 */
final class DocumentTest extends PHPUnit_Framework_TestCase {

	/**
	 * @test
	 */
	public function xpath() {
		$html = "<html><head></head><body><div><h1>TEST</h1></div></body></html>";
		$document = new Document($html);
		$element = $document->xpath("//h1/text()")->item(0);
		$this->assertTrue($element instanceof DOMText);
		$this->assertSame("TEST", $element->wholeText);
	}

	/**
	 * @test
	 */
	public function getOne() {
		$html = "<html><head></head><body><div><h1>TEST1</h1><h1>TEST2</h1></div></body></html>";
		$document = new Document($html);
		$text = $document->getOne("//h1/text()");
		$this->assertSame("TEST1", $text);
	}

	/**
	 * @test
	 */
	public function getAll() {
		$html = "<html><head></head><body><div><h1>TEST1</h1><h1>TEST2</h1></div></body></html>";
		$document = new Document($html);
		$a = $document->getAll("//h1/text()");
		$this->assertSame(["TEST1", "TEST2"], $a);
	}

}