<?php

namespace plan\util;

/**
 * Little hack to check if all indexes from an array are numerical and in
 * sequence. Require that the given `$array` is not empty.
 *
 * @param array $array to check if it is a sequence
 *
 * @return boolean
 */
function is_sequence(array $array): bool
{
    return !count(array_diff_key($array, array_fill(0, count($array), null)));
}

/**
 * Return true if `preg_*` functions support unicode character properties.
 * False otherwise.
 *
 * @link http://php.net/manual/en/regexp.reference.unicode.php
 *
 * @return boolean
 */
function has_pcre_unicode_support(): bool
{
    static $cache;

    // Mute compilation warning "PCRE does not support \L" as it will return
    // false on error anyway.
    return isset($cache) ? $cache : $cache = @preg_match('/\pL/u', 'z') === 1;
}

/**
 * Return a representation of the given value.
 *
 * @param mixed $value to represent
 *
 * @return string
 */
function repr($value): string
{
    static $limits = [
        'length' => 47,
        'size' => 3,
    ];

    if (is_string($value)) {
        $length = strlen($value);
        $open = $close = '"';

        if ($length > $limits['length']) {
            $value = substr($value, 0, $limits['length']);
            $close = '...';
        }

        return $open . $value . $close;
    }

    if (is_array($value)) {
        $size = count($value);
        $more = '';

        if ($size === 0) {
            return '[]';
        }

        if ($size > $limits['size']) {
            $value = array_slice($value, 0, $limits['size']);
            $more = ', ...';
        }

        if (is_sequence($value)) {
            $elements = array_map('plan\util\repr', $value);
        } else {
            $elements = [];
            foreach ($value as $key => $value) {
                $elements[] = repr($key) . ' => ' . repr($value);
            }
        }

        return '[' . implode(', ', $elements) . $more . ']';
    }

    if (is_object($value)) {
        return sprintf('<%s>', get_class($value));
    }

    if (is_resource($value)) {
        return sprintf('<resource:%s>', get_resource_type($value));
    }

    return strtolower(var_export($value, true));
}
