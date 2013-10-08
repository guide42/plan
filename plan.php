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
 * Base exception for all validation errors thrown by plan.
 */
class InvalidException extends \Exception {}

/**
 * Base validator.
 */
abstract class Validator
{
    protected $schema;

    public function __construct($schema)
    {
        $this->schema = $schema;
    }

    abstract public function __invoke($data);
}

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

class Type
{
    protected $types = array('boolean', 'integer', 'double', 'string',
                             'array', 'object', 'resource',
                             'NULL', 'unknown type');

    protected $type;

    public function __construct($type)
    {
        if (!in_array($type, $this->types, true)) {
            throw new SchemaException(
                sprintf('Invalid type %s', $type)
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

class StringType extends Type
{
    public function __construct()
    {
        parent::__construct('string');
    }
}
