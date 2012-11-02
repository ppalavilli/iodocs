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
		$json = json_encode($endpoints);
		$myFile = $serviceDef->name.".json";
		$fh = fopen($myFile, 'w') or die("can't open file");

		fwrite($fh, $json);

		fclose($fh);
		echo "Generated JSON digest successfully";
		exit;
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
					//	if(!$mbr->isSimpleType)
				{


					$jsonType = $this->generateJsonTypes($this->_dataTypes[$typ]);
					unset($compArr);					
					if( $mbr->maxOccurs && ($mbr->maxOccurs == "unbounded" || $mbr->maxOccurs > 1))
					{
						$arr[] = array(
								'Name' => lcfirst($mbr->name),
								'ValidatedClass' => $mbr->validatedClass,
								'Description' => $mbr->doc,
								'Required' => $required,
								'Type' => 'complex',
								'Default' => '',
								'Members' => array($jsonType),
						);
					}
					else
					{

						$arr[] = array(
								'Name' => lcfirst($mbr->name),
								'ValidatedClass' => $mbr->validatedClass,
								'Description' => $mbr->doc,
								'Required' => $required,
								'Type' => 'complex',
								'Default' => '',
								'Members' => $jsonType,
						);
					}

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
							'Default' => '',
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
							'Default' => '',
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
			$this->generateJson($serviceDefintion);
		}
		if (sizeof($outputFiles) == 0) {
			throw new Exception("Error writing PHP source files.");
		}
		return $outputFiles;
	}

	public function generateModel($classPrefix) {

		$outputFiles = array ();

		foreach ($this->_services as $service) {

			$classes = $this->_dataTypes;
			$classCode = "<?php\n";
			$classCode .= " /**\n * Stub objects for $service->name \n * Auto generated code \n * \n */\n";


			foreach ($classes as $c) {
				if ($c instanceof EnumType)
					continue;
				$classCode .= $this->_generateClass($c);
			}
			$classCode .= "?>";

			$baseDir = $this->_outputDirectory . DIRECTORY_SEPARATOR . $service->name;
			if (!file_exists($baseDir)) {
				mkdir($baseDir, parent :: WORKING_DIR_PERMS, true);
			}
			$fileName = $baseDir . DIRECTORY_SEPARATOR . $service->name . ".php";
			if (file_put_contents($fileName, $classCode . "\n\n")) {
				$outputFiles[] = $fileName;
			}
		}
		return $outputFiles;
	}

	/**
	 *
	 * Generates service wrapper class
	 * @param Service $service
	 */
	private function _generateService($service) {
		$className = $service->validatedName . "Service";

		$return = "require_once('$service->validatedName.php');\nrequire_once('PPUtils.php');\n";
		$return .= '/**' . "\n";
		$return .= ' * ' . $service->validatedName . " wrapper class\n";
		$return .= ' * Auto generated code' . "\n";
		$return .= ' */' . "\n";
		$return .= "class " . $className . " extends PPBaseService {\n";
		$return .= "\tprivate static $" . "SERVICE_VERSION='" . $service->version . "';\n";
		$return .= "\t" . 'public function __construct() {' . "\n";

		$return .= "\t\tparent::__construct('" . $service->name . "');\n";
		$return .= "\t}\n\n";
		if (strstr($this->_defaultNamespace, "urn")) {

			$return .= "\tprivate function setStandardParams(AbstractRequestType $" . "request) {\n";
			$return .= "\t	if ($" . "request->Version == null) {\n";
			$return .= "\t\t	$" . "request->Version=$" . "SERVICE_VERSION;\n";
			$return .= "\t\t}\n\t}\n";

		}
		$functionMap = array ();
		$functions = $service->operations;
		foreach ($functions as $function) {
			if (!isset ($functionMap[$function->validatedName])) {
				$functionMap[$function->validatedName] = array ();
			}
			$functionMap[$function->validatedName][] = $function;
		}
		$skipOperations = $this->getOperationsToSkip();
		foreach ($functionMap as $functionName => $functionNodeList) {
			if (!in_array($functionName, $skipOperations))
				$return .= $this->_generateServiceFunction($functionName, $functionNodeList) . "\n\n";
		}

		$return .= "}";
		return $return;
	}

	/**
	 * Generates the PHP code for a WSDL service operation function representation
	 *
	 * The function code that is generated examines the arguments that are passed and
	 * performs strict type checking against valid argument combinations for the given
	 * function name, to allow for overloading.
	 *
	 * @param string $functionName the php function name
	 * @param array $functionNodeList array of DOMElement interpreted WSDL function nodes
	 * @return string the php source code for the function
	 *
	 * @access private
	 */
	private function _generateServiceFunction($functionName, $functionNodeList) {
		$return = "";
		$return .= "\t" . '/**' . "\n";
		$return .= "\t" . ' * Service Call: ' . $functionName . "\n";

		$parameterComments = array ();
		$variableTypeOptions = array ();
		$returnOptions = array ();
		foreach ($functionNodeList as $functionNode) {
			$docNode = $functionNode->doc;
			if ($docNode)
				$return .= GeneratorUtil :: formatDoc($docNode, "\t");

			$parameters = $functionNode->input;
			$paramList = array ();

			if (count($parameters) > 0) {

				foreach ($parameters as $name => $type) {
					$return .= "\t * " . '@param ';
					if (substr($type->name, 0, -2) == "[]") {
						$parameterTypes = "array";
					} else {
						$parameterTypes = $type->name;
					}
					$return .= $type->name . ' $' . GeneratorUtil :: lcFirst($type->validatedName);
					$return .= "\n";
					$paramList[] = '$' . GeneratorUtil :: lcFirst($type->validatedName);
				}
			}
			$returns = $functionNode->output;
			if ($returns != null) {
				$returnOptions[] = $returns->validatedName;

			}
		}
		$paramList[] = "\$apiUsername";

		$return .= "\t" . ' * @return ' . join("|", array_unique($returnOptions)) . "\n";
		$return .= "\t" . ' * @throws APIException' . "\n";
		$return .= "\t" . ' */' . "\n";

		$return .= "\t" . 'public function ' . $functionName . '(';
		$return .= implode(", ", $paramList) . "=null";
		$return .= ') {' . "\n";
		if (strstr($this->_defaultNamespace, "urn")) {
			foreach ($functionNode->input as $i) {
				foreach ($i->members as $member) {
					$return .= "\t\t$" . "this->setStandardParams(" . $paramList[0] . "->" . $member->name . ");\n";
				}
			}
		}
		if ($returnOptions != null)
			$return .= "\t\t" . '$ret = new ' . $returnOptions[0] . '();' . "\n";
		$return .= "\t\t" . '$resp = $this->call("' . $functionNodeList[0]->name . '"';

		if (count($paramList) > 0)
			$return .= ", " . implode(", ", $paramList);
		$return .= ");\n";
		if (strstr($this->_defaultNamespace, "svcs"))
			$return .= "\t\t" . '$ret->init(PPUtils::nvpToMap($resp));' . "\n";
		else
			if (strstr($this->_defaultNamespace, "urn"))
			$return .= "\t\t" . '$ret->init(PPUtils::xmlToArray($resp));' . "\n";
		$return .= "\t\t" . 'return $ret;' . "\n\t}\n";

		return $return;
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

	/**
	 * Generate member declarations for a class
	 * alon with member documentation
	 *
	 * @param DataType $class
	 */
	private function _generateClassMembers($class) {

		/*		$json = json_encode($class);
		 echo "<pre>";
		print_r($json);
		echo "</pre>";*/

		foreach ($class->members as $property) {

			$return = "";

			// Get documentation for this class member from WSDL
			$return .= "\t/**\n";
			if ($property->doc != null)
				$return .= GeneratorUtil :: formatDoc($property->doc, "\t") . "\t *\n";
			if (GeneratorUtil :: isCollectionItem($property)) {
				$return .= "\t * array\n";
			}
			$return .= "\t * @access public\n" . "\t * @var " . $property->class . "\n" . "\t */\n"

			// Generate a public member variable. No getter/setters for now
			. "\t" . 'public $' . $property->validatedName . ";\n\n";

		}

		return $return;
	}

	private function _generateNVPSerializerMethod($class) {
		if (strstr($this->_defaultNamespace, "svcs")) {
			$nvpSerializeCode = "\n\tpublic function toNVPString(\$prefix='') { \n";
			$nvpSerializeCode .= "\t\t\$str = '';\n";
			$nvpSerializeCode .= "\t\t\$delim = '';\n";
			if ($class->extends != null)
				$nvpSerializeCode .= "parent::toNVPString(\$prefix='');";

			foreach ($class->members as $property) {

				if (GeneratorUtil :: isCollectionItem($property)) {
					// handle collection type
					$nvpSerializeCode .= "\t\t" . 'for($i=0; $i<count($this->' . $property->validatedName . ');$i++) {' . "\n";
					if ($this->isComplexType($property)) {
						$nvpSerializeCode .= "\t\t\t" . '$newPrefix = $prefix . "' . $property->name . '($i).";' . "\n\t\t\t\$str .= ";
						$nvpSerializeCode .= "\$delim . call_user_func(array(\$this->" . $property->validatedName . "[\$i], 'toNVPString'), \$newPrefix);\n";
					} else {
						$nvpSerializeCode .= "\t\t\t" . '$str .= $delim .  $prefix ."' . $property->name . '($i)=" .  urlencode($this->' . $property->validatedName . '[$i]);' . "\n";
					}
					$nvpSerializeCode .= "\t\t }\n";
				} else {
					$nvpSerializeCode .= "\t\tif( \$this->" . $property->validatedName . " != null ) {\n";
					if ($this->isComplexType($property)) {
						// nested complex type
						$nvpSerializeCode .= "\t\t\t\$newPrefix = \$prefix . '" . $property->name . ".';\n\t\t\t\$str .= ";
						$nvpSerializeCode .= "\$delim . call_user_func(array(\$this->" . $property->validatedName . ", 'toNVPString'), \$newPrefix);\n";
					} else {
						$nvpSerializeCode .= "\t\t\t\$str .= \$delim .  \$prefix . '" . $property->name . "=' . urlencode(\$this->" . $property->validatedName . ");\n";
					}
					$nvpSerializeCode .= "\t\t\t\$delim = '&';\n\t\t}\n";
				}
			}

			$nvpSerializeCode .= "\n\t\treturn \$str;\n\t}\n\n";
			return $nvpSerializeCode;
		}
		if (strstr($this->_defaultNamespace, "urn")) {
			$typePackage = "";
			$typeName = "";
			$flag = 0;
			$nvpSerializeCode = "\n\tpublic function toXMLString()  {\n";
			$nvpSerializeCode .= "\t\t\$str = '';\n";
			if ($class->extends != null)
				$nvpSerializeCode .= "$" .
				"str.=parent::toXMLString();\n";
			foreach ($this->_services as $s) {
				foreach ($s->operations as $o) {
					if ($o->input != null) {
						foreach ($o->input as $i) {
							if ($i->validatedName == $class->validatedName) {
								$typePackage = $this->packageNamingConvention($class->package);
								$typeName = $class->validatedName;
								$nvpSerializeCode .= "\$str.='<" . $typePackage . ":" . $typeName . ">';\n";
								$flag = 1;
							}
						}
					}
				}
			}
			foreach ($class->members as $property) {
				if ($property->isRef) {
					$package = $property->package;
				} else {
					$package = $class->package;
				}

				foreach ($this->_dataTypes as $types) {
					if ($types->name == $property->class) {
						if ($types->isContainsAttribute) {
							$property->isContainsAttribute = true;
						}
					}
				}

				$package = $this->packageNamingConvention($package);
				$nvpSerializeCode .= "\t\tif(\$this->" . $property->validatedName . " != null ) {\n";
				if (GeneratorUtil :: isCollectionItem($property)) {
					// handle collection type
					$nvpSerializeCode .= "\t\t" . 'for($i=0; $i<count($this->' . $property->validatedName . ');$i++) {' . "\n";
					if ($this->isComplexType($property)) {

						if ($property->isContainsAttribute) {
							$nvpSerializeCode .= "\t\t\t\$str .= '<" . $package . ":" . $property->name . "';\n\t\t\t\$str .= ";
							$nvpSerializeCode .= "\$this->$property->validatedName[\$i]->toXMLString();\n";
							$nvpSerializeCode .= "\t\t\t\$str .=  '</" . $package . ":" . $property->name . ">';\n";

						}
						else if($property->isAttribute)
						{
							$nvpSerializeCode .="\t\t\t\$str .= '\t".$property->name."=\"'.\$this->".$property->name[$i].".'\">';\n";
						}
						else {
							$nvpSerializeCode .= "\t\t\t\$str .= '<".$package.":".$property->name.">';\n\t\t\t\$str .= ";
							$nvpSerializeCode .=  "\$this->$property->validatedName[\$i]->toXMLString();\n";
							$nvpSerializeCode .= "\t\t\t\$str .=  '</".$package.":".$property->name.">';\n";
						}
					} else {
						if ($property->isValue) {
							$nvpSerializeCode .= "\t\t\t\$str .=" . "\$this->value[$i];" . "\n";
						} else {
							$nvpSerializeCode .= "\t\t\t\$str .= '<" . $package . ":" . $property->name . ">'.$" . "this->" . $property->validatedName . "[\$i].'</" . $package . ":" . $property->name . ">';\n";

						}

					}
					$nvpSerializeCode .= "\t\t }\n";
				} else {

					if ($this->isComplexType($property)) {
						if ($this->getTypeDescriptor($property) instanceof EnumType) {
							if ($property->isAttribute) {
								$nvpSerializeCode .= "\t\t\t\$str .= '\t" . $property->name . "=\"'.\$this->" . $property->name . ".'\">';\n";
							} else {
								$nvpSerializeCode .= "\t\t\t\$str .= '<" . $package . ":" . $property->name . ">'.$" . "this->" . $property->validatedName . ".'</" . $package . ":" . $property->name . ">';\n";

							}
						} else {

							if ($property->isContainsAttribute) {
								$nvpSerializeCode .= "\t\t\t\$str .='<" . $package . ":" . $property->name . "';\n\t\t\t\$str .= ";
								$nvpSerializeCode .= "\$this->" . $property->validatedName . "->toXMLString();\n";
								$nvpSerializeCode .= "\t\t\t\$str .=  '</" . $package . ":" . $property->name . ">';\n";
							} else
								if ($property->isAttribute) {

								$nvpSerializeCode .= "\t\t\t\$str .= '\t" . $property->name . "=\"'.\$this->" . $property->name . ".'\">';\n";

							} else {

								// nested complex type
								$nvpSerializeCode .= "\t\t\t\$str .='<" . $package . ":" . $property->name . ">';\n\t\t\t\$str .= ";
								$nvpSerializeCode .= "\$this->" . $property->validatedName . "->toXMLString();\n";
								$nvpSerializeCode .= "\t\t\t\$str .=  '</" . $package . ":" . $property->name . ">';\n";
							}
						}
					} else {
						if ($property->isValue) {
							$nvpSerializeCode .= "\t\t\t\$str .=" . "\$this->value;" . "\n";
						} else {

							$nvpSerializeCode .= "\t\t\t\$str .= '<" . $package . ":" . $property->name . ">'.$" . "this->" . $property->validatedName . ".'</" . $package . ":" . $property->name . ">';\n";
						}
					}

				}
				$nvpSerializeCode .= "\t\t }\n";
			}
			if ($flag)
				$nvpSerializeCode .= "$" .
				"str.='</" . $typePackage . ":" . $typeName . ">';\n";

			$nvpSerializeCode .= "\n\t\treturn \$str;\n\t}\n\n";
			return $nvpSerializeCode;
		}
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
	 *
	 *
	 * @param DataType $class
	 */
	private function _generateNVPDeserializerMethod($type) {

		if (strstr($this->_defaultNamespace, "urn")) {
			$nvpDeserializeCode = "\n\tpublic function init(\$arr = null) {\n\t\tif(\$arr != null) {\n";

			if ($type->extends != null)
				$nvpDeserializeCode .= "\t\t\t" . 'parent::init($arr);';
			$nvpDeserializeCode .= "\t\t\t" . 'foreach ($arr as $arry){' . "\n";
			foreach ($type->members as $property) {

				if (strcmp($property->maxOccurs, "unbounded") == 0 || $property->maxOccurs > 1) {


					// TODO: needs editing(case : array of arrays) ex: array of errors
					if ($this->isComplexType($property)) {
						if ($this->getTypeDescriptor($property) instanceof EnumType) {

						} else {
							$nvpDeserializeCode .= "\t\t\t" . 'if (is_array($arry["children"])&& ($arry["children"])!=null) 	{' . "\n";
							$nvpDeserializeCode .= "\t\t\t\$" . "i=0;\n\t\t\twhile(true) {" . "\n";
							$nvpDeserializeCode .= "\t\t\t" . 'if($arry["name"]=="' . strtolower($property->name) . '[$i]") {' . "\n";
							$nvpDeserializeCode .= "\t\t\t\t" . '$this->' . $property->validatedName . '[$i] = new ' . $property->validatedClass . '();' . "\n";
							$nvpDeserializeCode .= "\t\t\t\t" . '$this->' . $property->validatedName . '[$i]->init($arry["children"]);' . "\n";
							$nvpDeserializeCode .= "\t\t\t}\n";
							$nvpDeserializeCode .= "\t\t\telse break;\n\t\t\t\$"."i++;\n";
							$nvpDeserializeCode .= "\t\t\t}\n";
							$nvpDeserializeCode .= "\t\t}\n";

							$nvpDeserializeCode .= "\t\t\t\t" . 'if(is_array($arry["children"]) && ($arry["children"])!=null && $arry["name"]=="' . strtolower($property->name) . '") {' . "\n";
							$nvpDeserializeCode .= "\t\t\t\t" . '$this->' . $property->validatedName . ' = new ' . $property->validatedClass . '();' . "\n";
							$nvpDeserializeCode .= "\t\t\t\t" . '$this->' . $property->validatedName . '->init($arry["children"]);' . "\n";
							$nvpDeserializeCode .= "\t\t\t\t\t}\n";
						}


						//$arr, $newPrefix
					} else {
						//TODO: nvp deserialize for array of simple types
					}


				} else {
					if ($property->minOccurs == 1) {
						$mandatoryParams[] = $property->name;
					}

					if ($this->isComplexType($property)) {
						// nested complex type
						if ($this->getTypeDescriptor($property) instanceof EnumType) {
							$nvpDeserializeCode .= "\t\t\tif(\$arry != null && isset(\$arry['text']) && \$arry['name']=='" . strtolower($property->name) . "') {\n\t\t\t\t";
							$nvpDeserializeCode .= "\$this->" . $property->validatedName . ' = $arry["text"];';
						} else {
							$nvpDeserializeCode .= "\t\t\t" . 'if ( is_array($arry["children"])&& ($arry["children"])!=null) 	{' . "\n";

							$nvpDeserializeCode .= "\t\t\t" . 'if( $arry["name"]=="' . strtolower($property->name) . '") {' . "\n";

							$nvpDeserializeCode .= "\t\t\t\t\$this->" . $property->validatedName . ' = new ' . $property->validatedClass . '();' . "\n";
							$nvpDeserializeCode .= "\t\t\t\t\$this->" . $property->validatedName . '->init($arry["children"]);' . "\n";
							$nvpDeserializeCode .= "\t\t\t\t\t}\n";
						}

						$nvpDeserializeCode .= "\t\t\t}\n";
					} else {

						$nvpDeserializeCode .= "\t\t\tif(\$arry != null && isset(\$arry['text']) && \$arry['name']=='" . strtolower($property->name) . "') {\n\t\t\t\t";
						$nvpDeserializeCode .= "\$this->" . $property->validatedName . ' = $arry["text"];';

						$nvpDeserializeCode .= "\n\t\t\t}\n";

					}
				}

			}
			$nvpDeserializeCode .= "\t\t}\n";
			$nvpDeserializeCode .= "\t\t}\n\t}\n";
		} else {

			$nvpDeserializeCode = "\n\tpublic function init(\$map = null, \$prefix='') {\n\t\tif(\$map != null) {\n";

			foreach ($type->members as $property) {

				if (strcmp($property->maxOccurs, "unbounded") == 0 || $property->maxOccurs > 1) {
					//TODO: assuming there are no more than 10 elements for now
					$nvpDeserializeCode .= "\t\t\t$" . "i=0;\n\t\t\twhile(true) {\n";
					if ($this->isComplexType($property)) {
						$nvpDeserializeCode .= "\t\t\t\t" . 'if( PPUtils::array_match_key($map, $prefix."' . $property->name . '($i)") ) {' . "\n";
						$nvpDeserializeCode .= "\t\t\t\t\t" . '$newPrefix = $prefix."' . $property->name . '($i).";' . "\n";
						$nvpDeserializeCode .= "\t\t\t\t\t" . '$this->' . $property->validatedName . '[$i] = new ' . $property->validatedClass . '();' . "\n";
						$nvpDeserializeCode .= "\t\t\t\t\t" . '$this->' . $property->validatedName . '[$i]->init($map, $newPrefix);' . "\n";
						$nvpDeserializeCode .= "\t\t\t\t}\n";
						//$map, $newPrefix
					} else {
						//TODO: nvp deserialize for array of simple types

						$nvpDeserializeCode .= "\t\t\t\t\$mapKeyName = \$prefix.\"" . $property->name . '($i)" ;';
						$nvpDeserializeCode .= "\n\t\t\t\tif (PPUtils :: array_match_key(\$map, \$mapKeyName)) {\n";

						$nvpDeserializeCode .= "\t\t\t\t\t\$this->" . $property->validatedName . '[$i] = $map[$mapKeyName];';
						$nvpDeserializeCode .= "\n\t\t\t\t	}\n";
					}
					$nvpDeserializeCode .= "\t\t\t\telse break;\n\t\t\t\t$" . "i++;\n";
					$nvpDeserializeCode .= "\t\t\t}\n";

				} else {
					if ($property->minOccurs == 1) {
						$mandatoryParams[] = $property->name;
					}

					if ($this->isComplexType($property)) {
						// nested complex type
						$nvpDeserializeCode .= "\t\t\t" . 'if( PPUtils::array_match_key($map, $prefix."' . $property->name . '.") ) {' . "\n";
						$nvpDeserializeCode .= "\t\t\t\t" . '$newPrefix = $prefix ."' . $property->name . '.";' . "\n";
						$nvpDeserializeCode .= "\t\t\t\t\$this->" . $property->validatedName . ' = new ' . $property->validatedClass . '();' . "\n";
						$nvpDeserializeCode .= "\t\t\t\t\$this->" . $property->validatedName . '->init($map, $newPrefix);' . "\n";
						$nvpDeserializeCode .= "\t\t\t}\n";
					} else {

						$nvpDeserializeCode .= "\t\t\t\$mapKeyName =  \$prefix . '" . $property->name . "';\n";
						$nvpDeserializeCode .= "\t\t\tif(\$map != null && array_key_exists(\$mapKeyName, \$map)) {\n\t\t\t\t";
						$nvpDeserializeCode .= "\$this->" . $property->validatedName . ' = $map[$mapKeyName];';
						$nvpDeserializeCode .= "\n\t\t\t}\n";
					}
				}
			}
			$nvpDeserializeCode .= "\t\t}\n\t}\n";

		}
		return $nvpDeserializeCode;
	}

	private function _generateUtilityConstructors($class) {

		$mandatoryParams = array ();
		foreach ($class->members as $property) {
			if ($property->minOccurs == 1) {
				$mandatoryParams[] = $property->name;
			}
		}

		$return = "";
		if (count($mandatoryParams) > 0) {
			$return .= "\n\tpublic function __construct(";
			$args = array ();
			foreach ($mandatoryParams as $param) {
				$args[] = "\$$param = null";
			}

			$return .= implode(", ", $args) . ") {\n";
			foreach ($mandatoryParams as $param) {
				$return .= "\t\t\$this->$param  = \$$param;\n";
			}
			$return .= "\t}\n";
		}
		return $return;
	}
	/**
	 * Generates the PHP code for a WSDL message type class representation
	 *
	 * according to PHP naming conventions (e.g., "MY-VARIABLE").
	 * and could normally be retrieved by $myClass->{"MY-VARIABLE"}.  For
	 * convenience, however, this will be available as $myClass->MYVARIABLE.
	 *
	 * @param DOMElement $class the interpreted WSDL message type node
	 * @return string the php source code for the message type class
	 *
	 * @access private
	 * @todo Include any applicable annotation from WSDL
	 */
	private function _generateClass($class) {
		$return = "";
		$return .= '/**' . "\n";

		$return .= ' * ' . $class->name . "\n";
		if ($class->doc != null)
			$return .= GeneratorUtil :: formatDoc($class->doc);
		$return .= " */\n";

		$return .= "class " . $class->name;

		if ($class->extends != null) {
			$return .= " extends " . $class->extends;
		}
		$return .= " {\n";

		/*
		 * Add members from WSDL's fault message to the class
		* Under the NVP binding, any fault member is tacked to the
		* response data type. For a successful API call, these members
		* are empty.
		*/
		if ($class->isOutputType() && count($class->faults) > 0) {
			foreach ($class->faults as $fault) {
				foreach ($fault->members as $member) {
					$class->members[$member->name] = $member;
				}
			}
		}

		$return .= $this->_generateClassMembers($class);
		if ($class->isOutputType()) {
			//$return .= $this->_generateNVPDeserializerMethod($class);
		}
		if ($class->isInputType()) {
			//$return .= $this->_generateUtilityConstructors($class);
			//$return .= $this->_generateNVPSerializerMethod($class);
		}

		$return .= "}\n\n";
		return $return;
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
