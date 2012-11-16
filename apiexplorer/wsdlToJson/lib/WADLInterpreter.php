<?php

require_once 'AbstractSpecInterpreter.php';

class WADLInterpreterException extends Exception { }

class WADLInterpreter extends AbstractSpecInterpreter
{

	const DESCRIPTOR_XSL_FILE = "/wadl2meta.xsl";

	private $wadlLocation;

	public function __construct($options) {
		parent::__construct($options);
		$this->wadlLocation = $options['wadlLocation'];
	}

	protected function validateOptions($options) {
		parent::validateOptions($options);
		if(!array_key_exists("wadlLocation", $options)) {
			throw new WSDLInterpreterException("Invalid configuration");
		}
	}

	protected function _getAPIDescriptors() {


		$dom = new DOMDocument();
		$dom->load($this->wadlLocation);
		$dom = $this->_transformWSDL($dom, dirname(__FILE__) . self::DESCRIPTOR_XSL_FILE);

		$elements = array();
		foreach($dom->getElementsByTagName("element") as $elementDef) {
			$elements[$elementDef->getAttribute("name")] = $elementDef->getAttribute("type");
		}
		
		$classes = $dom->getElementsByTagName("classes");
		//XXX: Parsing item1		
		foreach($classes->item(1)->getElementsByTagName("class") as $class) {			
			$type = new DataType();
			$type->name = $class->getAttribute("name");
			foreach($class->getElementsByTagName("property") as $propDef) {
				$prop = new Property();
				$prop->name = $propDef->getAttribute("name");
				$prop->maxOccurs = $propDef->getAttribute("max");
				$prop->minOccurs = $propDef->getAttribute("min");
				if($propDef->getAttribute("type")) {
					$prop->class = $propDef->getAttribute("type");					
				} else if($propDef->getAttribute("ref") &&
						array_key_exists($propDef->getAttribute("ref"), $elements)) {
					$prop->class = $elements[$propDef->getAttribute("ref")];					
				}
				$type->members[] = $prop;
			}
			$this->_dataTypes["$type->package:$type->name"] = $type;				
		}

		$this->_services[0] = new Service();
		$this->_services[0]->name = "PayPalRest";
		$resources = $dom->getElementsByTagName("resources");
		$basePath = $resources->item(0)->getAttribute("base");

		foreach($resources->item(0)->getElementsByTagName("resource") as $resource) {			
			foreach($resource->getElementsByTagName("method") as $method) {
				$operation = new RestfulOperation();
				$operation->name = $resource->getAttribute("path");
				//TODO: Move preg replacement to Generator since it is iodocs specific				
				$operation->uri = preg_replace("(\{(.*)\})", ":\\1",  $resource->getAttribute("path"));
				$operation->name = $resource->getAttribute("path");
				$operation->httpMethod = $method->getAttribute("name");				
				if($operation->httpMethod == 'POST') {
					$req = $method->getElementsByTagName("request")->item(0);
					$representation = $req->getElementsByTagName("representation")->item(0);
					$operation->requestContentType = strtolower($representation->getAttribute("mediaType"));
					$reqElement = $representation->getAttribute("element");
					if($reqElement) {						
						$operation->input[$reqElement] =
							$this->_dataTypes[":" . $elements[$reqElement]]; //TODO: Get element package
					}
				}				
				$this->_services[0]->operations[] = $operation;
			}
			//TODO: Check if there are method specific params
			foreach($resource->getElementsByTagName("param") as $param) {
				$operation->input[$param->getAttribute("name")] = $this->_processParameter($param);
			}				
		}
	}

	private function _processParameter($param) {
		$paramType = $param->getAttribute("type");
		if(array_key_exists($paramType, $this->_dataTypes)) {
			return $this->_dataTypes[$paramType];
		} else if (substr($paramType, 0, 3) == "xs:") {
			return substr($paramType, 3);
		}
	}
	/**
	 * Transforms WSDL file into an intermediate xsl
	 * for easier parsing
	 *
	 * @param string $xslFile
	 * @throws WSDLInterpreterException
	 */
	private function _transformWSDL($dom, $xslFile, $debug=false) {
		try {
			$xslDom = new DOMDocument();
			$xslDom->load($xslFile);
			$xsl = new XSLTProcessor();
			$xsl->importStyleSheet($xslDom);
			if($debug) {
				echo $xsl->transformToXml($dom);
			} else {
				return $xsl->transformToDoc($dom);
			}
		} catch (Exception $e) {
			throw new WSDLInterpreterException("Error interpreting WSDL document (".$e->getMessage().")");
		}
	}

}