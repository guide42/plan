<?php declare(strict_types=1);

use plan\{Schema, Invalid, MultipleInvalid};

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