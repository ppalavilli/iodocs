<?php
require_once 'APIDescriptors.php';

class GeneratorUtil {

	/**
	 * Check if $prop is a collection item
	 * @param Property $prop
	 */
	public static function isCollectionItem($prop) {
		return ( $prop->maxOccurs > 1 || strstr($prop->maxOccurs, "unbounded") );
	}

	/**
	 * Convert first character of string to lowercase
	 * @param string $string
	 */
	public static function lcFirst($string) {
		$string{0} = strtolower($string{0});
		return $string;
	}

	/**
	 * Convert first character of string to uppercase
	 * @param string $string
	 */
	public static function ucFirst($string) {
		$string{0} = strtoupper($string{0});
		return $string;
	}

	/**
	 * Format a documentation string
	 * @param string $docString
	 * @param string $prefix
	 */
	public static function formatDoc($docString, $prefix="") {
		$docString = preg_replace('/^\s+$/m', "", $docString);
		$docString = preg_replace('/^\t+/m', "  ", $docString);
		$docString = preg_replace('/^\s+/m', "$prefix * ", $docString);

		return $docString;
	}

	/**
	 *
	 * Returns true if atleast one element in needle is contained in haystack
	 * @param array $haystack
	 * @param array $needle
	 */
	public static function strstr_array($haystack, $needle) {
		if( !is_array($needle) )
		return strstr($haystack, $needle);
		foreach($needle as $n) {
			if( ($ret = strstr($haystack, $n)) )
			return $ret;
		}
		return false;
	}

	/**
	 *
	 * Returns packagename for folder creation
	 * @param string $namespace
	 */

	public function getPackageNameFromNS($namespace) {

		if( array_key_exists($namespace, $this->_nameSpaces))
		$namespace = $this->_nameSpaces[$namespace];
			
		$packagePrefix = array('http://', 'urn://');
		if( GeneratorUtil::strstr_array( $namespace, $packagePrefix) === false ) {
			return $namespace;
		}
			
		$namespace = str_replace($packagePrefix, '', $namespace);
		$namespaceParts = explode("/", $namespace);

		$firstPart = explode(".", array_shift($namespaceParts));
		if(in_array($firstPart[0], array('www')))
		array_shift($firstPart);
		$ret = implode( ".", array_reverse($firstPart) )
		. "." . implode(".", $namespaceParts);
			
		return $ret;
	}

}
?>