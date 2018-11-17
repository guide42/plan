<?php

namespace plan\filter;

use Closure;
use plan\{Invalid, InvalidList, assert, filter};

/**
 * Cast data type into given $type.
 *
 * @param string $type given to `settype`
 *
 * @throws Invalid
 * @return Closure
 */
function type(string $type)
{
    return function($data, $path = null) use($type)
    {
        // We need to mute the warning here. The function will return false if
        // it fails anyways and will throw our Invalid exception if that
        // happend. Also, PHPUnit convert warnings into exceptions and make the
        // test fail.
        $ret = @settype($data, $type);

        if ($ret === false) {
            $tpl = 'Cannot cast {data} into {type}';
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
 * Wrapper for `boolval`.
 *
 * @return Closure
 */
function boolval()
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
function intval(int $base = 10)
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
function floatval()
{
    return function($data, $path = null)
    {
        return \floatval($data);
    };
}

/**
 * A wrapper for sanitize filters using `filter_var`.
 *
 * @param string $name of the filter
 *
 * @throws Invalid
 * @return Closure
 */
function sanitize(string $name)
{
    $id = filter_id($name);

    return function($data, $path = null) use($name, $id)
    {
        $newdata = filter_var($data, $id);

        if ($newdata === false) {
            $tpl = 'Sanitization {name} for {value} failed';
            $var = array(
                '{name}'  => $name,
                '{value}' => json_encode($data),
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
 * @return Closure
 */
function vars(bool $recursive = false, bool $inscope = true)
{
    $closure = function($data, $path = null) use($recursive, $inscope, &$closure)
    {
        if (!is_object($data)) {
            return $data;
        }

        if ($inscope) {
            $vars = get_object_vars($data);
        } else {
            $vars = (array) $data;

            $clkey = "\0" . get_class($data) . "\0";
            $cllen = strlen($clkey);

            $replace = array();

            foreach ($vars as $key => $value) {
                // XXX Why not this?
                //     $tmp = explode("\0", $key);
                //     $key = $tmp[count($tmp) - 1];
                if ($key[0] === "\0") {
                    unset($vars[$key]);

                    if ($key[1] === '*') {
                        $key = substr($key, 3);
                    } elseif (substr($key, 0, $cllen) === $clkey) {
                        $key = substr($key, $cllen);
                    }

                    $replace[$key] = $value;
                }
            }

            if (!empty($replace)) {
                $vars = array_replace($vars, $replace);
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
 * Will parse given $format into a \DateTime object.
 *
 * @param string  $format to parse the string with
 * @param boolean $strict if true will throw Invalid on warnings too
 *
 * @return Closure
 */
function datetime(string $format, bool $strict = false)
{
    $type = assert\datetime($format, $strict);

    return function($data, $path = null) use($type, $format)
    {
        $data = $type($data, $path);
        $date = date_create_immutable_from_format($format, $data);

        return $date;
    };
}
