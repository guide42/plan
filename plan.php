<?php

function plan($schema)
{
    if (is_object($schema) && $schema instanceof Type) {
        $validator = $schema;
    }

    elseif (is_scalar($schema)) {
        $validator = new ScalarValidator($schema);
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
 * Test that data equals to the scalar input as schema.
 */
class ScalarValidator extends Validator
{
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
