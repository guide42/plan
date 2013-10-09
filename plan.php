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

function type($type)
{
    return function($data) use($type)
    {
        if (gettype($data) !== $type) {
            throw new \UnexpectedValueException(
                sprintf('%s is not %s', json_encode($data), $type)
            );
        }

        return $data;
    };
}

function boolean()
{
    return type('boolean');
}

function int()
{
    return type('integer');
}

function float()
{
    return type('double');
}

function str()
{
    return type('string');
}

function scalar($scalar)
{
    return function($data) use($scalar)
    {
        $type = type(gettype($data));
        $data = $type($data);

        if ($data !== $scalar) {
            throw new \UnexpectedValueException(sprintf('%s is not %s',
                json_encode($data), json_encode($scalar)
            ));
        }

        return $data;
    };
}

function seq($schema)
{
    $compiled = array();

    for ($s = 0, $sl = count($schema); $s < $sl; $s++) {
        $compiled[] = plan($schema[$s]);
    }

    return function($data) use($compiled, $sl)
    {
        $type = type('array');
        $data = $type($data);

        // Empty sequence schema,
        //     allow any data
        if (empty($compiled)) {
            return $data;
        }

        $return = array();
        $dl = count($data);

        for ($d = 0; $d < $dl; $d++) {
            $found = null;

            for ($s = 0; $s < $sl; $s++) {
                try {
                    $return[] = $compiled[$s]($data[$d]);
                    $found = true;
                    break;
                } catch (\UnexpectedValueException $e) {
                    $found = false;
                }
            }

            if ($found !== true) {
                $msg = sprintf('Invalid value at index %d (value is %s)', $d
                               , json_encode($data[$d]));

                throw new \UnexpectedValueException($msg);
            }
        }

        return $return;
    };
}

function dict($schema, $required=false, $extra=false)
{
    $compiled = array();

    foreach ($schema as $key => $value) {
        $compiled[$key] = plan($value);
    }

    return function($data) use($compiled, $required, $extra)
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
                try {
                    $return[$dkey] = $compiled[$dkey]($dvalue);
                } catch (\UnexpectedValueException $e) {
                    $msg = sprintf('Invalid value at key %s (value is %s)'
                                   , json_encode($dkey)
                                   , json_encode($dvalue));
                    throw new \UnexpectedValueException($msg, null, $e);
                }
            } elseif ($extra) {
                $return[$dkey] = $dvalue;
            } else {
                throw new \UnexpectedValueException(
                    sprintf('Extra key %s not allowed', json_encode($dkey))
                );
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

function any()
{
    $validators = func_get_args();
    $count = func_num_args();
    $schemas = array();

    for ($i = 0; $i < $count; $i++) {
        $schemas[] = plan($validators[$i]);
    }

    return function($data) use($schemas, $count)
    {
        for ($i = 0; $i < $count; $i++) {
            try {
                return $schemas[$i]($data);
            } catch (\UnexpectedValueException $e) {
                // Ignore
            }
        }

        throw new \UnexpectedValueException('No valid value found');
    };
}

function all()
{
    $validators = func_get_args();
    $count = func_num_args();
    $schemas = array();

    for ($i = 0; $i < $count; $i++) {
        $schemas[] = plan($validators[$i]);
    }

    return function($data) use($schemas, $count)
    {
        $return = $data;

        for ($i = 0; $i < $count; $i++) {
            $return = $schemas[$i]($return);
        }

        return $return;
    };
}

function not($validator)
{
    $compiled = plan($validator);

    return function($data) use($compiled)
    {
        $pass = null;

        try {
            $compiled($data);
            $pass = true;
        } catch (\UnexpectedValueException $e) {
            $pass = false;
        }

        if ($pass) {
            throw new \UnexpectedValueException('Validator passed');
        }

        return $data;
    };
}

function length($min=null, $max=null)
{
    return function($data) use($min, $max)
    {
        if (gettype($data) === 'string') {
            $count = function($data) { return strlen($data); };
        } else {
            $count = function($data) { return count($data); };
        }

        if ($min !== null && $count($data) < $min) {
            throw new \UnexpectedValueException(
                sprintf('Value must be at least %d', $min)
            );
        }

        if ($max !== null && $count($data) > $max) {
            throw new \UnexpectedValueException(
                sprintf('Value must be at most %d', $max)
            );
        }

        return $data;
    };
}

function validate($filtername)
{
    $filterid = filter_id($filtername);

    return function($data) use($filtername, $filterid)
    {
        if (filter_var($data, $filterid) === false) {
            throw new \UnexpectedValueException(sprintf(
                'Validation %s for %s failed'
                , json_encode($filtername)
                , json_encode($data)
            ));
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

/**
 * Little hack to check if all indexes from an array are numerical and in
 * sequence.
 */
function is_sequence(array $array)
{
    return !count(array_diff_key($array, array_fill(0, count($array), null)));
}
