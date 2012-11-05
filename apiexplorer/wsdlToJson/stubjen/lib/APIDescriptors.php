<?php

/**
 *
 * Utility class for source code generation
 *
 */

define("TYPE_IN", 1);
define("TYPE_OUT", 2);
define("TYPE_INOUT", 3);

/**
 *
 * Represents a WSDL service
 */
class Service {
	public $name;
	public $validatedName;
	public $package;
	public $version;
	public $doc;
	public $operations;
	public $targetNamespace;
}

/**
 *
 * an API operation as defined by the service interface
 */
class Operation {
	public $name;
	public $validatedName;
	public $input = array();
	public $output;
	public $fault;
	public $doc;
}

class RestfulOperation extends Operation {
	public $uri;
	public $httpMethod;
	public $requestContentType;
}


/**
 *
 * A restriction based simple type from the WSDL document
 */
class EnumType {
	public $name;
	public $validatedName;
	public $package;
	public $doc;
	public $values = array();
	public $isUsed;
	public $isContainsAttribute=FALSE;
}

class TypePackage {
	public $targetNamespace;
	public $namespacePrefixes = array();
	public $types = array();
}

/**
 *
 * a complex type based class from the WSDL document
 */
class DataType {
	public $name;
	public $validatedName;
	public $package;
	public $members = array();
	public $isSimpleType = FALSE;
	public $extends;
	public $extendsPackage;
	public $doc;
	private $inOutType;
	public $isUsed;
	public $faults = array();
	public $isContainsAttribute=FALSE;
	

	public function setInOutType($inOutType) {
		if( $inOutType != TYPE_IN && $inOutType != TYPE_OUT && $inOutType != TYPE_INOUT )
		throw new WSDLInterpreterException("Invalid type $inOutType passed for Parameter type ($dataType->name)");
			
		if( $this->inOutType != "" && $this->inOutType != $inOutType ) {
			$this->inOutType = TYPE_INOUT;
		} else {
			$this->inOutType = $inOutType;
		}
	}

	public function isOutputType() {
		return ($this->inOutType == TYPE_INOUT || $this->inOutType == TYPE_OUT);
	}

	public function isInputType() {
		return ($this->inOutType == TYPE_INOUT || $this->inOutType == TYPE_IN);
	}

	public function isInOutType() {
		return ($this->inOutType == TYPE_INOUT);
	}
}

/**
 *
 * Member elements of a complex type
 */
class Property {
	public $name;
	public $validatedName;
	public $validatedClass;
	public $class;
	public $package;
	public $isSimpleType = false;
	public $maxOccurs;
	public $minOccurs;
	public $doc;
	public $validation;
	public $isAttribute=FALSE;
	public $isValue=FALSE;
	public $isContainsAttribute=FALSE;
	public $isRef=FALSE;
	public $default;
}
?>