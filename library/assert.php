<?php

namespace plan\assert;

use Closure;
use Traversable;

use function plan\compile;
use plan\{Invalid, InvalidList, assert, filter, util};

/**
 * Check that the input data is of the given $type. The data type will not be
 * casted.
 *
 * @param string $type something that `gettype` could return
 *
 * @throws Invalid
 * @return Closure
 */
function type(string $type)
{
    return function($data, $path = null) use($type)
    {
        if (gettype($data) !== $type) {
            $tpl = '{data} is not {type}';
            $var = array(
                '{data}' => json_encode($data),
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
 * @throws Invalid
 * @return Closure
 */
function scalar()
{
    return function($data, $path = null)
    {
        if (!is_scalar($data)) {
            $tpl = '{type} is not scalar';
            $var = array(
            	'{type}' => gettype($data),
                '{data}' => json_encode($data),
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
 * @throws Invalid
 * @return Closure
 */
function instance($class)
{
    return function($data, $path = null) use($class)
    {
        if (!$data instanceof $class) {
            $tpl = 'Expected {class} (is {data_class})';
            $var = array(
                '{class}'      => $class,
                '{data_class}' => is_object($data) ? get_class($data)
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
 * @throws Invalid
 * @return Closure
 */
function literal($literal)
{
    $type = assert\type(gettype($literal));

    return function($data, $path = null) use($type, $literal)
    {
        $data = $type($data, $path);

        if ($data !== $literal) {
            $tpl = '{data} is not {literal}';
            $var = array(
                '{data}'    => json_encode($data),
                '{literal}' => json_encode($literal),
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
 * @throws Invalid
 * @return Closure
 */
function seq(array $values)
{
    $schemas = array();

    for ($s = 0, $sl = count($values); $s < $sl; $s++) {
        $schemas[] = compile($values[$s]);
    }

    $type = assert\type('array');

    return function($data, $path = null) use($type, $schemas, $sl)
    {
        $data = $type($data, $path);

        // Empty sequence schema allows any data, no validation done
        if (empty($schemas)) {
            return $data;
        }

        $return = array();
        $root = $path === null ? [] : $path;
        $dl = count($data);

        for ($d = 0; $d < $dl; $d++) {
            $found = null;

            $path = $root;
            $path[] = $d;

            for ($s = 0; $s < $sl; $s++) {
                try {
                    $return[] = $schemas[$s]($data[$d], $path);
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
                $tpl = 'Invalid value at index {index} (value is {value})';
                $var = array(
                    '{index}' => $d,
                    '{value}' => json_encode($data[$d]),
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
 * @param array         $structure key/validator array
 * @param boolean|array $required  if require all keys to be present
 * @param boolean|array $extra     if accept extra keys
 *
 * @throws Invalid
 * @throws InvalidList
 * @return Closure
 */
function dict(array $structure, $required = false, $extra = false)
{
    $compiled = array();
    $reqkeys = array();

    foreach ($structure as $key => $value) {
        $compiled[$key] = compile($value);
    }

    if ($required === true) {
        $reqkeys = array_keys($compiled);
    } elseif (is_array($required)) {
        $reqkeys = array_values($required);
    } else {
        $reqkeys = array();
    }

    if (is_array($extra)) {
        if (util\is_sequence($extra)) {
            $cextra = array_flip(array_values($extra));
        } else {
            $cextra = array();
            foreach ($extra as $dextra => $vextra) {
                $cextra[$dextra] = compile($vextra);
            }
        }
    } else {
        $cextra = $extra === true ?: array();
    }

    $type = assert\any(
        assert\type('array'),
        assert\instance(Traversable::class)
    );

    return function($data, $path = null) use($type, $compiled, $reqkeys, $cextra)
    {
        $data = $type($data, $path);
        $root = $path === null ? [] : $path;

        $return = array();
        $errors = array();

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
                        $errors[] = $e;
                        continue;
                    }

                    $tpl = 'Invalid value at key {key} (value is {value})';
                    $var = array(
                        '{key}'   => $dkey,
                        '{value}' => json_encode($dvalue)
                    );

                    $errors[] = new Invalid($tpl, $var, null, $e, $path);
                }
            } elseif (in_array($dkey, $reqkeys)) {
                $return[$dkey] = $dvalue; // no validation done
            } elseif ($cextra === true || array_key_exists($dkey, $cextra)) {
                if (is_callable($cextra[$dkey])) {
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

            $reqkeys = array_filter($reqkeys, function($rkey) use($dkey) {
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
            if (count($errors) === 1) {
                throw $errors[0];
            }
            throw new InvalidList($errors);
        }

        return $return;
    };
}

/**
 * Runs a validator through a list of data keys.
 *
 * @param mixed $validator to check
 *
 * @throws Invalid
 * @return Closure
 */
function dictkeys($validator)
{
    $schema = compile($validator);

    $type = assert\any(
        assert\type('array'),
        assert\instance(Traversable::class)
    );

    return function($data, $path = null) use($type, $schema)
    {
        $data = $type($data, $path);

        $keys = array_keys($data);
        $keys = $schema($keys, $path);

        $return = array();

        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                $tpl = 'Value for key {key} not found in {data}';
                $var = array(
                    '{key}'  => json_encode($key),
                    '{data}' => json_encode($data),
                );

                throw new Invalid($tpl, $var, null, null, $path);
            }

            $return[$key] = $data[$key];
        }

        return $return;
    };
}

/**
 * Validates uploaded file structure and error.
 *
 * @throws Invalid
 * @return Closure
 */
function file()
{
    static $errors = array(
        UPLOAD_ERR_INI_SIZE => 'File {name} exceeds upload limit',
        UPLOAD_ERR_FORM_SIZE => 'File {name} exceeds upload limit in form',
        UPLOAD_ERR_PARTIAL => 'File {name} was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_CANT_WRITE => 'File {name} could not be written on disk',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary directory',
        UPLOAD_ERR_EXTENSION => 'File upload failed due to a PHP extension',
    );

    $type = assert\dict(
        array(),
        array('tmp_name', 'size', 'error', 'name', 'type'),
        false
    );

    return function($data, $path = null) use($type, $errors)
    {
        $data = $type($data, $path);

        if ($data['error'] !== UPLOAD_ERR_OK) {
            $tpl = isset($errors[$data['error']]) ? $errors[$data['error']]
                 : 'File {name} was not uploaded due to an unknown error';
            $var = array('{name}' => $data['name']);

            throw new Invalid($tpl, $var, null, null, $path);
        }

        return $data;
    };
}

/**
 * Validate the structure of an object.
 *
 * @param array  $structure to be validation in given $data
 * @param string $class     the class name of the object
 * @param string $byref     if false, a new object will be created
 *
 * @return Closure
 */
function object(array $structure, string $class = null, bool $byref = true)
{
    $type = assert\all(
        assert\type('object'),
        assert\iif(!is_null($class), assert\instance($class)),
        filter\vars(false, true),
        assert\dict($structure, false, true)
    );

    return function($data, $path = null) use($type, $byref)
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
 * @throws Invalid
 * @return Closure
 */
function any(...$validators)
{
    $count = func_num_args();
    $schemas = [];

    for ($i = 0; $i < $count; $i++) {
        $schemas[] = compile($validators[$i]);
    }

    return function($data, $path = null) use($schemas, $count)
    {
        for ($i = 0; $i < $count; $i++) {
            try {
                return $schemas[$i]($data, $path);
            } catch (InvalidList $e) {
                // ignore
            } catch (Invalid $e) {
                // ignore
            }
        }

        throw new Invalid('No valid value found', null, null, null, $path);
    };
}

/**
 * Validate all given _validators_ or throw an exception.
 *
 * @return Closure
 */
function all(...$validators)
{
    $count = func_num_args();
    $schemas = [];

    for ($i = 0; $i < $count; $i++) {
        $schemas[] = compile($validators[$i]);
    }

    return function($data, $path = null) use($schemas, $count)
    {
        for ($i = 0; $i < $count; $i++) {
            $data = $schemas[$i]($data, $path);
        }

        return $data;
    };
}

/**
 * Check that the given _validator_ fail or throw an exception.
 *
 * @param mixed $validator to check
 *
 * @throws Invalid
 * @return Closure
 */
function not($validator)
{
    $schema = compile($validator);

    return function($data, $path = null) use($schema)
    {
        try {
            $schema($data, $path);
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
 * @return Closure
 */
function iif(bool $condition, $true = null, $false = null)
{
    $schema = function($data, $path = null) { return $data; };

    if ($condition) {
        if (!is_null($true)) {
            $schema = compile($true);
        }
    } else {
        if (!is_null($false)) {
            $schema = compile($false);
        }
    }

    return function($data, $path = null) use($schema)
    {
        return $schema($data, $path);
    };
}

/**
 * The given $data length is between $min and $max value.
 *
 * @param integer|null $min the minimum value
 * @param integer|null $max the maximum value
 *
 * @throws Invalid
 * @return Closure
 */
function length(int $min = null, int $max = null)
{
    return function($data, $path = null) use($min, $max)
    {
        if (gettype($data) === 'string') {
            $count = function($data) { return strlen($data); };
        } else {
            $count = function($data) { return count($data); };
        }

        if (!is_null($min) && $count($data) < $min) {
            $tpl = 'Value must be at least {limit}';
            $var = array('{limit}' => $min);

            throw new Invalid($tpl, $var, null, null, $path);
        }

        if (!is_null($max) && $count($data) > $max) {
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
 * @throws Invalid
 * @return Closure
 */
function validate(string $name)
{
    $id = filter_id($name);

    return function($data, $path = null) use($name, $id)
    {
        if (filter_var($data, $id) === false) {
            $tpl = 'Validation {name} for {value} failed';
            $var = array(
                '{name}'  => $name,
                '{value}' => json_encode($data),
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
 * Will validate if $data can be parsed with given $format.
 *
 * @param string  $format to parse the string with
 * @param boolean $strict if true will throw Invalid on warnings too
 *
 * @throws Invalid
 * @throws InvalidList
 * @return Closure
 */
function datetime(string $format, bool $strict = false)
{
    return function($data, $path = null) use($format, $strict)
    {
        // Silent the PHP Warning when a non-string is given.
        $dt = @\date_parse_from_format($format, $data);

        if ($dt === false || !is_array($dt)) {
            $tpl = 'Datetime format {format} for {value} failed';
            $var = array(
                '{format}' => $format,
                '{value}'  => json_encode($data),
            );

            throw new Invalid($tpl, $var, null, null, $path);
        }

        if ($dt['error_count'] + ($strict ? $dt['warning_count'] : 0) > 0) {
            $problems = $dt['errors'];
            if ($strict) {
                $problems = array_merge($problems, $dt['warnings']);
            }

            $errors = array();
            foreach ($problems as $pos => $problem) {
                $tpl = 'Datetime format {format} for {value} failed'
                     . ' on position {pos}: {problem}';
                $var = array(
                    '{format}'  => $format,
                    '{value}'   => json_encode($data),
                    '{pos}'     => $pos,
                    '{problem}' => $problem,
                );

                $errors[] = new Invalid($tpl, $var, null, null, $path);
            }

            if (count($errors) === 1) {
                throw $errors[0];
            }
            throw new InvalidList($errors);
        }

        if ($dt['month'] !== false
            && $dt['day'] !== false
            && $dt['year'] !== false
            && !checkdate($dt['month'], $dt['day'], $dt['year'])
        ) {
            $tpl = 'Date in {value} is not valid';
            $var = array(
                '{value}' => json_encode($data),
            );

            throw new Invalid($tpl, $var, null, null, $path);
        }

        return $data;
    };
}

/**
 * A wrapper around `preg_match` in a match/notmatch fashion.
 *
 * @param string $pattern regular expression to match
 *
 * @throws Invalid
 * @return Closure
 */
function match(string $pattern)
{
    return function($data, $path = null) use($pattern)
    {
        if (!preg_match($pattern, $data)) {
            $tpl = 'Value {value} doesn\'t follow {pattern}';
            $var = array(
                '{pattern}' => $pattern,
                '{value}'   => json_encode($data),
            );

            throw new Invalid($tpl, $var, null, null, $path);
        }

        return $data;
    };
}
