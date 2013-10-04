NuSOAP is a rewrite of SOAPx4, provided by NuSphere and Dietrich Ayala.
It is a set of PHP classes - no PHP extensions required - that allow developers to create and consume web services based on SOAP 1.1, WSDL 1.1 and HTTP 1.0/1.1.

Here we are maintaining a version that is compatible with PHP 5.3.
All the API's remain unchanged so this code is backward compatible with previous NuSOAP versions.

## What is different in this version ##

### Support for closure registering on wsdl mode in nusoap_server ###

Now, instead of relying on the existence of a global function or sending an special
string like `"MyclassName->methodName"` or `"MyclassName::methodName"`, is possible
to send a [callable](http://php.net/manual/en/language.types.callable.php)
when registering service functions. 

To do so, instead of sending a string with the function name, send an array with
two elements, a function name (the name of the function in the WSDL) and a 
[callable](http://php.net/manual/en/language.types.callable.php) which will handle 
that function:

```php
<?php
$server = new soap_server;
$server->configureWSDL($serviceName, $myNamespace);

//WSDL configuration......

//Register your callable
$server->register(array(
    "name" => 'AddressReceive',
    "handler" => function ($address) {
        return $address;
    }), $in, $out, $myNamespace);
?>
```

The previous behavior still available.

### Added Autodiscover for service description! ###

Now is possible to describe your services as PHP classes, nusoap will generate the
WSDL for you. All you have to do is to document your class and make use of the new
nusoap_server_autodiscover class.

```php
<?php

// Describe your complex types:
class MyModel {

    /**
     * Document the class's properties, complex types are also allowed!
     * @var string
     */
    public $value;

    /**
     * The correct WSDL type will be determined using the documentation, if type is mixed
     * or is omitted autodiscover will fallback to `anyType`
     * @var int
     */
    public $secondValue;

}

// Describe your service
class MyService {

    /**
     * Document your methods, they will have the correct wsdl type based on
     * documentation.
     * @param MyModel $parameter1
     * @return string
     */
    function myServiceMethod(MyModel $parameter1) {
        return "Success";
    }

    /**
     * Arrays are mapped too! there are four types of array notation:
     * array[type], array<type>, type[] and array (the last one is the same as array[mixed], array<mixed> or mixed[])
     * 
     * @param array[\MyModel] $param
     * @return array<\MyModel>
     */
    function arrayMethod($param) {
        return $param;
    }
}

//Create a new nusoap_server_autodiscover object with the usual namespace parameter and
//a second parameter that will be your service, this class receives either an instance
//of your service, the service's class name or a ReflectionClass instance for your service.
$server = new nusoap_server_autodiscover($myNamespace, new MyService());

//Generate the service definition (this will also register your service to respond to the SOAP calls)
$server->generate()
//Do the magic.
        ->service(isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '');
exit();
?>
```

Refer to `samples/server_autodiscover.php` for a more advanced example.

#### TODO ####

* Check for mandatory and optional properties and parameters.

### Private and protected things are now actually private and protected ###

There were a lot of methods and attributes marked as private or protected but
being public (for retro-compatibility). Now those methods and properties have the
corresponding access levels.

#### WARNING ####

Doing this have caused a lot of issues as some methods and attributes marked as 'privated'
are being directly accessed outside the owning class or by one or more of its children classes.

There is probably more unresolved issues.

## Throw exceptions instead of die() ##

Now the classes that used `die()` as their failure mechanism throw exceptions instead.

## Throw exceptions instead of return magic values ##

Now there is no set/get error methods anymore as any code using them is inaccessible
due to exception throwing.

## Class files get included instead of copying the classes in a single file ##

## Removed Trailing ?> ##

Having a trailing `?>` in PHP files uses to cause problems with proxy generation
based libraries like [Go-AOP](https://github.com/lisachenko/go-aop-php/).

