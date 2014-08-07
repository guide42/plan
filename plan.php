<?php
/**
 * Copyright (c) 2013, Juan M Martínez <jm--at--guide42.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY
 * SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION
 * OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN
 * CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace plan;

class Schema
{
    /**
     * This is the root validator. It's what we get from compiled schemas. If
     * it has children, will be the validator in charge of call them.
     *
     * @var callable
     */
    protected $compiled;

    /**
     * @param mixed $schema the plan schema
     */
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

    /**
     * Compile the schema depending on it's type. Will return always a callable
     * or throw a \LogicException otherwise. If $schema is already a callable
     * will return it without modification. If not will wrap it around the
     * proper validation function.
     *
     * @param mixed $schema the plan schema
     *
     * @throws \LogicException
     * @return callable
     */
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

    /**
     * @param string $message  template for final message
     * @param array  $params   parameters for message template
     * @param array  $path     list of indexes/keys inside the tree
     * @param string $code     error identity code
     * @param string $previous previous exception
     */
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
    /**
     * @param array  $errors   are a list of `\plan\Invalid` exceptions
     * @param string $previous previous exception
     */
    public function __construct(array $errors, $previous=null)
    {
        $this->errors = $errors;

        $messages = array();
        foreach ($errors as $error) {
            $messages[] = $error->getMessage();
        }
        $message = 'Multiple invalid: ' . \json_encode($messages);

        parent::__construct($message, null, $previous);
    }

    /**
     * (non-PHPdoc)
     * @see \IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->errors);
    }
}

namespace plan\assert;

use plan\Schema;
use plan\Invalid;
use plan\InvalidList;

/**
 * Check that the input data is of the given $type. The data type will not be
 * casted.
 *
 * @param string $type something that `gettype` could return
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function type($type)
{
    return function($data, $path=null) use($type)
    {
        if (\gettype($data) !== $type) {
            throw new Invalid('{data} is not {type}', array(
                '{data}' => \json_encode($data),
                '{type}' => $type,
            ), $path);
        }

        return $data;
    };
}

/**
 * Alias of `type('boolean')`;
 */
function boolean()
{
    return type('boolean');
}

/**
 * Alias of `type('integer')`;
 */
function int()
{
    return type('integer');
}

/**
 * Alias of `type('double')`;
 */
function float()
{
    return type('double');
}

/**
 * Alias of `type('string')`;
 */
function str()
{
    return type('string');
}

/**
 * Wrapper for `is_scalar`.
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function scalar()
{
    return function($data, $path=null)
    {
        if (!\is_scalar($data)) {
            throw new Invalid('{data} is not scalar',
                array('{data}' => \json_encode($data)), $path);
        }

        return $data;
    };
}

/**
 * Wrapper for `instanceof` type operator.
 *
 * @param string|object $class right operator of `instanceof`
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function instance($class)
{
    return function($data, $path=null) use($class)
    {
        if (!$data instanceof $class) {
            throw new Invalid('Expected {class} (is {data_class})', array(
                '{class}'      => $class,
                '{data_class}' => \is_object($data) ? \get_class($data)
                                                    : 'not an object',
            ));
        }

        return $data;
    };
}

/**
 * Compare $data with $literal using the identity operator.
 *
 * @param mixed $literal something to compare to
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function literal($literal)
{
    return function($data, $path=null) use($literal)
    {
        $type = type(\gettype($literal));
        $data = $type($data, $path);

        if ($data !== $literal) {
            throw new Invalid('{data} is not {literal}', array(
                '{data}'    => \json_encode($data),
                '{literal}' => \json_encode($literal),
            ), $path);
        }

        return $data;
    };
}

/**
 * The given schema has to be a list of possible valid values to validate from.
 * If empty, will accept any value.
 *
 * @param array $values list of values
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function seq(array $values)
{
    $compiled = array();

    for ($s = 0, $sl = \count($values); $s < $sl; $s++) {
        $compiled[] = Schema::compile($values[$s]);
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
        $dl = \count($data);

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
                    if (\count($e->getPath()) > \count($path)) {
                        throw $e;
                    }
                }
            }

            if ($found !== true) {
                $msg = 'Invalid value at index {index} (value is {value})';
                throw new Invalid($msg, array(
                    '{index}' => $d,
                    '{value}' => \json_encode($data[$d]),
                ), $path);
            }
        }

        return $return;
    };
}

/**
 * Validate the structure of the data.
 *
 * @param array   $structure key/validator array
 * @param boolean $required  if require all keys to be present
 * @param boolean $extra     if accept extra keys
 *
 * @throws \plan\Invalid
 * @throws \plan\InvalidList
 * @return \Closure
 */
function dict(array $structure, $required=false, $extra=false)
{
    $compiled = array();
    $reqkeys = array();

    foreach ($structure as $key => $value) {
        $compiled[$key] = Schema::compile($value);
    }

    if ($required === true) {
        $reqkeys = \array_keys($compiled);
    } elseif (\is_array($required)) {
        $reqkeys = \array_values($required);
    } else {
        $reqkeys = array();
    }

    return function($data, $root=null) use($compiled, $reqkeys, $extra)
    {
        $type = any(type('array'), instance('\Traversable'));
        $data = $type($data, $root);

        $return = array();
        $exceptions = array();
        $root = null === $root ? array() : $root;

        foreach ($data as $dkey => $dvalue) {
            $path = $root;
            $path[] = $dkey;

            if (\array_key_exists($dkey, $compiled)) {
                try {
                    $return[$dkey] = $compiled[$dkey]($dvalue, $path);
                } catch (Invalid $e) {
                    if (\count($e->getPath()) > \count($path)) {
                        // Always grab deepest exception
                        // It will contain the path through here
                        $exceptions[] = $e;
                        continue;
                    }

                    $msg = 'Invalid value at key {key} (value is {value})';
                    $vars = array(
                        '{key}'   => $dkey,
                        '{value}' => \json_encode($dvalue)
                    );

                    $exceptions[] = new Invalid($msg, $vars, $path, null, $e);
                }
            } elseif ($extra) {
                $return[$dkey] = $dvalue;
            } else {
                $exceptions[] = new Invalid('Extra key {key} not allowed',
                    array('{key}' => $dkey), $path);
            }

            $rkey = \array_search($dkey, $reqkeys, true);

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
            if (\count($exceptions) === 1) {
                throw $exceptions[0];
            }
            throw new InvalidList($exceptions);
        }

        return $return;
    };
}

/**
 * Validate at least one of the given _validators_ of throw an exception.
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function any()
{
    $validators = \func_get_args();
    $count = \func_num_args();
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
                // Ignore: We want to validate only one, if this is not, it was
                //         not meant to be.
            }
        }

        throw new Invalid('No valid value found', array(), $path);
    };
}

/**
 * Validate all given _validators_ or throw an exception.
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function all()
{
    $validators = \func_get_args();
    $count = \func_num_args();
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

/**
 * Check that the given _validator_ fail or throw an exception.
 *
 * @param mixed $validator to check
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
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
            $count = function($data) { return \strlen($data); };
        } else {
            $count = function($data) { return \count($data); };
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
    $id = \filter_id($name);

    return function($data, $path=null) use($name, $id)
    {
        if (\filter_var($data, $id) === false) {
            throw new Invalid('Validation {name} for {value} failed', array(
                '{name}'  => $name,
                '{value}' => \json_encode($data),
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

function booleanval()
{
    return validate('boolean');
}

function intval()
{
    return validate('int');
}

function floatval()
{
    return validate('float');
}

namespace plan\filter;

use plan\Invalid;

/**
 * Cast data type into given $type.
 *
 * @param string $type given to `settype`
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function type($type)
{
    return function($data, $path=null) use($type)
    {
        $ret = @\settype($data, $type);

        if ($ret === false) {
            throw new Invalid('Cannot cast {data} into {type}', array(
                '{data}' => \json_encode($data),
                '{type}' => $type,
            ), $path);
        }

        return $data;
    };
}

/**
 * Wrapper for `boolval`.
 *
 * @return \Closure
 */
function booleanval()
{
    return function($data, $path=null)
    {
        return \boolval($data);
    };
}

/**
 * Wrapper for `intval`.
 *
 * @return \Closure
 */
function intval()
{
    return function($data, $path=null)
    {
        return \intval($data);
    };
}

/**
 * Wrapper for `floatval`.
 *
 * @return \Closure
 */
function floatval()
{
    return function($data, $path=null)
    {
        return \floatval($data);
    };
}

namespace plan\util;

/**
 * Little hack to check if all indexes from an array are numerical and in
 * sequence. Require that the given `$array` is not empty.
 */
function is_sequence(array $array)
{
    return !count(array_diff_key($array, array_fill(0, count($array), null)));
}
