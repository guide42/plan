<?php

namespace plan;

class Schema
{
    public function __construct($schema)
    {
        $this->compiled = self::compile($schema);
    }

    public function __invoke($data)
    {
        $validator = $this->compiled;

        try {
            return $validator($data);
        } catch (InvalidList $e) {
            throw $e;
        } catch (Invalid $e) {
            throw new InvalidList(array($e));
        }
    }

    public static function compile($schema)
    {
        if (is_scalar($schema)) {
            $validator = assert\literal($schema);
        }

        elseif (is_array($schema)) {
            if (empty($schema) || util\is_sequence($schema)) {
                $validator = assert\seq($schema);
            } else {
                $validator = assert\dict($schema);
            }
        }

        elseif (is_callable($schema)) {
            $validator = $schema;
        }

        else {
            throw new \LogicException(
                sprintf('Unsupported type %s', gettype($schema))
            );
        }

        return $validator;
    }
}

class Invalid extends \Exception
{
    /**
     * Path from the root to the exception.
     *
     * @var array
     */
    protected $path;

    public function __construct($message, array $params=array(),
                                array $path=null, $code=null, $previous=null)
    {
        $this->path = null === $path ? array() : $path;
        $message = strtr($message, $params);

        parent::__construct($message, $code, $previous);
    }

    /**
     * Retrieve the path.
     *
     * @return array
     */
    public function getPath()
    {
        return $this->path;
    }
}

class InvalidList extends \Exception implements \IteratorAggregate
{
    public function __construct(array $errors, $previous=null)
    {
        $this->errors = $errors;

        $messages = array();
        foreach ($errors as $error) {
            $messages[] = $error->getMessage();
        }
        $message = 'Multiple invalid: ' . json_encode($messages);

        parent::__construct($message, null, $previous);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->errors);
    }
}

namespace plan\assert;

use plan\Schema;
use plan\Invalid;
use plan\InvalidList;

function type($type)
{
    return function($data, $path=null) use($type)
    {
        if (gettype($data) !== $type) {
            throw new Invalid('{data} is not {type}', array(
                '{data}' => json_encode($data),
                '{type}' => $type,
            ), $path);
        }

        return $data;
    };
}

function boolean()
{
    return type('boolean');
}

function int()
{
    return type('integer');
}

function float()
{
    return type('double');
}

function str()
{
    return type('string');
}

function scalar()
{
    return function($data, $path=null)
    {
        if (!is_scalar($data)) {
            throw new Invalid('{data} is not scalar',
                array('{data}' => json_encode($data)), $path);
        }

        return $data;
    };
}

function instance($class)
{
    return function($data, $path=null) use($class)
    {
        if (!($data instanceof $class)) {
            throw new Invalid('Expected {class} (is {data_class})', array(
                '{class}'      => $class,
                '{data_class}' => is_object($data) ? get_class($data)
                                                   : 'not an object',
            ));
        }

        return $data;
    };
}

function literal($literal)
{
    return function($data, $path=null) use($literal)
    {
        $type = type(gettype($literal));
        $data = $type($data, $path);

        if ($data !== $literal) {
            throw new Invalid('{data} is not {literal}', array(
                '{data}'    => json_encode($data),
                '{literal}' => json_encode($literal),
            ), $path);
        }

        return $data;
    };
}

function seq($schema)
{
    $compiled = array();

    for ($s = 0, $sl = count($schema); $s < $sl; $s++) {
        $compiled[] = Schema::compile($schema[$s]);
    }

    return function($data, $root=null) use($compiled, $sl)
    {
        $type = type('array');
        $data = $type($data, $root);

        // Empty sequence schema,
        //     allow any data
        if (empty($compiled)) {
            return $data;
        }

        $return = array();
        $root = null === $root ? array() : $root;
        $dl = count($data);

        for ($d = 0; $d < $dl; $d++) {
            $found = null;

            $path = $root;
            $path[] = $d;

            for ($s = 0; $s < $sl; $s++) {
                try {
                    $return[] = $compiled[$s]($data[$d], $path);
                    $found = true;
                    break;
                } catch (Invalid $e) {
                    $found = false;
                    if (count($e->getPath()) > count($path)) {
                        throw $e;
                    }
                }
            }

            if ($found !== true) {
                $msg = 'Invalid value at index {index} (value is {value})';
                throw new Invalid($msg, array(
                    '{index}' => $d,
                    '{value}' => json_encode($data[$d]),
                ), $path);
            }
        }

        return $return;
    };
}

function dict($schema, $required=false, $extra=false)
{
    $compiled = array();
    $reqkeys = array();

    foreach ($schema as $key => $value) {
        $compiled[$key] = Schema::compile($value);
    }

    if ($required === true) {
        $reqkeys = array_keys($compiled);
    } elseif (is_array($required)) {
        $reqkeys = array_values($required);
    } else {
        $reqkeys = array();
    }

    return function($data, $root=null) use($compiled, $reqkeys, $extra)
    {
        $type = type('array');
        $data = $type($data, $root);

        $return = array();
        $exceptions = array();
        $root = null === $root ? array() : $root;

        foreach ($data as $dkey => $dvalue) {
            $path = $root;
            $path[] = $dkey;

            if (array_key_exists($dkey, $compiled)) {
                try {
                    $return[$dkey] = $compiled[$dkey]($dvalue, $path);
                } catch (Invalid $e) {
                    if (count($e->getPath()) > count($path)) {
                        // Always grab deepest exception
                        // It will contain the path through here
                        $exceptions[] = $e;
                        continue;
                    }

                    $msg = 'Invalid value at key {key} (value is {value})';
                    $vars = array(
                        '{key}'   => $dkey,
                        '{value}' => json_encode($dvalue)
                    );

                    $exceptions[] = new Invalid($msg, $vars, $path, null, $e);
                }
            } elseif ($extra) {
                $return[$dkey] = $dvalue;
            } else {
                $exceptions[] = new Invalid('Extra key {key} not allowed',
                    array('{key}' => $dkey), $path);
            }

            $rkey = array_search($dkey, $reqkeys, true);

            if ($rkey !== false) {
                unset($reqkeys[$rkey]);
            }
        }

        foreach ($reqkeys as $rvalue) {
            $path = $root;
            $path[] = $rvalue;

            $exceptions[] = new Invalid('Required key {key} not provided',
                array('{key}' => $rvalue), $path);
        }

        if (!empty($exceptions)) {
            if (count($exceptions) === 1) {
                throw $exceptions[0];
            }
            throw new InvalidList($exceptions);
        }

        return $return;
    };
}

function any()
{
    $validators = func_get_args();
    $count = func_num_args();
    $schemas = array();

    for ($i = 0; $i < $count; $i++) {
        $schemas[] = Schema::compile($validators[$i]);
    }

    return function($data, $path=null) use($schemas, $count)
    {
        for ($i = 0; $i < $count; $i++) {
            try {
                return $schemas[$i]($data);
            } catch (Invalid $e) {
                // Ignore
                // XXX Explain why
            }
        }

        throw new Invalid('No valid value found', array(), $path);
    };
}

function all()
{
    $validators = func_get_args();
    $count = func_num_args();
    $schemas = array();

    for ($i = 0; $i < $count; $i++) {
        $schemas[] = Schema::compile($validators[$i]);
    }

    return function($data, $path=null) use($schemas, $count)
    {
        $return = $data;

        for ($i = 0; $i < $count; $i++) {
            $return = $schemas[$i]($return);
        }

        return $return;
    };
}

function not($validator)
{
    $compiled = Schema::compile($validator);

    return function($data, $path=null) use($compiled)
    {
        $pass = null;

        try {
            $compiled($data, $path);
            $pass = true;
        } catch (Invalid $e) {
            $pass = false;
        }

        if ($pass) {
            throw new Invalid('Validator passed', array(), $path);
        }

        return $data;
    };
}

function length($min=null, $max=null)
{
    return function($data, $path=null) use($min, $max)
    {
        if (gettype($data) === 'string') {
            $count = function($data) { return strlen($data); };
        } else {
            $count = function($data) { return count($data); };
        }

        if ($min !== null && $count($data) < $min) {
            throw new Invalid('Value must be at least {limit}', array(
                '{limit}' => $min,
            ), $path);
        }

        if ($max !== null && $count($data) > $max) {
            throw new Invalid('Value must be at most {limit}', array(
                '{limit}' => $max,
            ), $path);
        }

        return $data;
    };
}

function validate($name)
{
    $id = filter_id($name);

    return function($data, $path=null) use($name, $id)
    {
        if (filter_var($data, $id) === false) {
            throw new Invalid('Validation {name} for {value} failed', array(
                '{name}'  => $name,
                '{value}' => json_encode($data),
            ), $path);
        }

        return $data;
    };
}

function url()
{
    return validate('validate_url');
}

function email()
{
    return validate('validate_email');
}

function ip()
{
    return validate('validate_ip');
}

function regexp()
{
    return validate('validate_regexp');
}

namespace plan\util;

/**
 * Little hack to check if all indexes from an array are numerical and in
 * sequence.
 */
function is_sequence(array $array)
{
    return !count(array_diff_key($array, array_fill(0, count($array), null)));
}
