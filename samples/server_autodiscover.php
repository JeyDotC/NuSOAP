<?php

require_once '../lib/nusoap.php';
require_once '../lib/class.soap_server_autodiscover.php';

$myNamespace = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';
$myNamespace = $myNamespace . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

class MyModel {

    /**
     *
     * @var string
     */
    public $value;

    /**
     *
     * @var int
     */
    public $secondValue;

}

class ComplexModel {

    /**
     *
     * @var int
     */
    public $value;

    /**
     *
     * @var MyModel
     */
    public $myModel;

}

class MyService {

    /**
     * Short descrition.
     * 
     * Long Description with a lot of text and paragraphs-
     * 
     * <p>Lorem Ipsum dolor Sit amet</p>.
     * 
     * @param string $param
     * @param int $param2
     */
    function commonServiceMethod($param, $param2, $mixed) {
        return "$param-$param2-$mixed";
    }

    /**
     * 
     * @param MyModel $parameter1
     * @param ComplexModel $paremeter2
     * @return string
     */
    function myServiceMethod(MyModel $parameter1, ComplexModel $paremeter2) {
        return "Success";
    }

    //// Testing Array styles /////

    /**
     * 
     * @param string[] $param
     * @return \MyModel
     */
    function arrayMethod(array $param) {
        return new MyModel();
    }

    /**
     * 
     * @param array[\MyModel] $param
     * @return array[\MyModel]
     */
    function arrayMethod2($param) {
        return $param;
    }

}

$server = new nusoap_server_autodiscover($myNamespace, new MyService());

$server->generate()
        ->service(isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '');
exit();
