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
function is_sequence(array $array)
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
function has_pcre_unicode_support()
{
    static $cache;

    // Mute compilation warning "PCRE does not support \L" as it will return
    // false on error anyway.
    return isset($cache) ? $cache : $cache = @preg_match('/\pL/u', 'z') === 1;
}
