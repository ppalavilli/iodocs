<?php
require_once 'PPAPIService.php';


class PPBaseService {

	private $serviceName;

   /*
    * Setters and getters for Third party authentication (Permission Services)
    */
	protected $accessToken;
	protected $tokenSecret;
	protected $lastRequest;
	protected $lastResponse;
	public $resHeader;
    public function getLastRequest() {
		return $this->lastRequest;
	}
    public function setLastRequest($lastRqst) {
		$this->lastRequest = $lastRqst;
	}
    public function getLastResponse() {
		return $this->lastResponse;
	}
    public function setLastResponse($lastRspns) {
		$this->lastResponse = $lastRspns;
	}

	public function getAccessToken() {
		return $this->accessToken;
	}
	public function setAccessToken($accessToken) {
		$this->accessToken = $accessToken;
	}
	public function getTokenSecret() {
		return $this->tokenSecret;
	}
	public function setTokenSecret($tokenSecret) {
		$this->tokenSecret = $tokenSecret;
	}

	public function __construct($serviceName) {
		$this->serviceName = $serviceName;
	}

	public function getServiceName() {
		return $this->serviceName;
	}
	public function getResHeader()
	{
		return $this->resHeader;
	}
	
	public function call($method, $requestObject, $apiUsername = null) {
		$params = $this->marshall($requestObject);
		$service = new PPAPIService();
		$service->setServiceName($this->serviceName);

		
		
		
		$res = $service->makeRequest($method, $params, $apiUsername ,$this->accessToken, $this->tokenSecret);
		$res = array_reverse($res);
		$this->lastResponse = $res[0];
		$this->resHeader = $res[1];
		$this->lastRequest = $service->params;
		return $this->lastResponse;
	}

    private function marshall($object) {
		$transformer = new PPObjectTransformer();
		return $transformer->toString($object);
	}
}
?>
