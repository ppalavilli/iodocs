<?php

/**
 * 
 * PHP proxy class for making API calls. Creates a NVP/SOAP payload based on Form POST 
 * This file is invoked by node.js.  
 */

define("DEFAULT_API_USERNAME", 'jb-us-seller_api1.paypal.com');
define("DEFAULT_API_PASSSWORD", 'WX4WTU3S8MY44S7F');
define("DEFAULT_API_SIGNATURE", 'AFcWxV21C7fd0v3bYYYRCpSSRl31A7yDhhsPUU2XhtMoZXsWHFxu-RWy');
define("DEFAULT_APPID", 'APP-80W284485P519543T');

$service = $_REQUEST['apiName'];
//$service = 'PayPalAPIs';
$operation = $_REQUEST['method'];
//$operation = 'SetExpressCheckout';

if(isset($_REQUEST['apiUserName']))
	$apiUserName = $_REQUEST['apiUserName'];
else
	$apiUserName = DEFAULT_API_USERNAME;
if(isset($_REQUEST['apiPassword']))
	$apiPassword = $_REQUEST['apiPassword'];
else
	$apiPassword = DEFAULT_API_PASSSWORD;
if(isset($_REQUEST['apiSignature']))
	$apiSignature = $_REQUEST['apiSignature'];
else
	$apiSignature = DEFAULT_API_SIGNATURE;

if(isset($_REQUEST['appId']))
	$appId = $_REQUEST['appId'];
else
	$appId = DEFAULT_APPID;

$arRemove = array('apiName', 'method', 'apiUserName', 'apiPassword', 'apiSignature', 'appId' );
$inputParams = array();
foreach($_REQUEST as $key => $val)
{
	$key = str_replace('_','.',$key);
	$inputParams[$key] = $val;
}
$filteredParamArr = queryFilter($inputParams, $arRemove);
//error_log(print_r($filteredParamArr, true));
 //$paramArr = test();
function test()
{
    $string = "SetExpressCheckoutReq.SetExpressCheckoutRequest.SetExpressCheckoutRequestDetails.ReturnURL=http://return&SetExpressCheckoutReq.SetExpressCheckoutRequest.SetExpressCheckoutRequestDetails.CancelURL=http://return&SetExpressCheckoutReq.SetExpressCheckoutRequest.SetExpressCheckoutRequestDetails.PaymentDetails(0).OrderTotal.currencyID=USD&SetExpressCheckoutReq.SetExpressCheckoutRequest.SetExpressCheckoutRequestDetails.PaymentDetails(0).OrderTotal.value=2&SetExpressCheckoutReq.SetExpressCheckoutRequest.SetExpressCheckoutRequestDetails.cpp-header-back-color=red";
	//$string = 'setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.orderTotal.currencyID=USD&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.orderTotal.value=1&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.returnURL=http://return&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.cancelURL=http://return&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.billingAddress.name=name&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.billingAddress.street1=street&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.billingAddress.cityName=san jose&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.billingAddress.stateOrProvince=CA&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.billingAddress.country=US';
	$string = explode('&', $string);
	foreach ($string as $tmpVar) {
		$tmp[] = explode('=',$tmpVar);
	}


	foreach ($tmp as $arrElement)
	{
		$paramArr[$arrElement[0]] = $arrElement[1];
	}


	foreach ($arr as $tmpVar) {
		$tmp[] = explode('=',$tmpVar);
	}
	foreach ($tmp as $arrElement)
	{
		$paramArr[$arrElement[0]] = $arrElement[1];
	}
	return $paramArr;
}
//$filteredParamArr = $paramArr;
if($service == 'PayPalAPIs')
{

	$file = file_get_contents('./PayPalAPIs.json');
	$digest = json_decode($file);
	$jsonArr = $digest->endpoints[0]->methods;
	foreach ($jsonArr as $method)
	{
		$jsonReq[] = $method->Parameters[0];
	}
	$mrg = array();    
    if(empty($filteredParamArr))
    {
        echo 'https://api.sandbox.paypal.com/2.0/#SEPERATOR#null#SEPERATOR#input parameters are not set#SEPERATOR#null#SEPERATOR#null';
        exit;
    }
    
	foreach ($filteredParamArr as $arrKey => $arrVal)
	{		
		$array = $arrVal;
		foreach(array_reverse(explode('.', $arrKey)) as $key)
		{
            $array = array($key => $array);
		}
		$mrg = array_merge_recursive($mrg,$array);
       
	}
	$i=0;
	foreach ($mrg as $req => $reqArray)
	{
		while($req != $jsonReq[$i]->Name)
		{
			$i++;
		}
		if($req == $jsonReq[$i]->Name)
		{
			$req = $req;
			$jsonType = $jsonReq[$i];
			$classVals[$req] = $reqArray;
		}
	}

	$objArray = buildType(array($jsonType), $classVals);

	$path = 'lib';
	set_include_path(get_include_path() . PATH_SEPARATOR . $path);
	require_once 'services/PayPalAPIInterfaceService/PayPalAPIInterfaceServiceService.php';
	require_once 'auth/PPSignatureCredential.php';
	require_once 'PPConfigManager.php';
	
	$credential = new PPSignatureCredential($apiUserName, $apiPassword, $apiSignature);
	$request = buildRequest($objArray[0]);

	$service = new PayPalAPIInterfaceServiceService();

	$resp = $service->$operation($request,$credential);
	
	$config = PPConfigManager::getInstance();
	$url = $config->get('service.EndPoint.PayPalAPI');
	$params = $service->getLastRequest();
	$response = $service->getLastResponse();
	$resHeader =  $service->getResHeader();
	$reqHeader = 'null';

} else {

	// Removing the top level data type from query param for HELIX APIs alone
	// E.g. 'payRequest.payKey' is converted to just 'payKey'
	$arr = array();
	foreach($filteredParamArr as $key => $val)
	{
		$index = strpos($key, '.', 0);
		$key = substr($key, $index+1);
		$arr[] = $key . '=' . $val;
	}
	$queryStr = implode('&', $arr);

	require_once 'lib/helix/lib/PPHttpConnection.php';
	require_once 'lib/helix/lib/PPUtils.php';
	$url = 'https://svcs.sandbox.paypal.com/'.$service.'/'.$operation;
	//$url = 'https://svcs.sandbox.paypal.com/AdaptivePayments/PaymentDetails';
	//$params ='requestEnvelope.errorLanguage=en_US&payKey=AP-5S482348KH512131U';
	$params = $queryStr;
	$headers = getPayPalHeaders($apiUserName, $apiPassword, $apiSignature, $appId);	
	$connection =  new PPHttpConnection();
	$res = $connection->execute($url, $params, $headers);
	$reqHeader = implode('&',$headers);
	$resHeader = $res[0];
	$response = $res[1];
}

echo $url.'#SEPERATOR#'.$params.'#SEPERATOR#'.$response.'#SEPERATOR#'.$resHeader.'#SEPERATOR#'.$reqHeader;

//$save = $url.'#SEPERATOR#'.$params.'#SEPERATOR#'.$response.'#SEPERATOR#'.$resHeader.'#SEPERATOR#'.$reqHeader;

function array_merge_recursive_new() {

    $arrays = func_get_args();
    $base = array_shift($arrays);

    foreach ($arrays as $array) {
        reset($base); //important
        while (list($key, $value) = @each($array)) {
            if (is_array($value) && @is_array($base[$key])) {
                $base[$key] = array_merge_recursive_new($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
    }

    return $base;
}

function getPayPalHeaders($apiUserName, $apiPassword, $apiSignature, $appId)
{
	$headers_arr[] = "X-PAYPAL-SECURITY-USERID: ".$apiUserName;
	$headers_arr[] = "X-PAYPAL-SECURITY-PASSWORD: ".$apiPassword ;
	$headers_arr[] = "X-PAYPAL-SECURITY-SIGNATURE: ".$apiSignature ;
	// Add other headers
	$headers_arr[] = "X-PAYPAL-APPLICATION-ID: ".$appId ;
	$headers_arr[] = "X-PAYPAL-REQUEST-DATA-FORMAT: NV" ;
	$headers_arr[] = "X-PAYPAL-RESPONSE-DATA-FORMAT: JSON" ;
	$headers_arr[] = "X-PAYPAL-DEVICE-IPADDRESS: " . PPUtils::getLocalIPAddress();
	$headers_arr[] = "X-PAYPAL-REQUEST-SOURCE: " . PPUtils::getRequestSource();
	$headers_arr[] = "X-PAYPAL-SANDBOX-EMAIL-ADDRESS: Platform.sdk.seller@gmail.com";
	return $headers_arr;
}

function queryFilter($arQuery, $arRemove)
{
	return $arQS = array_diff_key($arQuery, array_flip($arRemove));

}
function buildRequest($classDef )
{

	if(!empty($classDef) )
	{
		if(!empty($classDef['members']))
		{
			$newClass = $classDef['validatedType'];
			$req = new $newClass();		
			foreach ($classDef['members'] as $member)
			{
            //    if(array_key_exists(0, $member)) {
             //       $member = $member[0];
             //   }
				if(isset($member['value']))
				{
					if(isset($member['index'])) {
						$req->{$member['Name']}[$member['index']] = $member['value'];
					}
					else{
						$req->$member['Name'] = $member['value'];
					}
				}
				else if( $classDef['type'] == 'complex' &&  !empty($classDef['members']))
				{
                    if(isset($member['index'])) {
                        $req->{$member['Name']}[$member['index']] = buildRequest($member);
                    } else {
                        $req->$member['Name'] = buildRequest($member);
                    }
				}

			}
		}
	}

	return $req;


}
/**
 * @param jsonType - JSON SPEC read 
 * @param classVals posted values in nested format
 */
function buildType($jsonType, $classVals)
{
	$i=0;

	foreach ($classVals as $key => $val)
	{
        preg_match('/(.*)\((\d+)\)$/', $key, $matches);
        if(count($matches) == 3) {
            $idx = $matches[2];
            $key = $matches[1];
        }        
		$j=0;        
		while($key != $jsonType[$j]->Name)
		{       
			if( $jsonType[$j]->Name == null)
				break;
			$j++;

		}
		if($key == $jsonType[$j]->Name)
		{
			if(isset($jsonType[$j]->ValidatedName))
			{
				if($jsonType[$j]->Name != $jsonType[$j]->ValidatedName)
				{
					$objGenArray[$i]['Name'] = $jsonType[$j]->ValidatedName;
				}
				else
				{
					$objGenArray[$i]['Name'] = $key;
				}
			}
			else 
			{
				$objGenArray[$i]['Name'] = $key;
			}
			$objGenArray[$i]['type'] =  $jsonType[$j]->Type;
			$objGenArray[$i]['validatedType'] = $jsonType[$j]->ValidatedClass;
			if(!empty($jsonType[$j]->Members))
			{
                if(isset($idx)) {
                    $objGenArray[$i]['members'] = buildType($jsonType[$j]->Members[0], $val);
                    $objGenArray[$i]['index'] = $idx;
                } else {
                    $objGenArray[$i]['members'] = buildType($jsonType[$j]->Members, $val);
                }
			}
			else
			{
                if(isset($idx)) {
                	$objGenArray[$i]['value'] = $val;
                	$objGenArray[$i]['index'] = $idx;
                } else {
                    $objGenArray[$i]['value'] = $val;
                }
			}
			$i++;
		}




	}
	return $objGenArray;
}
?>
