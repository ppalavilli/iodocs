<?php
/**
 * Interprets WSDL documents for the purposes of PHP 5 object creation
 *
 * The WSDLInterpreter package is used for the interpretation of a WSDL
 * document into PHP classes that represent the messages using inheritance
 * and typing as defined by the WSDL rather than SoapClient's limited
 * interpretation.  PHP classes are also created for each service that
 * represent the methods with any appropriate overloading and strict
 * variable type checking as defined by the WSDL.
 *
 * PHP version 5
 *
 * LICENSE: This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category    WebServices
 * @package     WSDLInterpreter
 * @author      Kevin Vaughan kevin@kevinvaughan.com
 * @copyright   2007 Kevin Vaughan
 * @license     http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 *
 */

/**
 * A lightweight wrapper of Exception to provide basic package specific
 * unrecoverable program states.
 *
 * @category WebServices
 * @package WSDLInterpreter
 */
class WSDLInterpreterException extends Exception { }

/**
 * The main class for handling WSDL interpretation
 *
 * The WSDLInterpreter is utilized for the parsing of a WSDL document for rapid
 * and flexible use within the context of PHP 5 scripts.
 *
 * Example Usage:
 * <code>
 * require_once 'WSDLInterpreter.php';
 * $wsdlInterpreter = new WSDLInterpreter($options);
 * $wsdlInterpreter->generate();
 * </code>
 *
 * @version 1.0.0
 * @category WebServices
 * @package WSDLInterpreter
 */
class WSDLInterpreter
{


	const DESCRIPTOR_XSL_FILE = "/wsdl2meta.xsl";

	/**
	 * The WSDL document's URI
	 * @var string
	 * @access private
	 */
	private $_wsdl = null;

	/**
	 * DOM document representation of the wsdl and its translation
	 * @var DOMDocument
	 * @access private
	 */
	private $_dom = null;

	/**
	 *
	 * DOMXPath representation of the transformed WSDL
	 * @var DOMXPath
	 */
	private $_xpath = null;

	/**
	 * Array of generators for different language bindings
	 * @var array
	 * @access private
	 */
	private $_generators = array();

	private $_verbose = false;

	private $_defaultNamespace;
	private $_nameSpaces = array();
	private $_services;
	private $_dataTypes = array();
	private $_typePackages = array();


	/**
	 * Parses the target wsdl and loads the interpretation into object members
	 *
	 * @param array $options  Generator configuration options
	 * @throws WSDLInterpreterException Container for all WSDL interpretation problems
	 */
	public function __construct($options)
	{
		$this->validateOptions($options);
		$this->_wsdl = $options['wsdlLocation'];
		foreach($options["generator"] as $generator) {
			$outputDir = $options["outputDir"] . DIRECTORY_SEPARATOR . strstr($generator, "Generator", true); //TODO: Yuck
			$this->addGenerator(new $generator($outputDir, $options["generatorOptions"]));
		}
		$this->_verbose = $options["verbose"];

		$this->_dom = new DOMDocument();
		if( !@$this->_dom->load($this->_wsdl) ) {
			throw new WSDLInterpreterException("Error loading WSDL document ($wsdl)");
		}

		/**
		* Handle external xsd - Load them separately and replace external xsd imports in
		* main wsdl with inline schema. Note: We do not write the modified wsdl to file.
		* This schema transformation happens only on the DOMDocument object
		*/
		$xpath = new DOMXPath($this->_dom);
		$query = "//*[local-name()='import' and namespace-uri()='http://www.w3.org/2001/XMLSchema']";
		$entries = $xpath->query($query);
		foreach ($entries as $entry) {
			$parent = $entry->parentNode;
			$xsd = new DOMDocument();
			$schemaLocation = $entry->getAttribute("schemaLocation");
			if($schemaLocation == null || $schemaLocation == "") {
				continue;
			}
			$result = @$xsd->load(dirname($this->_wsdl) . "/" . $schemaLocation,
			LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOENT|LIBXML_XINCLUDE);
			if ($result) {
				$newNode = $this->_dom->importNode($xsd->documentElement, true);
				$parent->insertBefore($newNode, $entry);
				$parent->removeChild($entry);
			} else {
				throw new WSDLInterpreterException("Could not get schema file: " . dirname($this->_wsdl) . "/" . $schemaLocation);
			}
		}

		if($this->_verbose)
			$this->log("Loaded wsdl document " . $this->_wsdl);
	}

	private function validateOptions($options) {
		if(!array_key_exists("wsdlLocation", $options) ||
			!array_key_exists("generator", $options) ||	!is_array($options["generator"]) || count($options["generator"]) == 0) {
			throw new WSDLInterpreterException("Invalid configuration");
		}
	}

	public function setVerbose($verbose) {
		$this->_verbose = $verbose;
	}

	/**
	 * Transforms WSDL file into an intermediate xsl
	 * for easier parsing
	 *
	 * @param string $xslFile
	 * @throws WSDLInterpreterException
	 */
	private function _transformWSDL($xslFile, $debug=false) {
		try {
			$xsl = new XSLTProcessor();
			$xslDom = new DOMDocument();
			$xslDom->load($xslFile);
			$xsl->importStyleSheet($xslDom);
			if($debug) {
				var_dump($xsl->transformToXml($this->_dom));
			} else {
				$this->_dom = $xsl->transformToDoc($this->_dom);
			}
			$this->_dom->formatOutput = true;
			$this->_xpath = new DOMXPath($this->_dom);
		} catch (Exception $e) {
			throw new WSDLInterpreterException("Error interpreting WSDL document (".$e->getMessage().")");
		}
	}

	/**
	 * Setter for adding new generator
	 *
	 * @param IGenerator $generator
	 */
	public function addGenerator($generator) {
		if( !$generator instanceof AbstractGenerator )
		throw new WSDLInterpreterException("Generator classes must extend the AbstractGenerator class");
		$this->_generators[] = $generator;
	}


	/**
	 * Saves the source code that has been loaded to a target directory.
	 * The actual task of generating source code in different languages
	 * is handled by the generator classes
	 *
	 * Services will be saved by their validated name, and classes will be included
	 * with each service file so that they can be utilized independently.
	 *
	 * @param string $outputDirectory the destination directory for the source code
	 * @return array array of source code files that were written out
	 * @throws WSDLInterpreterException problem in writing out service sources
	 * @access public
	 * @todo Add split file options for more efficient output
	 */
	public function generate($classPrefix = "") {

		$this->_getAPIDescriptors();
		foreach($this->_generators as $generator) {
			

			$generator->setDescriptors($this->_nameSpaces, $this->_defaultNamespace, $this->_services, $this->_dataTypes);
			if ($this->_verbose);
				$this->log(get_class($generator) .  ": Generating service wrapper");
			$generator->generateService($classPrefix);
			if ($this->_verbose);
				$this->log(get_class($generator) .  ": Generating API classes");
			$generator->generateModel($classPrefix);
		}
	}


	/**
	 *
	 * Navigate the DOMDocument representing the transformed WSDL
	 * to generate API Descritptor objects.
	 *
	 * The code generator classes know only about the API Descriptors
	 * and have no knowledge of WSDL/DOM trees
	 */
	private function _getAPIDescriptors() {

		$this->_transformWSDL( dirname(__FILE__) . self::DESCRIPTOR_XSL_FILE );
		if($this->_verbose)
			$this->log("XSL transformation complete");
		$elements = $this->_xpath->query("//namespace");
		$defaultNS = array('xml', 'mime', 'soap', 'xs', 'http', 'wsdl');
		foreach ($elements as $elem) {
			$nsName = $elem->getAttribute("name");
			if( !in_array($nsName, $defaultNS) && !array_key_exists($nsName, $this->_nameSpaces) ) {
				$this->_nameSpaces[$nsName] = $elem->textContent;
			}
		}

		$elements = $this->_xpath->query("//attribute[@name='targetNamespace']");
		if($elements->length > 0)
		$this->_defaultNamespace = $elements->item(0)->textContent;


		foreach($this->_dom->getElementsByTagName("package") as $package) {

			//namespaces
			$namespacePrefixes = array();
			foreach($package->getElementsByTagName("namespace") as $ns) {
				$namespacePrefixes[$ns->getAttribute("name")] = $ns->textContent;
			}
			$elements=$this->_dom->getElementsByTagName("elements");
			foreach($package->getElementsByTagName("class") as $class) {
				//classes
				$d = new DataType();
				$d->name = $class->getAttribute("name");
				//assigning superclass and its package
				foreach($class->getElementsByTagName("extends") as $extends){
					$d->extends=$extends->getAttribute("name");
					$extendsPackage=$extends->getAttribute("package");
					$d->extendsPackage=$namespacePrefixes[$extendsPackage];
				}
				//package
				if($class->getAttribute("package")!=NULL)
				$d->package = $class->getAttribute("package");
				else
				$d->package = $this->_defaultNamespace;
				$docNode = $class->getElementsByTagName("documentation");
				if($docNode && $docNode->length > 0)
				$d->doc = $docNode->item(0)->textContent;


				if($elements->length>0){
					$element=$elements->item(0)->getElementsByTagName("element");
					//properties
					foreach ($class->getElementsByTagName('property') as $prop) {
						$p = new Property();
						$p->name = $prop->getAttribute("name");
						$p->maxOccurs = $prop->getAttribute("max");
						$p->minOccurs = $prop->getAttribute("min");
						$pkg = $prop->getAttribute("package");
						$type=$prop->getAttribute("type");
						if($type==null){
							$p->isRef=true;
						}
						//check for attrib parameter
						if($prop->getAttribute("attrib")){
							$p->isAttribute=true;
							$d->isContainsAttribute=true;
						}
						if($prop->getAttribute("value")){
							$p->isValue=true;
						}
						//assigning types and packages
						if($type!=null){
							if( $prop->getAttribute("simpletype") )
							$p->isSimpleType = true;
							else
							$p->package=$namespacePrefixes[$pkg];
							foreach ($element as $value) {
								if($type==$value->getAttribute("name")){
									if(strstr($value->getAttribute("package"),"xs")){
										$p->class=$value->getAttribute("type");
										break;
									}
								}
								$p->class=$type;
							}
						}else{
							$boolean=false;
							foreach ($element as $value) {
								if($p->name==$value->getAttribute("name"))
								{
									if($prop->getAttribute("simpletype")){
										$p->isSimpleType = true;
										$boolean=true;
									}
									else{
										$p->package=$namespacePrefixes[$pkg];
										$boolean=true;
									}
									$p->class = $value->getAttribute("type");
								}if($boolean==true){
									break;
								}
							}
						}
						$docNode = $prop->getAttribute("documentation");
						if($docNode)
						$p->doc = $docNode;
						$d->members[$p->name] = $p;
					}
				}

				$this->_dataTypes["$d->package:$d->name"] = $d;
			}
			//enums
			foreach($elements->item(0)->getElementsByTagName("enum") as $enum) {
				$e = new EnumType();
				$e->name = $enum->getAttribute("name");
				$e->package = $enum->getAttribute("package");
				foreach($enum->getElementsByTagName("value") as $v)
				$e->values[] = $v->textContent;
				$docNode = $enum->getElementsByTagName("documentation");
				if($docNode && $docNode->length > 0)
				$e->doc = $docNode->item(0)->textContent;

				$this->_dataTypes["$e->package:$e->name"] = $e;
			}
		}
		$this->log("Discovered data types");

		/* Navigate the DOMTree starting from
		 * 1. service defintions,
		 * 2. then to operations defined in that service,
		 * 3. input, output & fault messages defined by the operation, and
		 * 4. data types used by the mesages.
		 */
		foreach ($this->_dom->getElementsByTagName("service") as $service) {

			$s = new Service();
			$s->name = $service->getAttribute("name");

			$s->package = $service->getElementsByTagName("binding")->item(0)->getAttribute("type");

			$serviceAttribs = $this->_xpath->query("//attribute");
			foreach($serviceAttribs as $attrib) {
				if( strstr($attrib->getAttribute('name'), "ns:version") )
				$s->version = $attrib->textContent;
			}
			$this->_services[] = $s;


			//operations
			foreach ($service->getElementsByTagName("function") as $function) {
				$o = new Operation();
				$o->name = $function->getAttribute("name");
				$docNode = $function->getElementsByTagName("documentation");
				if($docNode && $docNode->length > 0)
				$o->doc = $docNode->item(0)->textContent;
				$s->operations[] = $o;
				//requests
				$parameters = $function->getElementsByTagName("parameters");
				if ($parameters->length > 0) {
					$parameterList = $parameters->item(0)->getElementsByTagName("variable");

					foreach ($parameterList as $variable) {
						// can be a simple type too
						$fqn = 	 $this->_nameSpaces[$variable->getAttribute("package")] . ":" . $variable->getAttribute("type");
						$p = $this->_dataTypes[$fqn];
						$o->input[$variable->getAttribute("name")] = $p;
						$this->discoverTypes($p, TYPE_IN);
					}
				}


				$soapFaults = array();
				//faults
				$faults = $function->getElementsByTagName("throws");
				if ($faults->length > 0) {
					$faultList = $faults->item(0)->getElementsByTagName("variable");
					foreach ($faultList as $variable) {
						$faultName = $variable->getAttribute("type");
						$fqn = 	 $this->_nameSpaces[$variable->getAttribute("package")] . ":" . $faultName;
						if(array_key_exists($fqn, $this->_dataTypes)) {
							$p = $this->_dataTypes[$fqn];
						} else {
							$p = new DataType();
							$p->name = $faultName;
							$p->validatedName = $faultName;
							$docNode = $variable->getElementsByTagName("documentation");
							if($docNode && $docNode->length > 0)
							$p->doc = $docNode->item(0)->textContent;
							$p->setInOutType(TYPE_OUT);
							$this->discoverTypes($p, TYPE_OUT);
						}
						$o->fault = $p;
						$soapFaults[$p->name] = $p;

					}
				}
				//responses
				$out = $function->getElementsByTagName("returns");
				if ($out->length > 0) {
					$outList = $out->item(0)->getElementsByTagName("variable");
					$elements=$this->_dom->getElementsByTagName("elements");
					foreach ($outList as $variable) {
						if($elements->length>0){
							$element=$elements->item(0)->getElementsByTagName("element");
							foreach ($element as $value) {
								if($value->getAttribute("name")==$variable->getAttribute("name")){
									$type=$value->getAttribute("type");
									break;
								}
								else{
									continue;
								}
							}
						}

						$fqn = 	 $this->_nameSpaces[$variable->getAttribute("package")] . ":" . $type;
						$p = $this->_dataTypes[$fqn];
						$p->faults = array_unique($soapFaults);

						// add members from WSDL's fault message to the class
						// since in the NVP/JSON bindings any data that is
						// sent as a SOAPFault in the SOAP binding
						// is just tacked along with the response data
						foreach( $p->faults as $fault ) {
							$this->discoverTypes($fault, TYPE_OUT);
							foreach($fault->members as $member) {
								$p->members[$member->name] = $member;
							}
						}
						$o->output = $p;
						$this->discoverTypes($p, TYPE_OUT);
					}
				}
			}
		}
		if($this->_verbose)
			$this->log("Got API Descriptors");
	}


	/**
	 * Recursively discover types that are actually used by the service methods.
	 *
	 * This will mean that we do not generate code files for types
	 * that are declared but not used in any operation
	 */
	private function discoverTypes($type, $isOutputType ) {

		if( array_key_exists("$type->package:$type->name", $this->_dataTypes )) {
			$type->isUsed = true;
			if($type instanceof EnumType)
			return;
			$type->setInOutType($isOutputType);
			$extends=$type->extends;
			//check for superclasses
			if($extends!=null){
				$fqn = $type->extendsPackage . ":" . $extends;
				$memType = $this->_dataTypes[$fqn];
				$this->discoverTypes($memType, $isOutputType);
			}
			//check for members
			foreach($type->members as $m) {
				if($this->isComplexType($m->class)) {
					$fqn = $m->package . ":" . $m->class;
					$memType = $this->_dataTypes[$fqn];
					$this->discoverTypes($memType, $isOutputType);

				}

			}
		}
	}

	private function isComplexType($typeName) {
		$expr = "*//class[@name='$typeName'] | *//enum[@name='$typeName']";
		$elements = $this->_xpath->query($expr);
		return (!is_null($elements) && $elements->length > 0);
	}

	private function log($message) {
		echo date("H:i:s") . " -  " . $message . "\n";
	}
}

?>
