<?php
require_once dirname(__FILE__) . '/../AbstractGenerator.php';
require_once dirname(__FILE__) . '/../APIDescriptors.php';
require_once dirname(__FILE__) . '/../GeneratorUtil.php';

class PHPGenerator extends AbstractGenerator {

	/**
	 * Array of classes and members representing the WSDL message types
	 * @var array
	 * @access private
	 */
	private $_classmap = array ();

	protected function _validateNamingConventions() {
		foreach ($this->_services as $s) {
			$s->validatedName = $this->_validateClassName($s->name);
			foreach ($s->operations as $o) {
				$o->validatedName = $this->_validateNamingConvention($o->name);
			}
		}

		foreach ($this->_dataTypes as $t) {
			$t->validatedName = $t->name;
			if ($t instanceof EnumType)
				continue;

			foreach ($t->members as $m) {
				$m->validatedName = $this->_validateNamingConvention($m->name);
				$m->validatedClass = $this->_validateType($m->class);
			}
		}
	}
	
	public function generateJson($serviceDef)
	{
		$methods = array();
		foreach ( $serviceDef->operations as $operation)
		{
			unset($params);
			unset($reqParams);
			$reqParams = array();
			foreach ($operation->input as $inputName => $inpt)
			{
				$params = $this->generateJsonTypes($inpt);
				$reqParam = array(
						'Name' => lcfirst($inputName),
						'Default' => '',
						'Members' => $params,
				);
				if(!is_string($inpt)) { 					
					$reqParam = $reqParam +
					array('ValidatedClass' => $inpt->validatedName,
							'Description' => $inpt->doc,
							'Required' => $inpt->isUsed,
							'Type' => 'complex');					
				} else {
					$reqParam = $reqParam +
					array('ValidatedClass' => $inpt,							
							'Required' => true, //TODO:
							'Type' => 'simple');
				}
				$reqParams[] = $reqParam;
			}
			$methods[] = array(
					'Name' => $operation->name,
					'Description' => $operation->doc,
					'URI' => isset($operation->uri) ? $operation->uri : NULL,
					'RequiresOAuth' => 'N',
					'Required' => 'Y',
					'Type' => 'complex',
					'Synopsis' =>$operation->doc,
					'HTTPMethod' => isset($operation->httpMethod) ? $operation->httpMethod : "POST",
					'RequestContentType' => isset($operation->requestContentType) ? $operation->requestContentType : "",
					'Default' => '',
					'Parameters' => $reqParams
			);

		}

		$endpoints = array(
				'endpoints' => array(
						array(
								'name' => $serviceDef->name,
								'methods' => $methods
						)
				)
		);
		return json_encode($endpoints);		
	}
	
	public function generateJsonTypes($inpt)
	{
		if(is_string($inpt)) {
			// We got a simple type
			return $inpt;
		} else {
			$arr = array();
			if($inpt->extends)
			{
				$type = $inpt->extends;
				$typ = $inpt->extendsPackage.':'.$type;
				$temp = $this->generateJsonTypes($this->_dataTypes[$typ]);
				foreach($temp as $val)
				{
					$arr[] = $val;
				}

			}
			foreach ($inpt->members as $mbr)
			{

				if($mbr->minOccurs == 1)
				{
					$required = 'Y';
				}
				else
					$required = 'N';
				$type = $mbr->validatedClass;
				$typ = $mbr->package.':'.$type;				
				if($this->isComplexType($mbr))
				{
					$jsonType = $this->generateJsonTypes($this->_dataTypes[$typ]);
					unset($compArr);
					$memberProps = array(
						'Name' => lcfirst($mbr->name),
						'ValidatedClass' => $mbr->validatedClass,
						'Description' => $mbr->doc,
						'Required' => $required,
						'Type' => 'complex',
						'Default' => isset($mbr->default) ? $mbr->default : ''
					);					
					if( $mbr->maxOccurs && ($mbr->maxOccurs == "unbounded" || $mbr->maxOccurs > 1))
					{
						$memberProps['Members'] = array($jsonType);
					}
					else
					{
						$memberProps['Members'] = $jsonType;
					}
					$arr[] = $memberProps;

				}
				else if(array_key_exists($typ, $this->_dataTypes) && $this->_dataTypes[$typ] instanceof EnumType )
				{
					$enumTyp = $this->generateEnumTypes($this->_dataTypes[$typ]);
					$arr[] = array(
							'Name' => lcfirst($mbr->name),
							'Required' => $required,
							'ValidatedClass' => $mbr->validatedClass,
							'Type' => 'enumerated',
							'Description' => $mbr->doc,
							'Default' => isset($mbr->default) ? $mbr->default : '',
							'EnumeratedList' => $enumTyp,
					);
				}
				else
				{
					$arr[] = array(
							'Name' => lcfirst($mbr->name),
							'ValidatedClass' => $mbr->validatedClass,
							'Required' => $required,
							'Type' => $type,
							'Default' => isset($mbr->default) ? $mbr->default : '',
							'Description' => $mbr->doc,

					);
				}
			}
			$arr = $this->srt($arr);
			return $arr;
		}

	}
	public function srt($arr)
	{
		$count = count($arr);
		for($j = 0; $j <$count; $j++)
		{
			for($i = 0; $i < $count -1; $i++)
			{
				if($arr[$i]['Required'] < 'Y')
				{
					$tmp = $arr[$i+1];
					$arr[$i+1] = $arr[$i];
					$arr[$i] = $tmp;
				}
				if($arr[$i]['Type'] == 'complex' && $arr[$i+1]['Type'] != 'complex' )
				{
					$tmp = $arr[$i+1];
					$arr[$i+1] = $arr[$i];
					$arr[$i] = $tmp;
				}
				if($arr[$i]['Type'] != 'complex' && $arr[$i]['Required'] < 'Y' )
				{
					$tmp = $arr[$i+1];
					$arr[$i+1] = $arr[$i];
					$arr[$i] = $tmp;
				}
				if($arr[$i]['Type'] == 'complex' && $arr[$i]['Required'] < 'Y' )
				{
					$tmp = $arr[$i+1];
					$arr[$i+1] = $arr[$i];
					$arr[$i] = $tmp;
				}
			}
		}
		return $arr;
	}


	public function generateEnumTypes($enumType)
	{
		return $enumType->values;
	}

	public function generateService($classPrefix) {
		if (count($this->_services) == 0) {
			throw new Exception("No services loaded");
		}

		$outputFiles = array ();
		foreach ($this->_services as $serviceDefintion) {
			$jsonFileName = $serviceDefintion->name . ".json";
			$fh = fopen($jsonFileName, 'w') or die("can't open file");
			fwrite($fh, $this->generateJson($serviceDefintion));
			fclose($fh);
			$outputFiles[] = $jsonFileName;
		}
		if (sizeof($outputFiles) == 0) {
			throw new Exception("Error writing PHP source files.");
		}
		return $outputFiles;
	}

	public function generateModel($classPrefix) {
		return;
	}


	private function isComplexType(Property $prop) {	
		$defaultTypes = array (
				'int',
				'boolean',
				'string',
				'long',
				'decimal',
				'dateTime'
		);
		$type = $this->getTypeDescriptor($prop);		
		if (in_array($prop->class, $defaultTypes) || $type == null|| $type instanceOf EnumType) {
			return false;
		}
		return true;
	}
	private function packageNamingConvention($package) {
		if (strstr($package, "PayPalAPI")) {
			$package = "urn";
		}
		if (strstr($package, "eBL")) {
			$package = "ebl";
		}
		if (strstr($package, "Core")) {
			$package = "cc";
		}
		if (strstr($package, "Enhanced")) {
			$package = "ed";
		}
		return $package;
	}

	/**
	 * Validates a name against standard PHP naming conventions
	 *
	 * @param string $name the name to validate
	 *
	 * @return string the validated version of the submitted name
	 *
	 * @access private
	 */
	private function _validateNamingConvention($name) {
		return preg_replace('#[^a-zA-Z0-9_\x7f-\xff]*#', '', preg_replace('#^[^a-zA-Z_\x7f-\xff]*#', '', $name));
	}

	/**
	 * Validates a class name against PHP naming conventions and already defined
	 * classes, and optionally stores the class as a member of the interpreted classmap.
	 *
	 * @param string $className the name of the class to test
	 * @param boolean $addToClassMap whether to add this class name to the classmap
	 *
	 * @return string the validated version of the submitted class name
	 *
	 * @access private
	 * @todo Add reserved keyword checks
	 */
	private function _validateClassName($className, $addToClassMap = true) {
		$validClassName = $this->_validateNamingConvention($className);

		if (class_exists($validClassName)) {
			throw new Exception("Class " . $validClassName . " already defined." .
					" Cannot redefine class with class loaded.");
		}

		return $validClassName;
	}

	/**
	 * Validates a wsdl type against known PHP primitive types, or otherwise
	 * validates the namespace of the type to PHP naming conventions
	 *
	 * @param string $type the type to test
	 *
	 * @return string the validated version of the submitted type
	 *
	 * @access private
	 * @todo Extend type handling to gracefully manage extendability of wsdl definitions, add reserved keyword checking
	 */
	private function _validateType($type) {
		$array = false;
		if (substr($type, -2) == "[]") {
			$array = true;
			$type = substr($type, 0, -2);
		}
		switch (strtolower($type)) {
			case "int" :
			case "integer" :
			case "long" :
			case "byte" :
			case "short" :
			case "negativeInteger" :
			case "nonNegativeInteger" :
			case "nonPositiveInteger" :
			case "positiveInteger" :
			case "unsignedByte" :
			case "unsignedInt" :
			case "unsignedLong" :
			case "unsignedShort" :
				$validType = "integer";
				break;

			case "float" :
			case "long" :
			case "double" :
			case "decimal" :
				$validType = "double";
				break;

			case "string" :
			case "token" :
			case "normalizedString" :
			case "hexBinary" :
				$validType = "string";
				break;

			default :
				$validType = $this->_validateNamingConvention($type);
				break;
		}
		if ($array) {
			$validType .= "[]";
		}
		return $validType;
	}

}
?>
