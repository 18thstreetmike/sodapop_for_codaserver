<?php
/**
 * This class takes any markup string and makes it well formed XML.
 *
 * @author mike
 */

class WellFormer {
	private $letters = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
	private $numbersUnderscoresDashes = array('0','1','2','3','4','5','6','7','8','9','-','_');
	private $emptyTags = array('br', 'img', 'hr', 'input');
	private $defaultRoot = 'div';

	private $document = null;

	public function __construct($document = null) {
		$this->setDocument($document);
	}

	private function setDocument($document) {
		if (!is_null($document)) {
			$this->document = trim($document);
			if (substr($this->document, 0, 1) != '<') {
				$this->document = '<'.$this->defaultRoot.'>'.$this->document.'</'.$this->defaultRoot.'>';
			}
		}
	}

	public function fix ($document = null) {
		if (!is_null($document)) {
			$this->setDocument($document);
		}
		if (!is_null($this->document)) {
			$node = $this->buildNode();
			//echo nl2br(var_export($node));
			return $this->printNode($node);
		} else {
			return '<'.$this->defaultRoot.' />';
		}
	}

	private function buildNode() {
		$node = array();
		$children = array();

		// get the tag name
		preg_match('/<([A-Za-z][A-Za-z0-9-_:]*)/', $this->document, $matches);
		if (!isset($matches[1])) {
			$this->document = substr($this->document, 1);
			return;
		}
		$node['name'] = strtolower($matches[1]);
		$this->document = trim(substr($this->document, strlen($matches[1]) + 1));

		// get the attributes
		if (substr($this->document, 0, 1) == '>' ) {
			$cleanAttributes = '';
			$this->document = trim(substr($this->document, 1));
		} else {
			preg_match('/([^>]*)/', $this->document, $matches);
			if (substr($matches[1], -1) != '/') {
				$rawAttributes = $matches[1];
				$this->document = trim(substr($this->document, strlen($matches[1])));
			} else {
				$rawAttributes = substr($matches[1], 0, strlen($matches[1]) -1);
				$this->document = trim(substr($this->document, strlen($matches[1]) - 1));
			}

			// clean them up
			$attributeMode = true;
			$firstAttributeChar = true;
			$inQuotes = false;
			$cleanAttributes = '';
			$quoteChar = '"';
			for ($i = 0; $i < strlen($rawAttributes); $i++) {
				$char = substr($rawAttributes, $i, 1);
				//echo 'test: '.($attributeMode ? 'true' : 'false').' '.(!$firstAttributeChar ? 'true' : 'false').' '.($char).' '.(($char == '"' || $char == "'") ? 'true' : 'false').' '.substr($cleanAttributes, -1).' '.(substr($cleanAttributes, -1) == '=' ? 'true' : 'false').'<br />';
				if ($attributeMode && $firstAttributeChar) {
					if (in_array(strtolower($char), $this->letters)) {
						$cleanAttributes .= ' '.strtolower($char);
						$firstAttributeChar = false;
					}
				} else if ($attributeMode && !$firstAttributeChar && substr($cleanAttributes, -1) != '=' && $char != '=' && $char != '"' && $char != "'") {
					if (in_array(strtolower($char), $this->letters) || in_array(strtolower($char), $this->numbersUnderscoresDashes)) {
						$cleanAttributes .= strtolower($char);
					}
				} else if ($attributeMode && !$firstAttributeChar && substr($cleanAttributes, -1) != '=' && $char == '=') {
					$cleanAttributes .= '=';
				} else if ($attributeMode && !$firstAttributeChar && ($char == '"' || $char == "'") && substr($cleanAttributes, -1) == '=') {
					$attributeMode = false;
					$cleanAttributes .= '"';
					$quoteChar = $char;
				} else if ($attributeMode && !$firstAttributeChar && substr($cleanAttributes, -1) == '=' && $char != '"' && $char != "'") {
					// skip
				} else if (!$attributeMode && $char != $quoteChar) {
					$cleanAttributes .= $char;
				} else if (!$attributeMode && $char == $quoteChar) {
					$cleanAttributes .= '"';
					$attributeMode = true;
					$firstAttributeChar = true;
				} else if ($attributeMode && trim($char) == '') {
					// skip
				}
			}
			if (substr($cleanAttributes, -1) == '=') {
				$cleanAttributes .= '""';
			} else if (strlen($cleanAttributes) > 0 && substr($cleanAttributes, -1) != '"') {
				$cleanAttributes .= '=""';
			}
		}
		$node['attributes'] = $cleanAttributes;
		$node['children'] = array();
		// determine if this node is empty
		if (substr($this->document, 0,2) == '/>') {
			$this->document = trim(substr($this->document, 2));
			return $node;
		} else if (substr($this->document, 0,1) == '>') {
			$this->document = trim(substr($this->document, 1));
		}

		// gather up child nodes
		while (trim($this->document) != '' && substr($this->document, 0, 2 + strlen($node['name'])) != '</'.$node['name']) {
			if (substr($this->document, 0, 1) != '<') {
				preg_match('/([^<]*)/', $this->document, $matches);
				$node['children'][] = array('text' => $matches[1]);
				$this->document = trim(substr($this->document, strlen($matches[1])));
			} else {
				if (substr($this->document, 0, 2) == '</') {
					return $node;
				} else {
					$child = $this->buildNode();
					if (!is_null($child['name'])) {
						$node['children'][] = $child;
					}
				}
			}
		}

		// clear to next > or <
		$this->document = substr($this->document, 2 + strlen($node['name']));
		$i = 0;
		$char = substr($this->document, $i,1);
		while ($char !== false && $char != '>' && $char != '<') {
			$i++;
			$char = substr($this->document, $i,1);
		}
		if ($char == '>') {
			$this->document = trim(substr($this->document, $i + 1));
		} else {
			$this->document = trim(substr($this->document, $i));
		}
		return $node;

	}

	private function printNode($node) {
		if (isset($node['text'])) {
			return $node['text'];
		} else {
			$retval = '<'.$node['name'].(strlen($node['attributes']) > 0 ? $node['attributes'] : '');
			if (count($node['children']) == 0) {
				return $retval.' />';
			} else {
				$retval .= '>';
				foreach ($node['children'] as $child) {
					$retval .= $this->printNode($child);
				}
				return $retval .= '</'.$node['name'].'>';
			}

		}
	}
}
