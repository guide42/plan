<?php

namespace plan\filter\intl;

use Closure;
use plan\{filter, util};

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

    return function($data, $path = null) use($pattern)
    {
        return preg_replace($pattern, '', $data);
    };
}

/**
 * Alias of `filter\intl\chars(true, true, false)`.
 */
function alpha(bool $whitespace = false)
{
    return filter\intl\chars(true, true, false, $whitespace);
}

/**
 * Alias of `filter\intl\chars(true, true, true)`.
 */
function alnum(bool $whitespace = false)
{
    return filter\intl\chars(true, true, true, $whitespace);
}
