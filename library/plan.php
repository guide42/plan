<?php declare(strict_types=1);

namespace plan;

use Closure;
use LogicException;

class Schema
{
    /**
     * The validation schema. Null when dirty.
     *
     * @var mixed|null
     */
    protected $schema;

    /**
     * This is the root validator. It's what we get from compiled schemas. If
     * it has children, will be the validator in charge of call them.
     *
     * @var callable|null
     */
    private $compiled;

    /**
     * False when schema is compiled. True while not compiled.
     *
     * @var boolean
     */
    private $dirty = true;

    /**
     * An schema can be almost anything when compiled into functions that assert
     * or filter the input data when being call.
     *
     * @param mixed $schema to check
     *
     * @throws LogicException
     */
    public function __construct($schema)
    {
        if ($schema === null) {
            throw new LogicException('Invalid schema type');
        }

        $this->schema = $schema;
    }

    /**
     * Validate `$data` by feeding it to the root validator and let them know
     * how to traverse the value, filter it or throw an `Invalid` if validation
     * fails.
     *
     * @param mixed $data to validate
     *
     * @return mixed
     */
    public function __invoke($data)
    {
        if ($this->dirty) {
            if (is_callable($this->schema)) {
                $this->compiled = $this->schema;
            } else {
                $this->compiled = compile($this->schema);
            }
            
            $this->dirty = false;
        }

        $compiled = $this->compiled;

        try {
            return $compiled($data);
        } catch (MultipleInvalid $errors) {
            throw $errors;
        } catch (Invalid $error) {
            throw new MultipleInvalid([$error]);
        } finally {
            unset($compiled);
        }
    }

    /**
     * Shows that the schema is or not compiled, and which type (if available)
     * the schema is.
     *
     * @return string
     */
    public function __toString(): string
    {
        if (is_callable($this->schema)) { 
            $type = 'compiled';
        } else {
            $type = util\repr($this->schema);
        }
        return sprintf('<Schema:%s>', $type);
    }
}

/**
 * Compile the schema depending on it's type. Will return always a callable
 * or throw a LogicException otherwise. If `$schema` is already a callable will
 * return it without modification. If not will wrap it around the proper
 * validation function.
 *
 * @param mixed $schema the plan schema
 *
 * @throws LogicException
 * @return Closure
 */
function compile($schema): callable
{
    if (is_scalar($schema)) {
        return assert\literal($schema);
    } elseif (is_array($schema)) {
        if (empty($schema) || util\is_sequence($schema)) {
            return assert\seq($schema);
        } else {
            return assert\dict($schema);
        }
    } elseif (is_callable($schema)) {
        return Closure::fromCallable($schema);
    }

    throw new LogicException(
        sprintf('Unsupported type %s', gettype($schema))
    );
}

/**
 * Returns a validator for the schema that when use will not thrown any invalid
 * exception, nor filter the value but return true in case of passed successfuly
 * or false otherwise.
 *
 * @param mixed $schema to validate
 *
 * @return Closure
 */
function validate($schema): callable
{
    /** @var Closure $schema */
    $schema = compile($schema);

    return function($data) use($schema)
    {
        try {
            $result = $schema($data);
            $valid = true;
        } catch (MultipleInvalid $e) {
            $valid = false;
        } catch (Invalid $e) {
            $valid = false;
        }

        return $valid;
    };
}

/**
 * Wraps a schema into a validator that will return an object instead of the
 * resulting value or throw any exception. The returning object will have the
 * following methods:
 *
 *     isValid()   // will return true if validation passed, false otherwise
 *     getResult() // will return the result or throw an MultipleInvalid if none
 *     getErrors() // will return a flat list of Invalid errors, or empty array
 *
 * @param mixed $schema to validate
 *
 * @return Closure
 */
function check($schema): callable
{
    /** @var Closure $schema */
    $schema = compile($schema);

    /**
     * Creates an return an object that will contain the result and a list of
     * errors thrown.
     *
     * @param mixed $data to validate
     *
     * @return object
     */
    return function($data) use($schema)
    {
        $valid = false;
        $result = null;
        $errors = array();

        try {
            $result = $schema($data);
            $valid = true;
        } catch (MultipleInvalid $e) {
            $errors = $e->getFlatErrors();
        } catch (Invalid $e) {
            $errors = [$e];
        }

        return new class($valid, $result, $errors)
        {
            /**
             * @var boolean
             */
            protected $valid;

            /**
             * @var mixed
             */
            protected $result;

            /**
             * @var array<Invalid>
             */
            protected $errors;

            public function __construct(bool $valid, $result, array $errors)
            {
                $this->valid = $valid;
                $this->result = $result;
                $this->errors = $errors;
            }

            /**
             * Return true if the validation pass. False otherwise.
             *
             * @return boolean
             */
            public function isValid(): bool
            {
                return $this->valid;
            }

            /**
             * Retrieve the filtered result if valid. Given default otherwise.
             *
             * @param mixed $default to return if is not valid
             *
             * @return mixed
             */
            public function getResult($default = null)
            {
                if ($this->valid) {
                    return $this->result;
                }

                return $default;
            }

            /**
             * Retrieve the list of `Invalid` exceptions.
             * 
             * @return array<Invalid>
             */
            public function getErrors()
            {
                return $this->errors;
            }
        };
    };
}
