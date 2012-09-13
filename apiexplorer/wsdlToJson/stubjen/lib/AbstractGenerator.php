<?php
abstract class AbstractGenerator {

	const WORKING_DIR_PERMS = "0764";
	/**
	 * Directory where output PHP files must be written to
	 * @var string
	 */
	protected $_outputDirectory;

	protected $_options;
	
	protected $_services;

	protected $_dataTypes = array();

	/**
	 * Namespace definitions as appearing in WSDL
	 * @var array
	 */
	protected $_nameSpaces;

	/**
	 * The default namespace
	 * @var string
	 */
	protected $_defaultNamespace;


	public function __construct($outputDir, $options = array()) {
		$this->_outputDirectory = $outputDir;
		$this->_options = $options;
		
		if(!file_exists($outputDir)) {
			mkdir($outputDir, 0666, true);
		}
	}

	public function setDescriptors($namespaces, $defaultNamespace, $services, $dataTypes) {
		$this->_nameSpaces = $namespaces;
		$this->_defaultNamespace = $defaultNamespace;
		$this->_services = $services;
		$this->_dataTypes = $dataTypes;
		$this->_validateNamingConventions();
	}

	protected abstract function _validateNamingConventions();
	public abstract function generateService($classPrefix);
	public abstract function generateModel($classPrefix);

	/**
	 * Return type descriptor for the property object
	 */
	public function getTypeDescriptor($prop) {
		$key = $prop->package . ":" . $prop->class;
		if(array_key_exists($key, $this->_dataTypes))
		return $this->_dataTypes[$key];
		return null;
	}
	
	/**
	 * 
	 * Returns array of operations to skip during generation
	 * @return array
	 */
	protected function getOperationsToSkip() {
		if(!isset($this->_options["skipOperations"]) || !is_file($this->_options["skipOperations"]))
			return array();
		return file($this->_options["skipOperations"], FILE_IGNORE_NEW_LINES);
	}
}