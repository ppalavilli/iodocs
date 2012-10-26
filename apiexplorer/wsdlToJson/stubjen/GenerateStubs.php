<?php
$libPath = "lib";
set_include_path(get_include_path() . PATH_SEPARATOR . $libPath);
set_include_path(get_include_path() . PATH_SEPARATOR . "config");


$options = parse_ini_file("config/generator.ini", true);
// Defaults:
$command = null;
$path = false;


if(1){
	$descType = "WADL";	
} else {
	$descType = "WSDL";
}

$interpreterClass = "${descType}Interpreter";
require_once "$interpreterClass.php";
foreach (glob("$libPath/generator/*.php") as $filename) {
	require_once $filename;
}

//echo "Generating Stubs... \n" ;
try {
	$interpreter = new $interpreterClass($options);
	$interpreter->generate();
//	echo "Stubs have been generated.\n";			
} catch (Exception $ex) {
	echo "Caught exception: " . $ex->getMessage() . "\n";
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