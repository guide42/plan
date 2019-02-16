<?php declare(strict_types=1);

namespace plan\assert;

use Closure;
use TypeError;
use LogicException;
use function plan\compile;
use plan\{Invalid, MultipleInvalid, assert, filter, util};

/**
 * Identiy validator.
 *
 * @return Closure
 */
function id()
{
    return function($data, $path = null)
    {
        return $data;
    };
}

/**
 * Compare `$data` with `$literal` using the identity operator.
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
            $ctx = [
                'data' => util\repr($data),
                'literal' => $literal,
            ];

            throw new Invalid('{data} is not {literal}', $ctx, $path);
        }

        return $data;
    };
}

/**
 * Check that the input data is of the given `$type`. The data type will not be
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
            $ctx = [
                'data' => util\repr($data),
                'type' => $type,
            ];

            throw new Invalid('{data} is not {type}', $ctx, $path);
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
 * Alias of `plan\assert\type('double')`.
 */
function float()
{
    return assert\type('double');
}

/**
 * Alias of `plan\assert\type('integer')`.
 */
function int()
{
    return assert\type('integer');
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
            $ctx = [
                'type' => gettype($data),
                'data' => util\repr($data),
            ];

            throw new Invalid('{type} is not scalar', $ctx, $path);
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
            $ctx = [
                'class' => $class,
                'object' => is_object($data)
                                ? get_class($data) : 'not an object',
            ];

            throw new Invalid('Expected {class} (is {object})', $ctx, $path);
        }

        return $data;
    };
}

/**
 * Validates that `$data` is iterable.
 *
 * @throws Invalid
 * @return Closure
 */
function iterable()
{
    return function($data, $path = null)
    {
        if (!is_iterable($data)) {
            $ctx = [
                'data' => util\repr($data),
            ];

            throw new Invalid('{data} is not iterable', $ctx, $path);
        }

        return $data;
    };
}

/**
 * Validates that `$data` is not `null` or empty string.
 *
 * @param mixed $schema to validate required values
 *
 * @throws Invalid
 * @return Closure
 */
function required($schema = null)
{
    $validator = compile($schema ?? id());

    return function($data, $path = null) use($validator)
    {
        if ($data === null || $data === '') {
            $ctx = [
                'key' => $path ? $path[count($path) - 1] : 'value',
                'data' => util\repr($data),
            ];

            throw new Invalid('Required {key} not provided', $ctx, $path);
        }

        return $validator($data);
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
            $error = null;

            $path = $root;
            $path[] = $d;

            for ($s = 0; $s < $sl; $s++) {
                try {
                    $return[] = $schemas[$s]($data[$d], $path);
                    $found = true;
                    break;
                } catch (Invalid $e) {
                    $found = false;
                    if ($e->getDepth() > count($path)) {
                        $error = $e;
                        break;
                    }
                }
            }

            if ($found !== true) {
                $tpl = $error ? '[{index}]' : '[{index}] Invalid value';
                $ctx = [
                    'index' => $d,
                    'value' => util\repr($data[$d]),
                ];

                throw new Invalid($tpl, $ctx, $path, 0, $error);
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
 * @throws MultipleInvalid
 * @return Closure
 */
function dict(array $structure, $required = array(), $extra = array())
{
    $compiled = array();
    $reqkeys = array();

    foreach ($structure as $ckey => $schema) {
        $compiled[$ckey] = compile($schema);
    }

    if ($required === true) {
        $compiled = array_map('plan\assert\required', $compiled);
    } elseif (is_array($required)) {
        foreach (array_values($required) as $rkey) {
            $compiled[$rkey] = required($compiled[$rkey] ?? id());
        }
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

    $type = assert\iterable();

    return function($data, $path = null) use($type, $compiled, $required, $cextra)
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
                    $ctx = ['key' => $dkey, 'value' => util\repr($dvalue)];
                    $errors[$dkey] = new Invalid('[{key}]', $ctx, $path, 0, $e);
                }

                unset($compiled[$dkey]);
            } elseif ($cextra === true || array_key_exists($dkey, $cextra)) {
                if (is_callable($cextra[$dkey])) {
                    try {
                        $return[$dkey] = $cextra[$dkey]($dvalue, $path);
                    } catch (Invalid $e) {
                        $tpl = 'Extra key {key} is not valid';
                        $ctx = ['key' => $dkey];
                        $errors[$dkey] = new Invalid($tpl, $ctx, $path, 0, $e);
                    }
                } else {
                    $return[$dkey] = $dvalue;
                }
            } else {
                $tpl = 'Extra key {key} not allowed';
                $ctx = ['key' => $dkey];
                $errors[$dkey] = new Invalid($tpl, $ctx, $path);
            }
        }

        if ($required !== false) {
            foreach ($compiled as $ckey => $schema) {
                $path = $root;
                $path[] = $ckey;

                try {
                    $return[$ckey] = $schema(null, $path);
                } catch (Invalid $e) {
                    $ctx = ['key' => $ckey];
                    $errors[$ckey] = new Invalid('[{key}]', $ctx, $path, 0, $e);
                }
            }
        }

        if (!empty($errors)) {
            throw new MultipleInvalid($errors, $root);
        }

        return $return;
    };
}

/**
 * Runs a validator through a list of data keys.
 *
 * @param mixed $schema to check
 *
 * @throws Invalid
 * @return Closure
 */
function keys($schema)
{
    $type = assert\iterable();
    $validator = compile($schema);

    return function($data, $path = null) use($type, $validator)
    {
        $data = $type($data, $path);

        $keys = array_keys($data);
        $keys = $validator($keys, $path);

        $return = array();

        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                $ctx = [
                    'key' => $key,
                    'data' => util\repr($data),
                ];

                throw new Invalid('Value for key {key} not found', $ctx, $path);
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

    $type = assert\dict([],
        /* required=*/['tmp_name', 'size', 'error', 'name', 'type'],
        /* extra=*/false
    );

    return function($data, $path = null) use($type, $errors)
    {
        $data = $type($data, $path);

        if ($data['error'] !== UPLOAD_ERR_OK) {
            $tpl = isset($errors[$data['error']]) ? $errors[$data['error']]
                 : 'File {name} was not uploaded due to an unknown error';
            $ctx = [
                'name' => util\repr($data['name']),
                'file' => util\repr($data),
            ];

            throw new Invalid($tpl, $ctx, $path);
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
 * Validate at least one of the given alternatives of throw an exception.
 *
 * @param array ...$alternatives schemas to match
 *
 * @throws Invalid
 * @return Closure
 */
function any(...$alternatives)
{
    $count = func_num_args();
    $schemas = array_map('plan\compile', $alternatives);

    return function($data, $path = null) use($schemas, $count)
    {
        $error = null;
        $depth = $path ? count($path) : 0;

        for ($i = 0; $i < $count; $i++) {
            try {
                return $schemas[$i]($data, $path);
            } catch (Invalid $e) {
                if ($error === null && $e->getDepth() > $depth) {
                    $error = $e;
                }
            }
        }

        throw new Invalid('No valid value found', null, $path, 0, $error);
    };
}

/**
 * Validate all given alternatives or throw an exception.
 *
 * @param array ...$alternatives schemas to match
 *
 * @return Closure
 */
function all(...$alternatives)
{
    $count = func_num_args();
    $schemas = array_map('plan\compile', $alternatives);

    return function($data, $path = null) use($schemas, $count)
    {
        for ($i = 0; $i < $count; $i++) {
            $data = $schemas[$i]($data, $path);
        }

        return $data;
    };
}

/**
 * Check that the given schema fail or throw an exception.
 *
 * @param mixed $schema to check
 *
 * @throws Invalid
 * @return Closure
 */
function not($schema)
{
    $schema = compile($schema);

    return function($data, $path = null) use($schema)
    {
        try {
            $schema($data, $path);
            $pass = true;
        } catch (Invalid $e) {
            $pass = false;
        }

        if ($pass) {
            throw new Invalid('Validator passed', null, $path);
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
 * The given `$data` length is between `$min` and `$max` value.
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
            $ctx = [
                'count' => $count($data),
                'limit' => $min,
            ];

            throw new Invalid('Value must be at least {limit}', $ctx, $path);
        }

        if (!is_null($max) && $count($data) > $max) {
            $ctx = [
                'count' => $count($data),
                'limit' => $max,
            ];

            throw new Invalid('Value must be at most {limit}', $ctx, $path);
        }

        return $data;
    };
}

/**
 * A wrapper for validate filters using `filter_var`.
 *
 * @param string  $name  of the the filter
 * @param integer $flags for filter
 *
 * @throws LogicException
 * @throws Invalid
 * @return Closure
 */
function validate(string $name, int $flags = 0)
{
    static $validate = ['domain', 'url', 'email', 'ip', 'mac_address'];
    static $whitelist = [
        'domain', 'url', 'email', 'ip', 'mac_address',
        'boolean', 'float', 'int',
    ];

    if (!in_array($name, $whitelist, true)) {
        throw new LogicException('Filter "' . $name . '" not allowed');
    }

    if (in_array($name, $validate, true)) {
        $id = filter_id('validate_' . $name);
    } else {
        $id = filter_id($name);
    }

    if ($name === 'email') {
        $flags |= FILTER_FLAG_EMAIL_UNICODE;
    }

    return function($data, $path = null) use($name, $id, $flags)
    {
        if (filter_var($data, $id) === false) {
            $ctx = [
                'name' => $name,
                'data' => util\repr($data),
            ];

            throw new Invalid('Expected {name}', $ctx, $path);
        }

        return $data;
    };
}

/**
 * Alias of `plan\assert\validate('url')`.
 */
function url()
{
    return assert\validate('url');
}

/**
 * Alias of `plan\assert\validate('email')`.
 */
function email()
{
    return assert\validate('email');
}

/**
 * Alias of `plan\assert\validate('ip')`.
 */
function ip()
{
    return assert\validate('ip');
}

/**
 * Alias of `plan\assert\validate('boolean')`.
 */
function boolval()
{
    return assert\validate('boolean');
}

/**
 * Alias of `plan\assert\validate('float')`.
 */
function floatval()
{
    return assert\validate('float');
}

/**
 * Alias of `plan\assert\validate('int')`.
 */
function intval()
{
    return assert\validate('int');
}

/**
 * Will validate if `$data` can be parsed with given `$format`.
 *
 * @param string  $format to parse the string with
 * @param boolean $strict if true will throw Invalid on warnings too
 *
 * @throws Invalid
 * @throws MultipleInvalid
 * @return Closure
 */
function datetime(string $format, bool $strict = false)
{
    return function($data, $path = null) use($format, $strict)
    {
        try {
            // Silent the PHP Warning when a non-string is given.
            $dt = @\date_parse_from_format($format, $data);
        } catch (TypeError $e) {
            $dt = false;
        }

        if ($dt === false || !is_array($dt)) {
            $tpl = 'Datetime format {format} for {data} failed';
            $ctx = [
                'format' => $format,
                'data' => util\repr($data),
            ];

            throw new Invalid($tpl, $ctx, $path);
        }

        if ($dt['error_count'] + ($strict ? $dt['warning_count'] : 0) > 0) {
            $problems = $dt['errors'];
            if ($strict) {
                $problems = array_merge($problems, $dt['warnings']);
            }

            $errors = array();
            foreach ($problems as $pos => $problem) {
                $tpl = 'Datetime format {format} for {data} failed: {problem}';
                $ctx = [
                    'problem' => $problem,
                    'format' => $format,
                    'data' => util\repr($data),
                    'pos' => $pos,
                ];

                $errors[] = new Invalid($tpl, $ctx, $path);
            }

            if (count($errors) === 1) {
                throw $errors[0];
            }
            throw new MultipleInvalid($errors, $path);
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
    $type = assert\type('string');

    return function($data, $path = null) use($type, $pattern)
    {
        $data = $type($data, $path);

        if (!preg_match($pattern, $data)) {
            $tpl = 'Value {data} doesn\'t follow {pattern}';
            $ctx = [
                'pattern' => $pattern,
                'data' => util\repr($data),
            ];

            throw new Invalid($tpl, $ctx, $path);
        }

        return $data;
    };
}
