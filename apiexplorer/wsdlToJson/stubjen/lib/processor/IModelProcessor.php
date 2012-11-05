<?php
interface IModelProcessor {
	function process($nameSpaces, $defaultNamespace, $services, $dataTypes);
}