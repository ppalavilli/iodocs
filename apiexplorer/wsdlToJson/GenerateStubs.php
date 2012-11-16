<?php
$libPath = "lib";
set_include_path(get_include_path() . PATH_SEPARATOR . $libPath . PATH_SEPARATOR . "config");

function checkPrerequisites() {
   $req = array('xsl');
   $missing = array();
   foreach($req as $ext) {
   	  if( !extension_loaded($ext) ) {
	      $missing[] = $ext;
	  }
   }
   if(count($missing) > 0) {
	  die("Required extensions missing. Please install/enable " . implode(", ", $missing) . " extension(s)");
   }
}


checkPrerequisites();
$options = parse_ini_file("config/generator.ini", true);
if(isset($options['wadlLocation'])){
	$descType = "WADL";	
} else {
	$descType = "WSDL";
}

$interpreterClass = "${descType}Interpreter";
require_once "$interpreterClass.php";
$requiredClasses = array_merge(glob("$libPath/generator/*.php"), glob("$libPath/processor/*.php"));
foreach ($requiredClasses as $filename) {
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
