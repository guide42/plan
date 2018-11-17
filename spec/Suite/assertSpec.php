<?php

use function plan\compile;
use plan\{Invalid, InvalidList, assert};

describe('assert', function() {
    describe('type', function() {
        it('validates boolean with assert\\bool', function() {
            $schema = assert\bool();

            expect($schema(true))->toBe(true);
            expect($schema(false))->toBe(false);
        });
        it('validates integer with assert\\int', function() {
            $schema = assert\int();

            expect($schema(0))->toBe(0);
            expect($schema(PHP_INT_MAX))->toBe(PHP_INT_MAX);
        });
        it('validates float with assert\\float', function() {
            $schema = assert\float();

            expect($schema(0.0))->toBe(0.0);
            expect($schema(2.33333333333333))->toBe(2.33333333333333);
        });
        it('validates string with assert\\str', function() {
            $schema = assert\str();

            expect($schema('hello'))->toBe('hello');
            expect($schema('world'))->toBe('world');
        });
        it('throws Invalid on not boolean with assert\\bool', function() {
            expect(function() {
                $schema = assert\bool();
                $schema(123);
            })
            ->toThrow(new Invalid('123 is not boolean'));
        });
        it('throws Invalid on not integer with assert\\int', function() {
            expect(function() {
                $schema = assert\int();
                $schema(2.7);
            })
            ->toThrow(new Invalid('2.7 is not integer'));
        });
        it('throws Invalid on not float with assert\\float', function() {
            expect(function() {
                $schema = assert\float();
                $schema('hello');
            })
            ->toThrow(new Invalid('"hello" is not double'));
        });
        it('throws Invalid on not string with assert\\str', function() {
            expect(function() {
                $schema = assert\str();
                $schema(true);
            })
            ->toThrow(new Invalid('true is not string'));
        });
    });

    describe('scalar', function() {
        it('validates scalar', function() {
            $schema = assert\scalar();

            expect($schema(true))->toBe(true);
            expect($schema(123))->toBe(123);
            expect($schema(2.7))->toBe(2.7);
            expect($schema('hello'))->toBe('hello');
        });
        it('throws Invalid on not scalar data', function() {
            $schema = assert\scalar();

            expect(function() use($schema) { $schema(array()); })->toThrow(new Invalid('array is not scalar'));
            expect(function() use($schema) { $schema(new stdClass()); })->toThrow(new Invalid('object is not scalar'));
        });
    });

    describe('instance', function() {
        it('validates instances from interface', function() {
            expect(assert\instance(Iterator::class)(new EmptyIterator))->toBeAnInstanceOf(EmptyIterator::class);
        });
        it('validates instances from class', function() {
            $asString = assert\instance(ArrayIterator::class);
            $asInstance = assert\instance(new ArrayIterator);

            expect($asString(new RecursiveArrayIterator))->toBeAnInstanceOf(RecursiveArrayIterator::class);
            expect($asInstance(new RecursiveArrayIterator))->toBeAnInstanceOf(RecursiveArrayIterator::class);
        });
        it('throws Invalid on not instance of', function() {
            expect(function() {
                $schema = assert\instance(stdClass::class);
                $schema(new Exception);
            })
            ->toThrow(new Invalid('Expected stdClass (is Exception)'));
        });
    });

    describe('literal', function() {
        it('validates equal value', function() {
            foreach ([
                [123, 123],
                ['hello', 'hello'],
                [array(1, '1'), array(1, '1')],
            ] as list($schema, $data)) {
                expect(assert\literal($schema)($data))->toBe($data);
            }
        });
        it('throws Invalid on not being equal', function() {
            expect(function() {
                $schema = assert\literal(2);
                $schema(3);
            })
            ->toThrow(new Invalid('3 is not 2'));
        });
        it('throws Invalid on types not being equal', function() {
            expect(function() {
                $schema = assert\literal('hello');
                $schema(42);
            })
            ->toThrow(new Invalid('42 is not string'));
        });
    });

    describe('seq', function() {
        it('does not validates if sequence validator is empty', function() {
            $schema = assert\seq([]);

            expect($schema([]))->toBe([]);
            expect($schema(['hello', 'world']))->toBe(['hello', 'world']);
            expect($schema([true, 3.14]))->toBe([true, 3.14]);
        });
        it('validates each value with the given validators', function() {
            $schema = assert\seq([true, 3.14, 'hello']);

            expect($schema([true]))->toBe([true]);
            expect($schema([true, true]))->toBe([true, true]);
            expect($schema([3.14, 'hello', true]))->toBe([3.14, 'hello', true]);
            expect($schema([3.14, true]))->toBe([3.14, true]);
        });
        it('throws Invalid when no valid value found', function() {
            expect(function() {
                $schema = assert\seq([true, false]);
                $schema(['hello']);
            })
            ->toThrow(new Invalid('Invalid value at index 0 (value is "hello")'));
        });
        it('throws Invalid from errors inside the sequence itself', function() {
            expect(function() {
                $schema = assert\seq([array('name' => assert\str())]);
                $schema([['name' => 3.14]]);
            })->toThrow(new Invalid('Invalid value at key name (value is 3.14)'));
        });
    });

    describe('dict', function() {});

    describe('dictkeys', function() {});

    describe('file', function() {});

    describe('object', function() {});

    describe('any', function() {
        it('validates any of the given validators', function() {
            $schema = assert\any('true', 'false', assert\bool());

            expect($schema('true'))->toBe('true');
            expect($schema('false'))->toBe('false');
            expect($schema(true))->toBe(true);
            expect($schema(false))->toBe(false);
        });
        it('throws Invalid on no valid value found when validator throws Invalid', function() {
            expect(function() {
                $schema = assert\any('true', 'false', assert\bool());
                $schema(42);
            })
            ->toThrow(new Invalid('No valid value found'));
        });
        it('throws Invalid on no valid value found when validator throws InvalidList', function() {
            expect(function() {
                $schema = assert\any(function($data, $path=null) {
                    throw new InvalidList([
                        new Invalid('Some error'),
                        new Invalid('Some more error')
                    ]);
                });
                $schema(42);
            })
            ->toThrow(new Invalid('No valid value found'));
        });
    });

    describe('all', function() {
        it('validates using all validators', function() {
            foreach ([
                [assert\all(assert\int(), 42), 42],
                [assert\all(assert\str(), 'string'), 'string'],
                [assert\all(assert\type('array'), assert\length(2, 4)), array('a', 'b', 'c')],
            ] as list($schema, $data)) {
                expect($schema($data))->toBe($data);
            }
        });
    });

    describe('not', function() {
        it('validates data that does not pass given validator', function() {
            foreach ([
                [compile(123), '123'],
                [compile(array(1, '1')), array(1, '1', 2, '2')],
                [assert\str(), 123],
                [assert\length(2, 4), array('a')],
                [assert\any(assert\str(), assert\bool()), array()],
                [assert\all(assert\str(), assert\length(2, 4)), array('a', 'b', 'c')],
            ] as list($schema, $data)) {
                expect(assert\not($schema)($data))->toBe($data);
            }
        });
        it('throws Invalid if validator passes', function() {
            foreach ([
                [compile(123), 123],
                [compile(array(1, '1')), array(1, '1', 1, '1')],
                [assert\str(), 'string'],
                [assert\length(2, 4), array('a', 'b', 'c')],
                [assert\any(assert\str(), assert\bool()), true],
                [assert\all(assert\str(), assert\length(2, 4)), 'abc'],
            ] as list($schema, $data)) {
                expect(function() use($schema, $data) {
                    $schema = assert\not($schema);
                    $schema($data);
                })
                ->toThrow(new Invalid('Validator passed'));
            }
        });
    });

    describe('iif', function() {
        it('validates first validator in condition is true', function() {
            $schema = assert\iif(true, assert\int(), assert\str());

            expect($schema(42))->toBe(42);
            expect(function() use($schema) {
                $schema('hello');
            })
            ->toThrow(new Invalid('"hello" is not integer'));
        });
        it('validates second validator in condition is false', function() {
            $schema = assert\iif(false, assert\int(), assert\str());

            expect($schema('hello'))->toBe('hello');
            expect(function() use($schema) {
                $schema(42);
            })
            ->toThrow(new Invalid('42 is not string'));
        });
        it('does not validates if validator is not given', function() {
            $called = false;
            $schema = assert\iif(true, null, function() use(&$called) {
                $called = true;
            });

            expect($schema('hello'))->toBe('hello');
            expect($called)->toBe(false);
        });
    });

    describe('length', function() {
        it('validates strings and arrays', function() {
            $schema = assert\length(2, 4);
            foreach (['abc', ['a', 'b', 'c']] as $data) {
                expect($schema($data))->toBe($data);
            }
        });
        it('throws Invalid when min is not reached', function() {
            expect(function() {
                $schema = assert\length(23);
                $schema('hello');
            })
            ->toThrow(new Invalid('Value must be at least 23'));
        });
        it('throws Invalid when max has been passed', function() {
            expect(function() {
                $schema = assert\length(0, 1);
                $schema('hello');
            })
            ->toThrow(new Invalid('Value must be at most 1'));
        });
    });

    describe('validate', function() {
        it('validates by filter name', function() {
            foreach ([
                ['int', '1234567'],
                ['boolean', 'true'],
                ['float', '7.9999999999999991118'],
                ['validate_url', 'http://www.example.org/'],
                ['validate_email', 'john@example.org'],
                ['validate_ip', '10.0.2.42'],
            ] as list($filter, $data)) {
                expect(assert\validate($filter)($data))->toBe($data);
            }
        });
        it('validates using alias validators', function() {
            foreach ([
                [assert\intval(), '1234567'],
                [assert\boolval(), 'true'],
                [assert\floatval(), '7.9999999999999991118'],
                [assert\url(), 'http://www.example.org/'],
                [assert\email(), 'john@example.org'],
                [assert\ip(), '10.0.2.42'],
            ] as list($schema, $data)) {
                expect($schema($data))->toBe($data);
            }
        });
        it('throws Invalid on invalid value', function() {
            expect(function() {
                $schema = assert\validate('int');
                $schema('hello');
            })
            ->toThrow(new Invalid('Validation int for "hello" failed'));
        });
    });

    describe('datetime', function() {});

    describe('match', function() {
        it('validates basic /[a-z]/ /[0-9]/ regular expressions', function() {
            foreach ([
                ['/[a-z]/', 'a'],
                ['/[0-9]/', '0'],
            ] as list($pattern, $data)) {
                expect(assert\match($pattern)($data))->toBe($data);
            }
        });
        it('throws Invalid when regex does not match', function() {
            expect(function() {
                $schema = assert\match('/[a-z]/');
                $schema(0);
            })
            ->toThrow(new Invalid('Value 0 doesn\'t follow /[a-z]/'));
        });
    });
});