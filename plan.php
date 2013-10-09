<?php

function plan($schema)
{
    if (is_scalar($schema)) {
        $validator = scalar($schema);
    }

    elseif (is_array($schema)) {
        if (empty($schema) || is_sequence($schema)) {
            $validator = seq($schema);
        } else {
            $validator = dict($schema);
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

function type($type, $msg='%s is not %s')
{
    return function($data) use($type, $msg)
    {
        if (gettype($data) !== $type) {
            throw new \UnexpectedValueException(
                sprintf($msg, var_export($data, true), $type)
            );
        }

        return $data;
    };
}

function bool($msg='%s is not %s')
{
    return type('boolean', $msg);
}

function int($msg='%s is not %s')
{
    return type('integer', $msg);
}

function float($msg='%s is not %s')
{
    return type('double', $msg);
}

function str($msg='%s is not %s')
{
    return type('string', $msg);
}

function scalar($scalar, $msg='%s is not %s')
{
    return function($data) use($scalar, $msg)
    {
        $type = type(gettype($data), $msg);
        $data = $type($data);

        if ($data !== $scalar) {
            throw new \UnexpectedValueException(sprintf($msg,
                var_export($data, true), var_export($scalar, true)
            ));
        }

        return $data;
    };
}

function seq($schema, $msg='')
{
    $compiled = array();

    for ($s = 0, $sl = count($schema); $s < $sl; $s++) {
        $compiled[] = plan($schema[$s]);
    }

    return function($data) use($compiled, $sl, $msg)
    {
        $type = type('array');
        $data = $type($data);

        // Empty sequence schema,
        //     allow any data
        if (empty($compiled)) {
            return $data;
        }

        $return = array();

        $d = 0;
        $dl = count($data);

        for (; $d < $dl; $d++) {
            for ($s = 0; $s < $sl; $s++) {
                try {
                    $return[] = $compiled[$s]($data[$d]);
                    break;
                } catch (\UnexpectedValueException $e) {
                    // Ignore
                }
            }
        }

        return $return;
    };
}

function dict($schema, $required=false, $extra=false, $msg='')
{
    $compiled = array();

    foreach ($schema as $key => $value) {
        $compiled[$key] = plan($value);
    }

    return function($data) use($compiled, $required, $extra, $msg)
    {
        $type = type('array');
        $data = $type($data);

        $return = array();

        if ($required === true) {
            $required = array_keys($compiled);
        } elseif (is_array($required)) {
            // TODO Validate array
        } else {
            $required = false;
        }

        foreach ($data as $dkey => $dvalue) {
            if (array_key_exists($dkey, $compiled)) {
                $return[$dkey] = $compiled[$dkey]($dvalue);
            } elseif ($extra) {
                $return[$dkey] = $dvalue;
            } else {
                throw new \UnexpectedValueException('Extra keys not allowed');
            }

            if ($required !== false) {
                $rkey = array_search($dkey, $required, true);

                if ($rkey !== false) {
                    unset($required[$rkey]);
                }
            }
        }

        if ($required !== false) {
            foreach ($required as $rvalue) {
                throw new \UnexpectedValueException(
                        sprintf('Required key %s not provided', $rvalue)
                );
            }
        }

        return $return;
    };
}

/**
 * Little hack to check if all indexes from an array are numerical and in
 * sequence.
 */
function is_sequence(array $array)
{
    return !count(array_diff_key($array, array_fill(0, count($array), null)));
}
