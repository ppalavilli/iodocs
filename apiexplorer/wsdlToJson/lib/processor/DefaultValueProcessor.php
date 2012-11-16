<?php

require_once 'IModelProcessor.php';

class DefaultValueProcessor implements IModelProcessor {

	private $_defaults;

	public function __construct($options) {
		if(isset($options['defaultsFile'])) {
			$this->_defaults = json_decode(file_get_contents($options['defaultsFile']));
		}
	}

	public function process($nameSpaces, $defaultNamespace, $services, $dataTypes) {
		foreach($dataTypes as $type) {
			foreach($type->members as $m) {
				$key = $type->name . "." . $m->name;
				if(array_key_exists($key, $this->_defaults)) {
					$m->default = $this->_defaults->$key;
				}
			}
		}
	}
}