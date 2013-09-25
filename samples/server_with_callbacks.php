<?php

require_once '../lib/nusoap.php';

$myNamespace = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';
$myNamespace = $myNamespace . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];


$server = new soap_server;
$server->soap_defencoding = 'UTF-8';
$server->decode_utf8 = false;

$serviceName = 'NuService';

$server->configureWSDL($serviceName, $myNamespace);
$server->wsdl->bindings[$serviceName . 'Binding']['portType'] = $serviceName;
// ==== WSDL TYPES DECLARATION ==============================================
// ---- Entities/Address -------------------------------------------------------------
$server->wsdl->addComplexType(
        'Address', 'complexType', 'struct', 'all', '', array(
    'AddressLine1' => array('type' => 'xsd:string'),
    'AddressLine2' => array('type' => 'xsd:string'),
    'AddressType' => array('type' => 'xsd:string'),
    'City' => array('type' => 'xsd:string'),
    'CountryCode' => array('type' => 'xsd:string'),
    'EmailAddress' => array('type' => 'xsd:string'),
    'FirstName' => array('type' => 'xsd:string'),
    'LastName' => array('type' => 'xsd:string'),
    'PhoneNumber' => array('type' => 'xsd:string'),
    'PostalCode' => array('type' => 'xsd:string'),
    'StateCode' => array('type' => 'xsd:string'),
        )
);


// ==== WSDL METHODS REGISTRATION ===========================================

$server->register(array(
    "name" => 'AddressReceive',
    "handler" => function ($address) {
        return $address;
    }), array('address' => 'tns:Address'), array('return' => 'tns:Address'), $myNamespace);


// ==== PROCESS REQUEST =====================================================
$server->service(isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '');
exit();