<?php

namespace Epsi\BIA;

use \tidy;
use \DOMDocument;
use \DOMNode;
use \DOMNodeList;
use \DOMText;
use \DOMAttr;
use \DOMXPath;
use \Exception;

class Document {

	protected $dom;
	protected $xpath;

	public function __construct($html) {
		$tidy = new tidy();
		$html = $tidy->repairString($html);
		$this->dom = new DOMDocument();
		@$this->dom->loadHTML($html);
		$this->xpath = new DOMXPath($this->dom);
	}

	public function xpath($xpathQuery) {
		return $this->xpath->query($xpathQuery);
	}

	public function getDOM() {
		return $this->dom;
	}

	public function getOne($xpathQuery) {
		$list = $this->xpath->query($xpathQuery);
		if (0 === $list->length) {
			return "";
		}
		$node = $list->item(0);
		if ($node instanceof DOMText) {
			return $node->wholeText;
		} elseif ($node instanceof DOMAttr) {
			return $node->value;
		}
		throw new DocumentException("XPath expression must point to DOMText or DOMAttr element");
	}

	public function getAll($xpathQuery) {
		$list = $this->xpath->query($xpathQuery);
		if (!$list instanceof DOMNodeList) {
			throw new DocumentException("XPath expression must return list of nodes");
		}
		$out = array();
		foreach ($list as $node) {
			if ($node instanceof DOMText) {
				$out[] = $node->wholeText;
			} elseif ($node instanceof DOMAttr) {
				$out[] = $node->value;
			} else {
				throw new DocumentException("Each element must be of type DOMText or DOMAttr");
			}
		}
		return $out;
	}

	public function getAllPairs($xpathQuery1, $xpathQuery2) {
		$keys = array_unique($this->getAll($xpathQuery1));
		$values = $this->getAll($xpathQuery2);
		if (count($keys) != count($values)) {
			throw new DocumentException("Unique keys count does not match values count");
		}
		return array_combine($keys, $values);
	}

}

class DocumentException extends Exception { }