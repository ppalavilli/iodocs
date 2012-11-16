<?php
abstract class AbstractSpecInterpreter {

	protected $_generators = array();
	
	/**
	 * Post processors that operate on the model data
	 * @var 
	 */
	protected $_modelPostProcessors = array();	

	protected $_verbose = false;
	
	protected $_defaultNamespace;
	protected $_nameSpaces = array();
	protected $_services;
	protected $_dataTypes = array();
	protected $_typePackages = array();
	
	public function __construct($options) {
		$this->validateOptions($options);
		
		foreach($options["modelprocessor"] as $processor) {
			$this->addModelProcessor(new $processor($options["processorOptions"]));
		}
		foreach($options["generator"] as $generator) {
			$outputDir = $options["outputDir"] . DIRECTORY_SEPARATOR . strstr($generator, "Generator", true); //TODO: Yuck
			$this->addGenerator(new $generator($outputDir, $options["generatorOptions"]));
		}	
		
		$this->_verbose = $options["verbose"];
	}

	protected abstract function _getAPIDescriptors();
	
	protected function validateOptions($options) {
		if(!array_key_exists("wsdlLocation", $options) ||
				!array_key_exists("generator", $options) ||	!is_array($options["generator"]) || count($options["generator"]) == 0) {
			throw new WSDLInterpreterException("Invalid configuration");
		}
	}
	
	protected function log($message) {
		echo date("H:i:s") . " -  " . $message . "\n";
	}
	
	/**
	 * Sets verbose mode
	 * 
	 * @param boolean $verbose
	 */
	public function setVerbose($verbose) {
		$this->_verbose = $verbose;
	}

	/**
	 * Setter for adding new generator
	 *
	 * @param IGenerator $generator
	 */
	public function addGenerator(AbstractGenerator $generator) {
		$this->_generators[] = $generator;
	}

	public function addModelProcessor(IModelProcessor $processor) {		
		$this->_modelPostProcessors[] = $processor;
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
		foreach($this->_modelPostProcessors as $proc) {
			$proc->process($this->_nameSpaces, $this->_defaultNamespace, $this->_services, $this->_dataTypes);
		}
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
	
}