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
$operation = $_REQUEST['method'];


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


$paramArr = queryFilter($inputParams, $arRemove);


/*$service = 'PayPalAPIs';
 $operation = 'SetExpressCheckout';
$paramArr = test();*/
function test()
{
	$string = 'setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.orderTotal.currencyID=USD&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.orderTotal.value=1&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.returnURL=http://return&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.cancelURL=http://return&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.billingAddress.name=name&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.billingAddress.street1=street&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.billingAddress.cityName=san jose&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.billingAddress.stateOrProvince=CA&setExpressCheckoutReq.setExpressCheckoutRequest.setExpressCheckoutRequestDetails.billingAddress.country=US';
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
	foreach ($paramArr as $arrKey => $arrVal)
	{
		$array = array();
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
		while(lcfirst($req) != $jsonReq[$i]->Name)
		{
			$i++;
		}
		if(lcfirst($req) == $jsonReq[$i]->Name)
		{
			$req = ucfirst($req);
			$jsonType = $jsonReq[$i];
			$classVals[$req] = $reqArray;
		}
	}

	$objArray = buildType(array($jsonType), $classVals);

	$path = 'lib';
	set_include_path(get_include_path() . PATH_SEPARATOR . $path);
	require_once('services/PayPalAPIInterfaceService/PayPalAPIInterfaceServiceService.php');

	$request = buildRequest($objArray[0]);



	$service = new PayPalAPIInterfaceServiceService();

	$resp = $service->$operation($request);

	$url = 'https://api.sandbox.paypal.com/2.0/';
	$params = $service->getLastRequest();
	$response = $service->getLastResponse();
	$resHeader =  $service->getResHeader();
	$reqHeader = 'null';

}

else
{

	foreach($get as $key => $val)
	{
		$index = strpos($key,'.',0);
		$key = substr($key, $index+1);
		$arr[] = $key.'='.$val;
	}
	$queryStr = implode('&',$arr);

	require_once 'lib/PPHttpConnection.php';
	require_once 'lib/PPUtils.php';
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
function buildRequest($className )
{

	if(!empty($className) )
	{
		if(!empty($className['members']))
		{
			$newClass = $className['validatedType'];
			$req = new $newClass();
		}
		if(!empty($className['members']))
		{
			$i= 0;
			foreach ($className['members'] as $member)
			{
				if($member['value'] != null)
				{
					$req->$member['Name'] = $member['value'];
					$i++;
				}
				else if( $className['type'] == 'complex' &&  !empty($className['members']))
				{
					$req->$member['Name'] = buildRequest($className['members'][$i]);
					$i++;
				}

			}
		}
	}

	return $req;


}
function buildType($jsonType, $classVals)
{
	$i=0;

	foreach ($classVals as $key => $val)
	{
		$j=0;
		while(lcfirst($key) != $jsonType[$j]->Name)
		{
			if( $jsonType[$j]->Name == null)
				break;
			$j++;

		}
		if(lcfirst($key) == $jsonType[$j]->Name)
		{
			$objGenArray[$i]['Name'] = ucfirst($key);
			$objGenArray[$i]['type'] =  $jsonType[$j]->Type;
			$objGenArray[$i]['validatedType'] = $jsonType[$j]->ValidatedClass;
			if(!empty($jsonType[$j]->Members))
			{
				$objGenArray[$i]['members'] = buildType($jsonType[$j]->Members, $val);
			}
			else
			{
				$objGenArray[$i]['value'] = $val;
			}
			//$objGenArray['value'] = $val;
			$i++;
		}




	}
	return $objGenArray;
}
?>
