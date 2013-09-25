<?php

require_once '../lib/nusoap.php';
nusoap_base::setGlobalDebugLevel(0);

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';
$myNamespace = $protocol . $_SERVER['HTTP_HOST'] . str_replace("client_for_server_with_callbacks", "server_with_callbacks", $_SERVER['SCRIPT_NAME']) . '?wsdl';

$client->soap_defencoding = 'utf-8';
$client = new nusoap_client($myNamespace);

$result = $client->call("AddressReceive", array(
    "address" => array(
        'AddressLine1' => "qeqwe",
        'AddressLine2' => "qweqwe",
        'AddressType' => "qweqwe",
        'City' => "qweqwe",
        'CountryCode' => "qweqwe",
        'EmailAddress' => "qweqwe",
        'FirstName' => "qweqwe",
        'LastName' => "qweqwe",
        'PhoneNumber' => "qweqwe",
        'PostalCode' => "qweqwe",
        'StateCode' => "qweqwe",
    )));

var_dump($result);