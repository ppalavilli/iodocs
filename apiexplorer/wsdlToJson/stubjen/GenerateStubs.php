<?php
$libPath = "lib";
set_include_path(get_include_path() . PATH_SEPARATOR . $libPath);
set_include_path(get_include_path() . PATH_SEPARATOR . "config");
require_once 'WSDLInterpreter.php';
foreach (glob("$libPath/generator/*.php") as $filename) {
    require_once $filename;
}

$options = parse_ini_file("config/generator.ini", true);
// Defaults:
$command = null;
$path = false;

//echo "Generating Stubs... \n" ;
try {
	$wsdlInterpreter = new WSDLInterpreter($options);
	$wsdlInterpreter->generate();
//	echo "Stubs have been generated.\n";			
} catch (WSDLInterpreterException $ex) {
	echo "Caught exception: " . $ex->getMessage() . "\n";
} catch(Exception $ex) {
    echo $ex->getMessage() . "\n";
}


/**
 * Print usage information.
 */
function usage() {
    echo <<<USAGE
Usage: GenerateStubs 

USAGE;
    exit;
}

?>