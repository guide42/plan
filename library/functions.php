<?php

namespace plan;

use Closure;
use LogicException;

/**
 * Compile the schema depending on it's type. Will return always a callable
 * or throw a LogicException otherwise. If $schema is already a callable will
 * return it without modification. If not will wrap it around the proper
 * validation function.
 *
 * @param mixed $schema the plan schema
 *
 * @throws LogicException
 * @return Closure
 */
function compile($schema)
{
    if (is_scalar($schema)) {
        $validator = assert\literal($schema);
    }

    elseif (is_array($schema)) {
        if (empty($schema) || util\is_sequence($schema)) {
            $validator = assert\seq($schema);
        } else {
            $validator = assert\dict($schema);
        }
    }

    elseif (is_callable($schema)) {
        $validator = Closure::fromCallable($schema);
    }

    else {
        throw new LogicException(
            sprintf('Unsupported type %s', gettype($schema))
        );
    }

    return $validator;
}

/**
 * Wraps a schema into a validator that will return an object instead of the
 * resulting value or throw any exception. The returning object will have the
 * following methods:
 *
 *     isValid()   // will return true if result is available, false otherwise
 *     getResult() // will return the result or throw an InvalidList if none
 *     getErrors() // will return a flat list of Invalid errors, or empty array
 *
 * @param mixed $schema to validate
 *
 * @return Closure
 */
function validate($schema)
{
    /** @var Closure $validator */
    $validator = compile($schema);

    /**
     * Creates an return an object that will contain the result and a list of
     * errors thrown.
     *
     * @param mixed $data
     *
     * @return object
     */
    return function($data) use($validator)
    {
        $valid = false;
        $result = null;
        $errors = array();

        try {
            $result = $validator($data);
            $valid = true;
        } catch (InvalidList $e) {
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

            public function isValid()
            {
                return $this->valid;
            }

            public function getResult()
            {
                if (!$this->valid) {
                    throw new InvalidList($this->errors);
                }

                return $this->result;
            }

            public function getErrors()
            {
                return $this->errors;
            }
        };
    };
}
