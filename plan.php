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

class Invalid extends Exception
{
    /**
     * Path from the root to the exception.
     *
     * @var array
     */
    protected $path;

    public function __construct($message, array $params=array(),
                                array $path=null, $code=null, $previous=null)
    {
        $this->path = null === $path ? array() : $path;
        $message = strtr($message, $params);

        parent::__construct($message, $code, $previous);
    }

    /**
     * Retrieve the path.
     *
     * @return array
     */
    public function getPath()
    {
        return $this->path;
    }
}

function type($type)
{
    return function($data, $path=null) use($type)
    {
        if (gettype($data) !== $type) {
            throw new Invalid('{data} is not {type}', array(
                '{data}' => json_encode($data),
                '{type}' => $type,
            ), $path);
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
    return function($data, $path=null) use($scalar)
    {
        $type = type(gettype($data));
        $data = $type($data, $path);

        if ($data !== $scalar) {
            throw new Invalid('{data} is not {scalar}', array(
                '{data}'   => json_encode($data),
                '{scalar}' => json_encode($scalar),
            ), $path);
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

    return function($data, $root=null) use($compiled, $sl)
    {
        $type = type('array');
        $data = $type($data, $root);

        // Empty sequence schema,
        //     allow any data
        if (empty($compiled)) {
            return $data;
        }

        $return = array();
        $root = null === $root ? array() : $root;
        $dl = count($data);

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
                    if (count($e->getPath()) > count($path)) {
                        throw $e;
                    }
                }
            }

            if ($found !== true) {
                $msg = 'Invalid value at index {index} (value is {value})';
                throw new Invalid($msg, array(
                    '{index}' => $d,
                    '{value}' => json_encode($data[$d]),
                ), $path);
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

    return function($data, $root=null) use($compiled, $required, $extra)
    {
        $type = type('array');
        $data = $type($data, $root);

        $return = array();
        $root = null === $root ? array() : $root;

        if ($required === true) {
            $required = array_keys($compiled);
        } elseif (is_array($required)) {
            // TODO Validate array
        } else {
            $required = false;
        }

        foreach ($data as $dkey => $dvalue) {
            $path = $root;
            $path[] = $dkey;

            if (array_key_exists($dkey, $compiled)) {
                try {
                    $return[$dkey] = $compiled[$dkey]($dvalue, $path);
                } catch (Invalid $e) {
                    if (count($e->getPath()) > count($path)) {
                        throw $e; // Always throw deepest exception
                    } else {
                        $msg = 'Invalid value at key {key} (value is {value})';
                        throw new Invalid($msg, array(
                            '{key}'   => $dkey,
                            '{value}' => json_encode($dvalue),
                        ), $path, null, $e);
                    }
                }
            } elseif ($extra) {
                $return[$dkey] = $dvalue;
            } else {
                throw new Invalid('Extra key {key} not allowed', array(
                    '{key}' => $dkey,
                ), $path);
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
                $path = $root;
                $path[] = $rvalue;

                throw new Invalid('Required key {key} not provided', array(
                    '{key}' => $rvalue,
                ), $path);
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

    return function($data, $path=null) use($schemas, $count)
    {
        for ($i = 0; $i < $count; $i++) {
            try {
                return $schemas[$i]($data);
            } catch (Invalid $e) {
                // Ignore
                // XXX Explain why
            }
        }

        throw new Invalid('No valid value found', array(), $path);
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

    return function($data, $path=null) use($schemas, $count)
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
            throw new Invalid('Validator passed', array(), $path);
        }

        return $data;
    };
}

function length($min=null, $max=null)
{
    return function($data, $path=null) use($min, $max)
    {
        if (gettype($data) === 'string') {
            $count = function($data) { return strlen($data); };
        } else {
            $count = function($data) { return count($data); };
        }

        if ($min !== null && $count($data) < $min) {
            throw new Invalid('Value must be at least {limit}', array(
                '{limit}' => $min,
            ), $path);
        }

        if ($max !== null && $count($data) > $max) {
            throw new Invalid('Value must be at most {limit}', array(
                '{limit}' => $max,
            ), $path);
        }

        return $data;
    };
}

function validate($name)
{
    $id = filter_id($name);

    return function($data, $path=null) use($name, $id)
    {
        if (filter_var($data, $id) === false) {
            throw new Invalid('Validation {name} for {value} failed', array(
                '{name}'  => $name,
                '{value}' => json_encode($data),
            ), $path);
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
