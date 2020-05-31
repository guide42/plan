<?php declare(strict_types=1);

namespace plan;

use Closure;
use LogicException;

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

        return new class($valid, $result, $errors) implements Check
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
            public function getErrors(): array
            {
                return $this->errors;
            }
        };
    };
}
