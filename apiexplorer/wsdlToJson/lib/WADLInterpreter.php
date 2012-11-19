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

		$namespaces = array();
		foreach($dom->getElementsByTagName("namespace") as $ns) {
			$namespaces[$ns->getAttribute("name")] = $ns->nodeValue;
		}
		$elements = array();
		foreach($dom->getElementsByTagName("element") as $elementDef) {
			if($elementDef->hasAttribute("package")){
				if(in_array($elementDef->getAttribute("package"), $namespaces)) {			
					$elementDef->setAttribute("package", array_search($elementDef->getAttribute("package"), $namespaces) );
				}
				$k = $elementDef->getAttribute("package") . ":" . $elementDef->getAttribute("name");
			} else {
				$k = $elementDef->getAttribute("name");
			}
			$elements[$k] = $elementDef->getAttribute("type");
		}
		
		$classes = $dom->getElementsByTagName("classes");
		//XXX: Parsing item1		
		foreach($classes->item(1)->getElementsByTagName("class") as $class) {			
			$type = new DataType();
			$type->name = $class->getAttribute("name");
			$type->package = in_array($class->getAttribute("package"), $namespaces) ? 
				array_search($class->getAttribute("package"), $namespaces) : $class->getAttribute("package");
			foreach($class->getElementsByTagName("property") as $propDef) {
				$prop = new Property();
				$prop->name = $propDef->getAttribute("name");
				$prop->package = (isset($prop->package) && trim($prop->package) != "") ? $propDef->getAttribute("package") : $type->package;
				$prop->maxOccurs = $propDef->getAttribute("max");				
				$prop->minOccurs = $propDef->getAttribute("min");
				if($propDef->hasAttribute("documentation"))
					$prop->doc = $propDef->getAttribute("documentation");
				
				if($propDef->getAttribute("type")) {
					$prop->class = $propDef->getAttribute("type");					
				} else if($propDef->getAttribute("ref") &&
						array_key_exists($prop->package . ":" . $propDef->getAttribute("ref"), $elements)) {
					$prop->class = $elements[$prop->package . ":" . $propDef->getAttribute("ref")];										
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
						//TODO: Get element package
						$operation->input[$reqElement] =
							new Parameter($reqElement, $this->_dataTypes[$reqElement], true, TYPE_IN);						
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
			$type = $this->_dataTypes[$paramType];
		} else if (substr($paramType, 0, 3) == "xs:") {
			$type = substr($paramType, 3);
		}
		return new Parameter($param->getAttribute("name"), $type, true, TYPE_IN);
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
				exit;
			} else {
				return $xsl->transformToDoc($dom);
			}
		} catch (Exception $e) {
			throw new WSDLInterpreterException("Error interpreting WSDL document (".$e->getMessage().")");
		}
	}

}