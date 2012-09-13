<?php
interface IGenerator {
	
	public function setDescriptors($namespaces, $defaultNamespace, 
											$services, $dataTypes);
	public function generateService($classPrefix);
	public function generateModel($classPrefix);
															
}