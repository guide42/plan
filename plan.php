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
            throw new InvalidList([$e]);
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
        if (\is_scalar($schema)) {
            $validator = assert\literal($schema);
        }

        elseif (\is_array($schema)) {
            if (empty($schema) || util\is_sequence($schema)) {
                $validator = assert\seq($schema);
            } else {
                $validator = assert\dict($schema);
            }
        }

        elseif (\is_callable($schema)) {
            $validator = $schema;
        }

        else {
            throw new \LogicException(
                \sprintf('Unsupported type %s', \gettype($schema))
            );
        }

        return $validator;
    }
}

class Invalid extends \Exception
{
    /**
     * Message template.
     *
     * @var string
     */
    protected $template;

    /**
     * Parameters to message template.
     *
     * @var array
     */
    protected $params = [];

    /**
     * Path from the root to the exception.
     *
     * @var array
     */
    protected $path = [];

    /**
     * @param string $template template for final message
     * @param array  $params   parameters to the template
     * @param string $code     error identity code
     * @param string $previous previous exception
     * @param array  $path     list of indexes/keys inside the tree
     */
    public function __construct($template, array $params=null, $code=null,
                                $previous=null, array $path=null
    ) {
        if (!\is_null($params) && !util\is_sequence($params)) {
            $message = \strtr($template, $params);
        } else {
            $message = $template;
        }

        parent::__construct($message, $code, $previous);

        $this->template = $template;
        $this->params = $params === null ? [] : $params;
        $this->path = $path === null ? [] : $path;
    }

    /**
     * Retrieve the path.
     *
     * @return array
     */
    public function getPath()
    {
        return \array_values($this->path);
    }
}

class InvalidList extends \Exception implements \IteratorAggregate
{
    /**
     * List of exceptions.
     *
     * @var array
     */
    protected $errors;

    /**
     * List of messages.
     *
     * @var array
     */
    protected $messages;

    /**
     * @param array  $errors   are a list of `\plan\Invalid` exceptions
     * @param string $previous previous exception
     */
    public function __construct(array $errors, $previous=null)
    {
        /**
         * Extracts error message.
         *
         * @param Invalid $error the exception
         *
         * @return string
         */
        $extract = function(Invalid $error)
        {
            return $error->getMessage();
        };

        $this->errors = $errors;
        $this->messages = \array_map($extract, $this->errors);

        $message = 'Multiple invalid: ' . \json_encode($this->messages);

        parent::__construct($message, null, $previous);
    }

    /**
     * Retrieve a list of error messages.
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
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

use plan\assert;
use plan\filter;
use plan\util;

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
            $tpl = '{data} is not {type}';
            $var = array(
                '{data}' => \json_encode($data),
                '{type}' => $type,
            );

            throw new Invalid($tpl, $var, null, null, $path);
        }

        return $data;
    };
}

/**
 * Alias of `plan\assert\type('boolean')`.
 */
function bool()
{
    return assert\type('boolean');
}

/**
 * Alias of `plan\assert\type('integer')`.
 */
function int()
{
    return assert\type('integer');
}

/**
 * Alias of `plan\assert\type('double')`.
 */
function float()
{
    return assert\type('double');
}

/**
 * Alias of `plan\assert\type('string')`.
 */
function str()
{
    return assert\type('string');
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
            $tpl = '{data} is not scalar';
            $var = array(
                '{data}' => \json_encode($data),
            );

            throw new Invalid($tpl, $var, null, null, $path);
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
            $tpl = 'Expected {class} (is {data_class})';
            $var = array(
                '{class}'      => $class,
                '{data_class}' => \is_object($data) ? \get_class($data)
                                                    : 'not an object',
            );

            throw new Invalid($tpl, $var, null, null, $path);
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
    $type = assert\type(\gettype($literal));

    return function($data, $path=null) use($type, $literal)
    {
        $data = $type($data, $path);

        if ($data !== $literal) {
            $tpl = '{data} is not {literal}';
            $var = array(
                '{data}'    => \json_encode($data),
                '{literal}' => \json_encode($literal),
            );

            throw new Invalid($tpl, $var, null, null, $path);
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

    $type = assert\type('array');

    return function($data, $root=null) use($type, $compiled, $sl)
    {
        $data = $type($data, $root);

        // Empty sequence schema,
        //     allow any data
        if (empty($compiled)) {
            return $data;
        }

        $return = array();
        $root = $root === null ? [] : $root;
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
                $tpl = 'Invalid value at index {index} (value is {value})';
                $var = array(
                    '{index}' => $d,
                    '{value}' => \json_encode($data[$d]),
                );

                throw new Invalid($tpl, $var, null, null, $path);
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

    if (\is_array($extra)) {
        if (util\is_sequence($extra)) {
            $cextra = \array_flip(\array_values($extra));
        } else {
            $cextra = array();
            foreach ($extra as $dextra => $vextra) {
                $cextra[$dextra] = Schema::compile($vextra);
            }
        }
    } else {
        $cextra = $extra === true ?: array();
    }

    $type = assert\any(
        assert\type('array'),
        assert\instance('\Traversable')
    );

    return function($data, $root=null) use($type, $compiled, $reqkeys, $cextra)
    {
        $data = $type($data, $root);
        $root = $root === null ? [] : $root;

        $return = array();
        $errors = array();

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
                        $errors[] = $e;
                        continue;
                    }

                    $tpl = 'Invalid value at key {key} (value is {value})';
                    $var = array(
                        '{key}'   => $dkey,
                        '{value}' => \json_encode($dvalue)
                    );

                    $errors[] = new Invalid($tpl, $var, null, $e, $path);

                    unset($tpl);
                    unset($var);
                }
            } elseif (\in_array($dkey, $reqkeys)) {
                $return[$dkey] = $dvalue; // no validation done
            } elseif ($cextra === true || \array_key_exists($dkey, $cextra)) {
                if (\is_callable($cextra[$dkey])) {
                    try {
                        $return[$dkey] = $cextra[$dkey]($dvalue, $path);
                    } catch (Invalid $e) {
                        $tpl = 'Extra key {key} is not valid';
                        $var = array('{key}' => $dkey);

                        $errors[] = new Invalid($tpl, $var, null, $e, $path);
                    }
                } else {
                    $return[$dkey] = $dvalue;
                }
            } else {
                $tpl = 'Extra key {key} not allowed';
                $var = array('{key}' => $dkey);

                $errors[] = new Invalid($tpl, $var, null, null, $path);
            }

            $reqkeys = \array_filter($reqkeys, function($rkey) use($dkey) {
                return $rkey !== $dkey;
            });
        }

        foreach ($reqkeys as $rvalue) {
            $path = $root;
            $path[] = $rvalue;

            $tpl = 'Required key {key} not provided';
            $var = array('{key}' => $rvalue);

            $errors[] = new Invalid($tpl, $var, null, null, $path);
        }

        if (!empty($errors)) {
            if (\count($errors) === 1) {
                throw $errors[0];
            }
            throw new InvalidList($errors);
        }

        return $return;
    };
}

function file()
{
    return assert\dict(
        array('error' => 0),
        array('tmp_name', 'size', 'error', 'name', 'type'),
        false
    );
}

/**
 * Runs a validator through a list of data keys.
 *
 * @param mixed $validator to check
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function dictkeys($validator)
{
    $compiled = Schema::compile($validator);

    $type = assert\any(
        assert\type('array'),
        assert\instance('\Traversable')
    );

    return function($data, $root=null) use($type, $compiled)
    {
        $data = $type($data, $root);

        $keys = \array_keys($data);
        $keys = $compiled($keys, $root);

        $return = array();

        foreach ($keys as $key) {
            if (!\array_key_exists($key, $data)) {
                $tpl = 'Value for key {key} not found in {data}';
                $var = array(
                    '{key}'  => \json_encode($key),
                    '{data}' => \json_encode($data),
                );

                throw new Invalid($tpl, $var, null, null, $root);
            }

            $return[$key] = $data[$key];
        }

        return $return;
    };
}

/**
 * Validate the structure of an object.
 *
 * @param array  $structure to be validation in given $data
 * @param string $class     the class name of the object
 * @param string $byref     if false, a new object will be created
 *
 * @return \Closure
 */
function object(array $structure, $class=null, $byref=true)
{
    $type = assert\all(
        assert\type('object'),
        assert\iif(null !== $class, assert\instance($class)),
        filter\vars(false, true),
        assert\dict($structure, false, true)
    );

    return function($data, $path=null) use($type, $byref)
    {
        $vars = $type($data, $path);

        if ($byref) {
            $object = $data;
        } else {
            $object = clone $data;
        }

        foreach ($vars as $key => $value) {
            $object->$key = $value;
        }

        return $object;
    };
}

/**
 * Validate at least one of the given _validators_ of throw an exception.
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function any(...$validators)
{
    $count = \func_num_args();
    $schemas = [];

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

        throw new Invalid('No valid value found', null, null, null, $path);
    };
}

/**
 * Validate all given _validators_ or throw an exception.
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function all(...$validators)
{
    $count = \func_num_args();
    $schemas = [];

    for ($i = 0; $i < $count; $i++) {
        $schemas[] = Schema::compile($validators[$i]);
    }

    return function($data, $path=null) use($schemas, $count)
    {
        $return = $data;

        for ($i = 0; $i < $count; $i++) {
            $return = $schemas[$i]($return, $path);
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
            throw new Invalid('Validator passed', null, null, null, $path);
        }

        return $data;
    };
}

/**
 * Simple condition validator.
 *
 * @param boolean $condition to check
 * @param mixed   $true      validator if the condition is true
 * @param mixed   $false     validator if the condition is false
 *
 * @return \Closure
 */
function iif($condition, $true=null, $false=null)
{
    $validator = function($data, $path=null) { return $data; };

    if ($condition) {
        if (null !== $true) {
            $validator = Schema::compile($true);
        }
    } else {
        if (null !== $false) {
            $validator = Schema::compile($false);
        }
    }

    return function($data, $path=null) use($validator)
    {
        return $validator($data, $path);
    };
}

/**
 * The given $data length is between $min and $max value.
 *
 * @param integer|null $min the minimum value
 * @param integer|null $max the maximum value
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function length($min=null, $max=null)
{
    return function($data, $path=null) use($min, $max)
    {
        if (\gettype($data) === 'string') {
            $count = function($data) { return \strlen($data); };
        } else {
            $count = function($data) { return \count($data); };
        }

        if ($min !== null && $count($data) < $min) {
            $tpl = 'Value must be at least {limit}';
            $var = array('{limit}' => $min);

            throw new Invalid($tpl, $var, null, null, $path);
        }

        if ($max !== null && $count($data) > $max) {
            $tpl = 'Value must be at most {limit}';
            $var = array('{limit}' => $max);

            throw new Invalid($tpl, $var, null, null, $path);
        }

        return $data;
    };
}

/**
 * A wrapper for validate filters using `filter_var`.
 *
 * @param string $name of the the filter
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function validate($name)
{
    $id = \filter_id($name);

    return function($data, $path=null) use($name, $id)
    {
        if (\filter_var($data, $id) === false) {
            $tpl = 'Validation {name} for {value} failed';
            $var = array(
                '{name}'  => $name,
                '{value}' => \json_encode($data),
            );

            throw new Invalid($tpl, $var, null, null, $path);
        }

        return $data;
    };
}

function url()
{
    return assert\validate('validate_url');
}

function email()
{
    return assert\validate('validate_email');
}

function ip()
{
    return assert\validate('validate_ip');
}

function boolval()
{
    return assert\validate('boolean');
}

function intval()
{
    return assert\validate('int');
}

function floatval()
{
    return assert\validate('float');
}

/**
 * A wrapper around `preg_match` in a match/notmatch fashion.
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function match($pattern)
{
    return function($data, $path=null) use($pattern)
    {
        if (!\preg_match($pattern, $data)) {
            $tpl = 'Value {value} doesn\'t follow {pattern}';
            $var = array(
                '{pattern}' => $pattern,
                '{value}'   => \json_encode($data),
            );

            throw new Invalid($tpl, $var, null, null, $path);
        }

        return $data;
    };
}

namespace plan\filter;

use plan\Invalid;
use plan\filter;

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
        // We need to mute the warning here. The function will return false if
        // it fails anyways and will throw our Invalid exception if that
        // happend. Also, PHPUnit convert warnings into exceptions and make the
        // test fail.
        $ret = @\settype($data, $type);

        if ($ret === false) {
            $tpl = 'Cannot cast {data} into {type}';
            $var = array(
                '{data}' => \json_encode($data),
                '{type}' => $type,
            );

            throw new Invalid($tpl, $var, null, null, $path);
        }

        return $data;
    };
}

/**
 * Wrapper for `boolval`.
 *
 * @return \Closure
 */
function boolval()
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
function intval($base=10)
{
    return function($data, $path=null) use($base)
    {
        return \intval($data, $base);
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

/**
 * A wrapper for sanitize filters using `filter_var`.
 *
 * @param string $name of the filter
 *
 * @throws \plan\Invalid
 * @return \Closure
 */
function sanitize($name)
{
    $id = \filter_id($name);

    return function($data, $path=null) use($name, $id)
    {
        $newdata = \filter_var($data, $id);

        if ($newdata === false) {
            $tpl = 'Sanitization {name} for {value} failed';
            $var = array(
                '{name}'  => $name,
                '{value}' => \json_encode($data),
            );

            throw new Invalid($tpl, $var, null, null, $path);
        }

        return $newdata;
    };
}

/**
 * Alias of `plan\filter\sanitize('url')`.
 */
function url()
{
    return filter\sanitize('url');
}

/**
 * Alias of `plan\filter\sanitize('email')`.
 */
function email()
{
    return filter\sanitize('email');
}

/**
 * Will take an object and return an associative array from it's properties.
 *
 * @param boolean $recursive if true will process with recursion
 * @param boolean $inscope   if true will only return public properties
 *
 * @return \Closure
 */
function vars($recursive=false, $inscope=true)
{
    $closure = function($data, $path=null) use($recursive, $inscope, &$closure)
    {
        if (!\is_object($data)) {
            return $data;
        }

        if ($inscope) {
            $vars = \get_object_vars($data);
        } else {
            $vars = (array) $data;

            $clkey = "\0" . \get_class($data) . "\0";
            $cllen = \strlen($clkey);

            $replace = array();

            foreach ($vars as $key => $value) {
                // XXX Why not this?
                //     $tmp = \explode("\0", $key);
                //     $key = $tmp[\count($tmp) - 1];
                if ($key[0] === "\0") {
                    unset($vars[$key]);

                    if ($key[1] === '*') {
                        $key = \substr($key, 3);
                    } elseif (\substr($key, 0, $cllen) === $clkey) {
                        $key = \substr($key, $cllen);
                    }

                    $replace[$key] = $value;
                }
            }

            if (!empty($replace)) {
                $vars = \array_replace($vars, $replace);
            }
        }

        if ($recursive) {
            // This is a ingenius way of doing recursion because we don't send
            // the $path variable. If in the future this function throw an
            // exception it should be doing manually:
            //
            //     $root = $path === null ? array() : $path;
            //     foreach ($vars as $key => $value) {
            //         $path = $root;
            //         $path[] = $key;
            //         $vars[$key] = $closure($value, $path);
            //     }
            $vars = \array_map($closure, $vars);
        }

        return $vars;
    };

    return $closure;
}

namespace plan\filter\intl;

use plan\filter;
use plan\util;

/**
 * Keep only langauge chars.
 *
 * @param boolean $lower      keep lower case letters
 * @param boolean $upper      keep upper case letters
 * @param boolean $number     keep numbers
 * @param boolean $whitespace keep whitespace
 *
 * @return \Closure
 */
function chars($lower=true, $upper=true, $number=true, $whitespace=false)
{
    $patterns = array();

    if ($whitespace) {
        $patterns[] = '\s';
    }

    if (util\has_pcre_unicode_support()) {
        if ($lower && $upper) {
            $patterns[] = '\p{L}';
        } elseif ($lower) {
            $patterns[] = '\p{Ll}';
        } elseif ($upper) {
            $patterns[] = '\p{Lu}';
        }
        if ($number) {
            $patterns[] = '\p{N}';
        }

        $pattern = '/[^' . \implode('', $patterns) . ']/u';
    } else {
        if ($lower) {
            $patterns[] = 'a-z';
        }
        if ($upper) {
            $patterns[] = 'A-Z';
        }
        if ($number) {
            $patterns[] = '0-9';
        }

        $pattern = '/[^' . \implode('', $patterns) . ']/';
    }

    return function($data, $path=null) use($pattern)
    {
        return \preg_replace($pattern, '', $data);
    };
}

/**
 * Alias of `filter\intl\chars(true, true, false)`.
 */
function alpha($whitespace=false)
{
    return filter\intl\chars(true, true, false, $whitespace);
}

/**
 * Alias of `filter\intl\chars(true, true, true)`.
 */
function alnum($whitespace=false)
{
    return filter\intl\chars(true, true, true, $whitespace);
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

/**
 * Return true if `preg_*` functions support unicode character properties.
 * False otherwise.
 *
 * @link http://php.net/manual/en/regexp.reference.unicode.php
 */
function has_pcre_unicode_support()
{
    static $cache;

    // Mute compilation warning "PCRE does not support \L" as it will return
    // false on error anyway.
    return isset($cache) ? $cache : $cache = @preg_match('/\pL/u', 'z') === 1;
}
