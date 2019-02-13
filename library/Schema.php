<?php declare(strict_types=1);

namespace plan;

use LogicException;

class Schema
{
    /**
     * The validation schema.
     *
     * @var mixed|null
     */
    protected $schema;

    /**
     * This is the root validator. It's what we get from compiled schemas. If
     * it has children, will be the validator in charge of call them.
     * Null when dirty.
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
     * @throws MultipleInvalid
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
        } catch (Invalid $error) {
            if ($error instanceof MultipleInvalid) {
                throw $error;
            }
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