<?php declare(strict_types=1);

namespace plan\filter;

use Closure;
use LogicException;
use plan\{Invalid, assert, filter, util};

/**
 * Cast data type into given $type.
 *
 * @param string $type given to `settype`
 *
 * @throws Invalid
 * @return Closure
 */
function type(string $type): callable
{
    return function($data, $path = null) use($type)
    {
        // Must mute the warning here. Function `settype` will return false if
        // it fails and then will throw our Invalid exception. Also, test
        // frameworks convert warnings into exceptions and make the test fail.
        $ret = @settype($data, $type);

        if ($ret === false) {
            $ctx = [
                'data' => util\repr($data),
                'type' => $type,
            ];

            throw new Invalid('Cannot cast {data} into {type}', $ctx, $path);
        }

        return $data;
    };
}

/**
 * Wrapper for `boolval`.
 *
 * @return Closure
 */
function boolval(): callable
{
    return function($data, $path = null)
    {
        return \boolval($data);
    };
}

/**
 * Wrapper for `intval`.
 *
 * @param integer $base numerical base
 *
 * @return Closure
 */
function intval(int $base = 10): callable
{
    return function($data, $path = null) use($base)
    {
        return \intval($data, $base);
    };
}

/**
 * Wrapper for `floatval`.
 *
 * @return Closure
 */
function floatval(): callable
{
    return function($data, $path = null)
    {
        return \floatval($data);
    };
}

/**
 * A wrapper for sanitize filters using `filter_var`.
 *
 * @param string  $name  of the filter
 * @param integer $flags for filter
 *
 * @throws LogicException
 * @throws Invalid
 * @return Closure
 */
function sanitize(string $name, int $flags = 0): callable
{
    static $whitelist = ['url', 'email', 'float', 'int', 'string'];

    if (!in_array($name, $whitelist, true)) {
        throw new LogicException('Filter "' . $name . '" not allowed');
    }

    if ($name === 'float' || $name === 'int') {
        $id = filter_id('number_' . $name);
    } else {
        $id = filter_id($name);
    }

    if ($name === 'float') {
        $flags |= FILTER_FLAG_ALLOW_FRACTION;
    }
    if ($name === 'string') {
        $flags |= FILTER_FLAG_NO_ENCODE_QUOTES;
    }

    return function($data, $path = null) use($name, $id, $flags)
    {
        $new = filter_var($data, $id, array('flags' => $flags));

        if ($new === false) {
            $ctx = [
                'name' => $name,
                'data' => util\repr($data),
            ];

            throw new Invalid('Sanitization {name} failed', $ctx, $path);
        }

        return $new;
    };
}

/**
 * Alias of `plan\filter\sanitize('url')`.
 */
function url(): callable
{
    return filter\sanitize('url');
}

/**
 * Alias of `plan\filter\sanitize('email')`.
 */
function email(): callable
{
    return filter\sanitize('email');
}

/**
 * Alias of `plan\filter\sanitize('float')`.
 */
function float(): callable
{
    return filter\sanitize('float');
}

/**
 * Alias of `plan\filter\sanitize('int')`.
 */
function int(): callable
{
    return filter\sanitize('int');
}

/**
 * Alias of `plan\filter\sanitize('string')`.
 */
function str(): callable
{
    return filter\sanitize('string');
}

/**
 * Will take an object and return an associative array from it's properties.
 *
 * @param boolean $recursive if true will process with recursion
 * @param boolean $inscope   if true will only return public properties
 *
 * @return Closure
 */
function vars(bool $recursive = false, bool $inscope = true): callable
{
    $closure = function($data, $path = null) use($recursive, $inscope, &$closure)
    {
        if (!is_object($data)) {
            return $data;
        }

        if ($inscope) {
            $vars = get_object_vars($data);
        } else {
            $orig = (array) $data;
            $vars = [];

            foreach ($orig as $key => $value) {
                $tmp = explode("\0", $key);
                $key = $tmp[count($tmp) - 1];

                $vars[$key] = $value;
            }
        }

        if ($recursive) {
            // This is a ingenius way of doing recursion because we don't send
            // the $path variable. If in the future this function throw an
            // exception it should be doing manually:
            //
            //     $root = $path === null ? [] : $path;
            //     foreach ($vars as $key => $value) {
            //         $path = $root;
            //         $path[] = $key;
            //         $vars[$key] = $closure($value, $path);
            //     }
            $vars = array_map($closure, $vars);
        }

        return $vars;
    };

    return $closure;
}

/**
 * Will parse given `$format` into a \DateTime object.
 *
 * @param string  $format to parse the string with
 * @param boolean $strict if true will throw Invalid on warnings too
 *
 * @return Closure
 */
function datetime(string $format, bool $strict = false): callable
{
    $type = assert\datetime($format, $strict);

    return function($data, $path = null) use($type, $format)
    {
        $data = $type($data, $path);
        $date = date_create_immutable_from_format($format, $data);

        return $date;
    };
}

namespace plan\filter\intl;

use plan\{assert, util};

/**
 * Keep only langauge chars.
 *
 * @param boolean $lower      keep lower case letters
 * @param boolean $upper      keep upper case letters
 * @param boolean $number     keep numbers
 * @param boolean $whitespace keep whitespace
 *
 * @return Closure
 */
function chars(
    bool $lower = true,
    bool $upper = true,
    bool $number = true,
    bool $whitespace = false
) {
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

        $pattern = '/[^' . implode('', $patterns) . ']/u';
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

        $pattern = '/[^' . implode('', $patterns) . ']/';
    }

    $type = assert\str();

    return function($data, $path = null) use($pattern, $type)
    {
        return preg_replace($pattern, '', $type($data));
    };
}

/**
 * Alias of `filter\intl\chars(true, true, false)`.
 */
function alpha(bool $whitespace = false)
{
    return chars(true, true, false, $whitespace);
}

/**
 * Alias of `filter\intl\chars(true, true, true)`.
 */
function alnum(bool $whitespace = false)
{
    return chars(true, true, true, $whitespace);
}
