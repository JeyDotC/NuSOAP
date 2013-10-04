<?php

/**
 * Description of class
 *
 * @author jguevara
 */
class nusoap_server_autodiscover extends soap_server {

    private $uri;

    /**
     *
     * @var ReflectionClass
     */
    private $class;
    private $serviceInstance;
    private $name;
    private $mappedComplexTypes = array();
    private $mappedArrays = array();
    private $knownPrimitiveTypes = array(
        "int" => "xsd:int",
        "integer" => "xsd:int",
        "float" => "xsd:float",
        "double" => "xsd:double",
        "string" => "xsd:string",
        "bool" => "xsd:bool",
        "boolean" => "xsd:bool",
        "mixed" => "xsd:anyType",
    );

    function __construct($uri, $class, $name = "") {
        $this->uri = $uri;
        if ($class instanceof ReflectionClass) {
            $this->class = $class;
        } else {
            $this->class = new ReflectionClass($class);
        }
        if (!empty($name)) {
            $this->name = $name;
        } else {
            $this->name = $this->class->getShortName();
        }
    }

    public function generate() {
        $this->serviceInstance = $this->class->newInstanceArgs(func_get_args());

        $this->configureWSDL($this->name, $this->uri);
        $this->wsdl->bindings[$this->name . 'Binding']['portType'] = $this->name;

        $this->registerServices();

        return $this;
    }

    private function registerServices() {
        $methods = $this->class->getMethods(ReflectionMethod::IS_PUBLIC & ~ReflectionMethod::IS_STATIC);

        foreach ($methods as /* @var $method ReflectionMethod */ $method) {
            $metadata = nusoap_comment_parser::parse($method->getDocComment());
            $this->registerServiceMethod($method, $metadata);
        }
    }

    private function registerServiceMethod(ReflectionMethod $method, array $metadata) {
        $params = array();
        $return = array();

        $return["return"] = $this->handleType(isset($metadata["return"]) ? $metadata["return"]["type"] : "mixed");

        foreach ($method->getParameters() as $k => /* @var $parameter ReflectionParameter */ $parameter) {
            $parameterTypeName = isset($metadata["param"][$k]) ? $metadata["param"][$k]["type"] : "mixed";
            if ($parameter->getClass() != null) {
                $params[$parameter->name] = $this->registerComplexObject($parameter->getClass());
            } else {
                $params[$parameter->name] = $this->handleType($parameterTypeName);
            }
        }

        $this->register(array(
            "name" => $method->name,
            "handler" => array(
                new nusoap_soap_action_handler
                        ($this->serviceInstance, $method->name, $params, $this->mappedComplexTypes, $this->mappedArrays, $this->knownPrimitiveTypes),
                "invoke"
            )), $params, $return, $this->uri, false, false, false, "{$metadata["description"]} {$metadata["longDescription"]}");
    }

    private function handleType($type) {
        if (array_key_exists($type, $this->knownPrimitiveTypes)) {
            return $this->knownPrimitiveTypes[$type];
        } else if (class_exists($type)) {
            return $this->registerComplexObject(new ReflectionClass($type));
        } else if ($type == "array" || $type == "array[]") {
            return $this->registerArray($this->handleType("mixed"));
        } else if (substr_compare($type, "[]", -strlen("[]")) === 0) {
            $actualType = $this->handleType(trim($type, "[]"));
            return $this->registerArray($actualType);
        } else if (
                preg_match('/array\[[a-z0-9_\\\]*\]/i', $type) === 1 || preg_match('/array<[a-z0-9_\\\]*>/i', $type) === 1) {
            $start = 6;
            $length = strlen($type) - $start - 1;
            $actualType = $this->handleType(substr($type, $start, $length));
            return $this->registerArray($actualType);
        }
        return $this->knownPrimitiveTypes["mixed"];
    }

    private function registerComplexObject(ReflectionClass $type) {
        $wsdlType = "tns:{$type->getShortName()}";

        if (!array_key_exists($wsdlType, $this->mappedComplexTypes)) {
            $structure = array();
            foreach ($type->getProperties(ReflectionProperty::IS_PUBLIC & ~ReflectionProperty::IS_STATIC) as /* @var $property ReflectionProperty */ $property) {
                $metadata = nusoap_comment_parser::parse($property->getDocComment());

                $typeName = $this->handleType(isset($metadata["var"]) ? $metadata["var"]["type"] : "mixed");
                $structure[$property->name] = array(
                    "type" => $typeName,
                );
            }
            $this->wsdl->addComplexType($type->getShortName(), 'complexType', 'struct', 'all', '', $structure);
            $this->mappedComplexTypes[$wsdlType] = array(
                "class" => $type->getName(),
                "structure" => $structure,
            );
        }

        return $wsdlType;
    }

    private function registerArray($itemType) {
        $alias = trim($itemType, '\\');
        if (strpos($itemType, ":") !== false) {
            $alias = substr($itemType, strpos($itemType, ":") + 1);
        }
        $alias .= "Array";
        $wsdlType = "tns:$alias";

        if (!array_key_exists($wsdlType, $this->mappedArrays)) {
            $this->wsdl->addComplexType($alias, 'complexType', 'array', '', 'SOAP-ENC:Array', array(), array(
                array(
                    'ref' => 'SOAP-ENC:arrayType',
                    'wsdl:arrayType' => "{$itemType}[]"
                )
                    ), $itemType
            );
            $this->mappedArrays[$wsdlType] = $itemType;
        }

        return $wsdlType;
    }

}

class nusoap_soap_action_handler {

    private $delegate;
    private $actionName;
    private $parameterTypes = array();
    private $mappedComplexTypes = array();
    private $mappedArrays = array();
    private $knownPrimitiveTypes;

    function __construct($delegate, $actionName, $parameterTypes, $mappedComplexTypes, $mappedArrays, $knownPrimitiveTypes) {
        $this->delegate = $delegate;
        $this->actionName = $actionName;
        $this->parameterTypes = $parameterTypes;
        $this->mappedComplexTypes = $mappedComplexTypes;
        $this->mappedArrays = $mappedArrays;
        $this->knownPrimitiveTypes = $knownPrimitiveTypes;
    }

    public function invoke() {
        //Check parameter types...
        $parameters = $this->checkParameters(func_get_args());

        $result = call_user_func_array(array($this->delegate, $this->actionName), $parameters);

        //Check result type...

        return json_decode(json_encode($result), true);
    }

    private function checkParameters($arguments) {
        $parameters = array();
        foreach (array_values($this->parameterTypes) as $k => $wsdlType) {
            $parameters[] = $this->handleParameter($wsdlType, $arguments[$k]);
        }

        return $parameters;
    }

    private function handleParameter($wsdlType, $argument) {
        if (in_array($wsdlType, $this->knownPrimitiveTypes)) {
            $parameter = $argument;
        } else if (array_key_exists($wsdlType, $this->mappedComplexTypes)) {
            $parameter = $this->mapObject($wsdlType, $argument);
        } else if (array_key_exists($wsdlType, $this->mappedArrays)) {
            $actualType = $this->mappedArrays[$wsdlType];
            $parameter = array();
            foreach ($argument as $key => $value) {
                $parameter[$key] = $this->handleParameter($actualType, $value);
            }
        }

        return $parameter;
    }

    private function mapObject($wsdlType, $data) {
        $mapping = $this->mappedComplexTypes[$wsdlType];
        $instace = new $mapping["class"]();
        foreach ($mapping["structure"] as $porperty => $metadata) {
            $propertyType = $metadata["type"];
            if (in_array($propertyType, $this->knownPrimitiveTypes)) {
                $instace->{$porperty} = $data[$porperty];
            } else if (array_key_exists($propertyType, $this->mappedComplexTypes)) {
                $instace->{$porperty} = $this->mapObject($propertyType, $data[$porperty]);
            }
        }
        return $instace;
    }

}

/**
 * Directly taken from https://github.com/Luracast/Restler
 */
class nusoap_comment_parser {

    /**
     * name for the embedded data
     *
     * @var string
     */
    public static $embeddedDataName = 'properties';

    /**
     * Regular Expression pattern for finding the embedded data and extract
     * the inner information. It is used with preg_match.
     *
     * @var string
     */
    public static $embeddedDataPattern = '/```(\w*)[\s]*(([^`]*`{0,2}[^`]+)*)```/ms';

    /**
     * Pattern will have groups for the inner details of embedded data
     * this index is used to locate the data portion.
     *
     * @var int
     */
    public static $embeddedDataIndex = 2;

    /**
     * Delimiter used to split the array data.
     *
     * When the name portion is of the embedded data is blank auto detection
     * will be used and if URLEncodedFormat is detected as the data format
     * the character specified will be used as the delimiter to find split
     * array data.
     *
     * @var string
     */
    public static $arrayDelimiter = ',';

    /**
     * character sequence used to escape \@
     */
    const escapedAtChar = '\\@';

    /**
     * character sequence used to escape end of comment
     */
    const escapedCommendEnd = '{@*}';

    /**
     * Instance of Restler class injected at runtime.
     *
     * @var Restler
     */
    public $restler;

    /**
     * Comment information is parsed and stored in to this array.
     *
     * @var array
     */
    private $_data = array();

    /**
     * Parse the comment and extract the data.
     *
     * @static
     *
     * @param      $comment
     * @param bool $isPhpDoc
     *
     * @return array associative array with the extracted values
     */
    public static function parse($comment, $isPhpDoc = true) {
        $p = new self();
        if (empty($comment)) {
            return $p->_data;
        }

        if ($isPhpDoc) {
            $comment = self::removeCommentTags($comment);
        }

        $p->extractData($comment);
        return $p->_data;
    }

    /**
     * Removes the comment tags from each line of the comment.
     *
     * @static
     *
     * @param $comment PhpDoc style comment
     *
     * @return string comments with out the tags
     */
    public static function removeCommentTags($comment) {
        $pattern = '/(^\/\*\*)|(^\s*\**[ \/]?)|\s(?=@)|\s\*\//m';
        return preg_replace($pattern, '', $comment);
    }

    /**
     * Extracts description and long description, uses other methods to get
     * parameters.
     *
     * @param $comment
     *
     * @return array
     */
    private function extractData($comment) {
        //to use @ as part of comment we need to
        $comment = str_replace(
                array(self::escapedCommendEnd, self::escapedAtChar), array('*/', '@'), $comment);

        $description = array();
        $longDescription = array();
        $params = array();

        $mode = 0; // extract short description;
        $comments = preg_split("/(\r?\n)/", $comment);
        // remove first blank line;
        array_shift($comments);
        $addNewline = false;
        foreach ($comments as $line) {
            $line = trim($line);
            $newParam = false;
            if (empty($line)) {
                if ($mode == 0) {
                    $mode++;
                } else {
                    $addNewline = true;
                }
                continue;
            } elseif ($line{0} == '@') {
                $mode = 2;
                $newParam = true;
            }
            switch ($mode) {
                case 0 :
                    $description[] = $line;
                    if (count($description) > 3) {
                        // if more than 3 lines take only first line
                        $longDescription = $description;
                        $description[] = array_shift($longDescription);
                        $mode = 1;
                    } elseif (substr($line, -1) == '.') {
                        $mode = 1;
                    }
                    break;
                case 1 :
                    if ($addNewline) {
                        $line = ' ' . $line;
                    }
                    $longDescription[] = $line;
                    break;
                case 2 :
                    $newParam ? $params[] = $line : $params[count($params) - 1] .= ' ' . $line;
            }
            $addNewline = false;
        }
        $description = implode(' ', $description);
        $longDescription = implode(' ', $longDescription);
        $description = preg_replace('/\s+/ms', ' ', $description);
        $longDescription = preg_replace('/\s+/ms', ' ', $longDescription);
        list($description, $d1) = $this->parseEmbeddedData($description);
        list($longDescription, $d2) = $this->parseEmbeddedData($longDescription);
        $this->_data = compact('description', 'longDescription');
        $d2 += $d1;
        if (!empty($d2)) {
            $this->_data[self::$embeddedDataName] = $d2;
        }
        foreach ($params as $key => $line) {
            list(, $param, $value) = preg_split('/\@|\s/', $line, 3) + array('', '', '');
            list($value, $embedded) = $this->parseEmbeddedData($value);
            $value = array_filter(preg_split('/\s+/ms', $value));
            $this->parseParam($param, $value, $embedded);
        }
        return $this->_data;
    }

    /**
     * Parse parameters that begin with (at)
     *
     * @param       $param
     * @param array $value
     * @param array $embedded
     */
    private function parseParam($param, array $value, array $embedded) {
        $data = &$this->_data;
        $allowMultiple = false;
        switch ($param) {
            case 'param' :
                $value = $this->formatParam($value);
                $allowMultiple = true;
                break;
            case 'var' :
                $value = $this->formatVar($value);
                break;
            case 'return' :
                $value = $this->formatReturn($value);
                break;
            case 'class' :
                $data = &$data[$param];
                list ($param, $value) = $this->formatClass($value);
                break;
            case 'access' :
                $value = $value [0];
                break;
            case 'expires' :
            case 'status' :
                $value = intval($value[0]);
                break;
            case 'throws' :
                $value = $this->formatThrows($value);
                $allowMultiple = true;
                break;
            case 'header' :
                $allowMultiple = true;
                break;
            case 'author':
                $value = $this->formatAuthor($value);
                $allowMultiple = true;
                break;
            case 'link':
            case 'example':
            case 'todo':
                $allowMultiple = true;
            //don't break, continue with code for default:
            default :
                $value = implode(' ', $value);
        }
        if (!empty($embedded)) {
            if (is_string($value)) {
                $value = array('description' => $value);
            }
            $value[self::$embeddedDataName] = $embedded;
        }
        if (empty($data[$param])) {
            if ($allowMultiple) {
                $data[$param] = array(
                    $value
                );
            } else {
                $data[$param] = $value;
            }
        } elseif ($allowMultiple) {
            $data[$param][] = $value;
        } elseif ($param == 'param') {
            $arr = array(
                $data[$param],
                $value
            );
            $data[$param] = $arr;
        } else {
            if (!is_string($value) && isset($value[self::$embeddedDataName]) && isset($data[$param][self::$embeddedDataName])
            ) {
                $value[self::$embeddedDataName]
                        += $data[$param][self::$embeddedDataName];
            }
            $data[$param] = $value + $data[$param];
        }
    }

    /**
     * Parses the inline php doc comments and embedded data.
     *
     * @param $subject
     *
     * @return array
     * @throws Exception
     */
    private function parseEmbeddedData($subject) {
        $data = array();

        while (preg_match('/{@(\w+)\s([^}]*)}/ms', $subject, $matches)) {
            $subject = str_replace($matches[0], '', $subject);
            if ($matches[2] == 'true' || $matches[2] == 'false') {
                $matches[2] = $matches[2] == 'true';
            }
            if ($matches[1] != 'pattern' && false !== strpos($matches[2], static::$arrayDelimiter)
            ) {
                $matches[2] = explode(static::$arrayDelimiter, $matches[2]);
            }
            $data[$matches[1]] = $matches[2];
        }

        while (preg_match(self::$embeddedDataPattern, $subject, $matches)) {
            $subject = str_replace($matches[0], '', $subject);
            $str = $matches[self::$embeddedDataIndex];
            if (isset($this->restler) && self::$embeddedDataIndex > 1 && !empty($matches[1])
            ) {
                $extension = $matches[1];
                $formatMap = $this->restler->getFormatMap();
                if (isset($formatMap[$extension])) {
                    /**
                     * @var \Luracast\Restler\Format\iFormat
                     */
                    $format = $formatMap[$extension];
                    $format = new $format();
                    $data = $format->decode($str);
                }
            } else { // auto detect
                if ($str{0} == '{') {
                    $d = json_decode($str, true);
                    if (json_last_error() != JSON_ERROR_NONE) {
                        throw new Exception('Error parsing embedded JSON data'
                        . " $str");
                    }
                    $data = $d + $data;
                } else {
                    parse_str($str, $d);
                    //clean up
                    $d = array_filter($d);
                    foreach ($d as $key => $val) {
                        $kt = trim($key);
                        if ($kt != $key) {
                            unset($d[$key]);
                            $key = $kt;
                            $d[$key] = $val;
                        }
                        if (is_string($val)) {
                            if ($val == 'true' || $val == 'false') {
                                $d[$key] = $val == 'true' ? true : false;
                            } else {
                                $val = explode(self::$arrayDelimiter, $val);
                                if (count($val) > 1) {
                                    $d[$key] = $val;
                                } else {
                                    $d[$key] = preg_replace('/\s+/ms', ' ', $d[$key]);
                                }
                            }
                        }
                    }
                    $data = $d + $data;
                }
            }
        }
        return array($subject, $data);
    }

    private function formatThrows(array $value) {
        $r = array();
        $r['code'] = count($value) && is_numeric($value[0]) ? intval(array_shift($value)) : 500;
        $reason = implode(' ', $value);
        $r['reason'] = empty($reason) ? '' : $reason;
        return $r;
    }

    private function formatClass(array $value) {
        $param = array_shift($value);

        if (empty($param)) {
            $param = 'Unknown';
        }
        $value = implode(' ', $value);
        return array(
            $param,
            array('description' => $value)
        );
    }

    private function formatAuthor(array $value) {
        $r = array();
        $email = end($value);
        if ($email{0} == '<') {
            $email = substr($email, 1, -1);
            array_pop($value);
            $r['email'] = $email;
        }
        $r['name'] = implode(' ', $value);
        return $r;
    }

    private function formatReturn(array $value) {
        $data = explode('|', array_shift($value));
        $r = array(
            'type' => count($data) == 1 ? $data[0] : $data
        );
        $r['description'] = implode(' ', $value);
        return $r;
    }

    private function formatParam(array $value) {
        $r = array();
        $data = array_shift($value);
        if (empty($data)) {
            $r['type'] = 'mixed';
        } elseif ($data{0} == '$') {
            $r['name'] = substr($data, 1);
            $r['type'] = 'mixed';
        } else {
            $data = explode('|', $data);
            $r['type'] = count($data) == 1 ? $data[0] : $data;

            $data = array_shift($value);
            if (!empty($data) && $data{0} == '$') {
                $r['name'] = substr($data, 1);
            }
        }
        if ($value) {
            $r['description'] = implode(' ', $value);
        }
        return $r;
    }

    private function formatVar(array $value) {
        $r = array();
        $data = array_shift($value);
        if (empty($data)) {
            $r['type'] = 'mixed';
        } elseif ($data{0} == '$') {
            $r['name'] = substr($data, 1);
            $r['type'] = 'mixed';
        } else {
            $data = explode('|', $data);
            $r['type'] = count($data) == 1 ? $data[0] : $data;
        }
        if ($value) {
            $r['description'] = implode(' ', $value);
        }
        return $r;
    }

}
