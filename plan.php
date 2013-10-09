<?php

function plan($schema)
{
    if (is_scalar($schema)) {
        $validator = new ScalarValidator($schema);
    }

    elseif (is_array($schema)) {
        if (empty($schema) || is_sequence($schema)) {
            $validator = new SequenceValidator($schema);
        } else {
            $validator = new ArrayValidator($schema);
        }
    }

    elseif (is_object($schema) && ($schema instanceof Type ||
                                   $schema instanceof Validator)) {
        $validator = $schema;
    }

    elseif (is_callable($schema)) {
        $validator = new CallableValidator($schema);
    }

    else {
        throw new SchemaException(
            sprintf('Unsupported type %s', gettype($schema))
        );
    }

    return $validator;
}

/**
 * Thrown when the schema has some unrepairable errors.
 */
class SchemaException extends \Exception {}

/**
 * Exception for all validation errors thrown by plan.
 */
class InvalidException extends \Exception {}

/**
 * Base validator.
 */
abstract class Validator
{
    /**
     * RAW schema input by the user.
     *
     * @var unknown
     */
    protected $schema;

    public function __construct($schema)
    {
        $this->schema = $schema;
    }

    abstract public function __invoke($data);
}

/**
 * Wraps a callable to be a validator.
 */
class CallableValidator extends Validator
{
    public function __construct($schema)
    {
        if (!is_callable($schema)) {
            throw new SchemaException(
                sprintf('Schema is not callable')
            );
        }

        parent::__construct($schema);
    }

    public function __invoke($data)
    {
        return call_user_func($this->schema, $data);
    }
}

/**
 * Test that data equals to the scalar input as schema.
 */
class ScalarValidator extends Validator
{
    public function __construct($schema)
    {
        if (!is_scalar($schema)) {
            throw new SchemaException(
                sprintf('Schema is not scalar')
            );
        }

        parent::__construct($schema);
    }

    public function __invoke($data)
    {
        $type = new Type(gettype($this->schema));
        $data = $type($data);

        if ($data !== $this->schema) {
            throw new InvalidException(
                sprintf('%s is not %s', var_export($data, true),
                                        var_export($this->schema, true))
            );
        }

        return $data;
    }
}

/**
 * An array with associative keys.
 */
class ArrayValidator extends Validator
{
    /**
     * If true, the validation require all keys to be present. If is an array,
     * it contains all keys that must be required. Otherwise no key will be.
     *
     * @var boolean|array
     */
    protected $required;

    /**
     * If true, the validation accept extra keys. Otherwise will throw an
     * exception.
     *
     * @var boolean
     */
    protected $extra;

    public function __construct($schema, $required=false, $extra=false)
    {
        if (!is_array($schema)) {
            throw new SchemaException(
                sprintf('Schema is not an array')
            );
        }

        foreach ($schema as $key => $value) {
            $schema[$key] = plan($value);
        }

        parent::__construct($schema);

        $this->required = $required;
        $this->extra = $extra;
    }

    public function __invoke($data)
    {
        $type = new ArrayType();
        $data = $type($data);

        $return = array();

        if ($this->required === true) {
            $required = array_keys($this->schema);
        } elseif (is_array($this->required)) {
            $required = $this->required;
        } else {
            $required = false;
        }

        foreach ($data as $dkey => $dvalue) {
            if (array_key_exists($dkey, $this->schema)) {
                $return[$dkey] = $this->schema[$dkey]($dvalue);
            } elseif ($this->extra) {
                $return[$dkey] = $dvalue;
            } else {
                throw new InvalidException('Extra keys not allowed');
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
                throw new InvalidException(
                    sprintf('Required key %s not provided', $rvalue)
                );
            }
        }

        return $return;
    }
}

/**
 * An array that all indexes are numeric and in a sequence. Is treated as a set
 * of valid values.
 */
class SequenceValidator extends Validator
{
    public function __construct($schema)
    {
        if (!is_array($schema)) {
            throw new SchemaException(
                sprintf('Schema is not an array')
            );
        }

        if (!empty($schema) && !is_sequence($schema)) {
            throw new SchemaException(
                sprintf('Schema is not a sequence')
            );
        }

        foreach ($schema as $key => $value) {
            $schema[$key] = plan($value);
        }

        parent::__construct($schema);
    }

    public function __invoke($data)
    {
        $type = new ArrayType();
        $data = $type($data);

        // Empty sequence schema,
        //     allow any data
        if (empty($this->schema)) {
            return $data;
        }

        $return = array();

        foreach ($data as $dkey => $dvalue) {
            foreach ($this->schema as $skey => $svalue) {
                try {
                    $value = $svalue($dvalue);
                    $return[] = $value;
                    break;
                } catch (InvalidException $e) {
                    //
                }
            }
        }

        return $return;
    }
}

/**
 * Validator that test the type of the input data.
 */
class Type
{
    /**
     * A list of possible types.
     *
     * @var array
     */
    protected $types = array('boolean', 'integer', 'double', 'string',
                             'array', 'object', 'resource',
                             'NULL', 'unknown type');

    /**
     * The current type to test.
     *
     * @var string
     */
    protected $type;

    public function __construct($type)
    {
        if (!in_array($type, $this->types, true)) {
            throw new SchemaException(
                sprintf('Unknown type %s', $type)
            );
        }

        $this->type = $type;
    }

    public function __invoke($data)
    {
        if (gettype($data) !== $this->type) {
            throw new InvalidException(
                sprintf('%s is not of type %s', var_export($data, true)
                        , $this->type)
            );
        }
        return $data;
    }
}

/**
 * Alias for `Type('boolean')`.
 */
class BooleanType extends Type
{
    public function __construct()
    {
        parent::__construct('boolean');
    }
}

/**
 * Alias for `Type('integer')`.
 */
class IntegerType extends Type
{
    public function __construct()
    {
        parent::__construct('integer');
    }
}

/**
 * Alias for `Type('double')`.
 */
class DoubleType extends Type
{
    public function __construct()
    {
        parent::__construct('double');
    }
}

/**
 * Alias for `Type('string')`.
 */
class StringType extends Type
{
    public function __construct()
    {
        parent::__construct('string');
    }
}

/**
 * Alias for `Type('array')`.
 */
class ArrayType extends Type
{
    public function __construct()
    {
        parent::__construct('array');
    }
}

/**
 * Alias for `Type('object')`.
 */
class ObjectType extends Type
{
    public function __construct()
    {
        parent::__construct('object');
    }
}

/**
 * Little hack to check if all indexes from an array are numerical and in
 * sequence.
 */
function is_sequence(array $array)
{
    return !count(array_diff_key($array, array_fill(0, count($array), null)));
}
