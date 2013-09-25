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

Next step will be to replace the 'magic return value' from nusoap_client to throw
an appropriate exception.

## Class files get included instead of copying the classes in a single file ##

## Removed Trailing ?> ##

Having a trailing `?>` in PHP files uses to cause problems with proxy generation
based libraries like [Go-AOP](https://github.com/lisachenko/go-aop-php/).

