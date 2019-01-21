<?php declare(strict_types=1);

use plan\{Schema, Invalid, MultipleInvalid};
use function plan\{compile, validate, check};

describe('Schema', function() {
    describe('__construct', function() {
        it('throws LogicException on invalid schema', function() {
            expect(function() {
                $schema = new Schema(null);
            })
            ->toThrow(new LogicException('Invalid schema type'));
        });
    });

    describe('__invoke', function() {
        it('returns validated data using compiled schema', function() {
            $schema = new Schema(42);
            $result = $schema(42);

            expect($result)->toBe(42);
        });
        it('returns validated data using raw schema', function() {
            $schema = new Schema(function($data, $path = null) {
                if ($data !== 42) {
                    throw new Invalid('Not 42');
                }
                return $data;
            });

            $result = $schema(42);

            expect($result)->toBe(42);
        });
        it('throws MultipleInvalid as catched', function() {
            expect(function() {
                $schema = new Schema(function($data, $path = null) {
                    throw new MultipleInvalid([
                        new Invalid('Not valid'),
                    ]);
                });
                $schema(24);
            })
            ->toThrow(new MultipleInvalid([
                new Invalid('Not valid'),
            ]));
        });
        it('throws MultipleInvalid with only when Invalid when chatched', function() {
            expect(function() {
                $schema = new Schema(function($data, $path = null) {
                    throw new Invalid('Not valid');
                });
                $schema(24);
            })
            ->toThrow(new MultipleInvalid([
                new Invalid('Not valid'),
            ]));
        });
    });

    describe('__toString', function() {
        it('returns compiled when a callable is given', function() {
            $schema = new Schema(function($data, $path = null) {
                return 42;
            });

            expect($schema->__toString())->toBe('<Schema:compiled>');
        });
        it('returns representation when a literal is given', function() {
            expect((new Schema(42))->__toString())->toBe('<Schema:42>');
            expect((new Schema('foobar'))->__toString())->toBe('<Schema:"foobar">');
        });
    });
});

describe('compile', function() {
    it('returns schema for literal', function() {
        expect(compile(213))->toBeAnInstanceOf(Closure::class);
    });
    it('returns schema for sequence', function() {
        expect(compile(['foo', 123]))->toBeAnInstanceOf(Closure::class);
    });
    it('returns schema for dictionary', function() {
        expect(compile(array('foo' => 'bar')))->toBeAnInstanceOf(Closure::class);
    });
    it('returns schema for validator', function() {
        expect(compile(function($data, $path = null) { return $data; }))->toBeAnInstanceOf(Closure::class);
    });
    it('throws LogicException for invalid schema', function() {
        expect(function() {
            compile(STDIN);
        })
        ->toThrow(new LogicException('Unsupported type resource'));
    });
});

describe('validate', function() {
    it('returns true when schema succeed', function() {
        $schema = validate(123);
        $result = $schema(123);

        expect($result)->toBe(true);
    });
    it('returns false when schema throws MultipleInvalid', function() {
        $schema = validate(function($data, $path = null) {
            throw new MultipleInvalid([
                new Invalid('Not valid'),
            ]);
        });

        expect($schema(123))->toBe(false);
    });
    it('returns false when schema throws Invalid', function() {
        $schema = validate(function($data, $path = null) {
            throw new Invalid('Not valid');
        });

        expect($schema(123))->toBe(false);
    });
});

describe('check', function() {
    it('returns an object with isValid method that returns true when schema succeed', function() {
        $schema = check(123);
        $result = $schema(123);

        expect($result->isValid())->toBe(true);
    });
    it('returns an object with isValid method that returns false when schema throws MultipleInvalid', function() {
        $schema = check(function($data, $path = null) {
            throw new MultipleInvalid([
                new Invalid('Not valid'),
            ]);
        });

        $result = $schema(123);

        expect($result->isValid())->toBe(false);
    });
    it('returns an object with isValid method that returns false when schema throws Invalid', function() {
        $schema = check(function($data, $path = null) {
            throw new Invalid('Not valid');
        });

        $result = $schema(123);

        expect($result->isValid())->toBe(false);
    });
    it('returns an object with getResult method that returns the filtered data when is valid', function() {
        $schema = check(function($data, $path = null) {
            return strval($data);
        });

        $result = $schema(123);

        expect($result->getResult())->toBe('123');
    });
    it('returns an object with getResult method that returns the default value when is not valid', function() {
        $schema = check(function($data, $path = null) {
            throw new Invalid('Not valid');
        });

        $result = $schema(123);

        expect($result->getResult())->toBe(null);
        expect($result->getResult('321'))->toBe('321');
    });
    it('returns an object with getErrors method that returns an empty list when is valid', function() {
        $schema = check(function($data, $path = null) {
            return strval($data);
        });

        $result = $schema(123);

        expect($result->getErrors())->toBe([]);
    });
    it('returns an object with getErrors method that returns a flat list of Invalid or MultipleInvalid exceptions when is not valid', function() {
        $previous = new Invalid('Not valid pre');
        $invalid0 = new Invalid('Not valid 0');
        $invalid1 = new Invalid('Not valid 1', null, [0], 0, $previous);
        $invalidIterator1 = new MultipleInvalid([$invalid1]);

        $schema = check(function() use($invalid0, $invalidIterator1) {
            throw new MultipleInvalid([$invalid0, $invalidIterator1]);
        });

        $result = $schema(123);

        expect($result->getErrors())->toBe([$invalid0, $invalid1, $previous]);
    });
});
