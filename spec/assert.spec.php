<?php declare(strict_types=1);

use function plan\compile;
use plan\{Invalid, MultipleInvalid, assert};

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

    describe('iterable', function() {
        it('validates arrays or objects implementing Traversable', function() {
            foreach ([
                array(),
                array(1, 2, 3),
                array(['one' => 1]),
                new ArrayObject,
                new ArrayIterator,
            ] as $data) {
                expect(assert\iterable()($data))->toBe($data);
            }
        });
        it('throws Invalid on non-arrays types', function() {
            foreach ([
                [true, 'true is not iterable'],
                ['a', '"a" is not iterable'],
                [123, '123 is not iterable'],
            ] as list($data, $msg)) {
                expect(function() use($data, $msg) {
                    $schema = assert\iterable();
                    $schema($data);
                })
                ->toThrow(new Invalid($msg));
            }
        });
        it('throws Invalid on objects not implementing Traversable', function() {
            foreach ([
                new stdClass,
                new LogicException,
            ] as $data) {
                expect(function() use($data) {
                    $schema = assert\iterable();
                    $schema($data);
                })
                ->toThrow(new Invalid('<' . get_class($data) . '> is not iterable'));
            }
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
            ->toThrow(new Invalid('[0] Invalid value'));
        });
        it('throws Invalid from deeper errors', function() {
            expect(function() {
                $schema = assert\seq([array('name' => assert\str())]);
                $schema([array('name' => 3.14)]);
            })
            ->toThrow(new Invalid('[0]: { [name]: 3.14 is not string }'));
        });
        it('throws Invalid from deeper errors before when encounter (fast)', function() {
            expect(function() {
                $schema = assert\seq([
                    ['foo', 'bar'],
                    // The actual validation which can possible pass is not
                    // being run because a deeper error has been thrown before.
                    // This means depth-first and fail-fast.
                    'foobar',
                ]);
                $schema([['foobar']]);
            })
            ->toThrow(new Invalid('[0]: [0] Invalid value'));
        });
    });

    describe('dict', function() {
        it('validates an associative array', function() {
            $schema = assert\dict([ 'key' => assert\str() ]);
            $data = [ 'key' => 'foobar' ];

            expect($schema($data))->toBe($data);
        });
        it('validates all keys in structure to be required', function() {
            $schema = assert\dict([ 'key' => assert\str() ], true);
            $data = [ 'key' => 'foobar' ];

            expect($schema($data))->toBe($data);
        });
        it('validates required key from associative array', function() {
            $schema = assert\dict([], ['key']);
            $data = [ 'key' => 'foobar' ];

            expect($schema($data))->toBe($data);
        });
        it('validates allowing extra keys', function() {
            $schema = assert\dict([], false, true);
            $data = [ 'key' => 'foobar' ];

            expect($schema($data))->toBe($data);
        });
        it('validates allowing extra keys by name', function() {
            $schema = assert\dict([], false, ['key']);
            $data = [ 'key' => 'foobar' ];

            expect($schema($data))->toBe($data);
        });
        it('validates extra keys', function() {
            $schema = assert\dict([], false, [ 'key' => assert\str() ]);
            $data = [ 'key' => 'foobar' ];

            expect($schema($data))->toBe($data);
        });
        it('throws MultipleInvalid when key in structure is not present', function() {
            expect(function() {
                $schema = assert\dict([ 'key' => assert\str() ], true);
                $schema([]);
            })
            ->toThrow(new MultipleInvalid([
                'key' => new Invalid('Required key key not provided'),
            ]));
        });
        it('throws MultipleInvalid when given required key is not present', function() {
            expect(function() {
                $schema = assert\dict([], ['key']);
                $schema([]);
            })
            ->toThrow(new MultipleInvalid([
                'key' => new Invalid('Required key key not provided'),
            ]));
        });
        it('throws MultipleInvalid when extra validator fails', function() {
            expect(function() {
                $schema = assert\dict([], [], ['yek' => assert\str()]);
                $schema(['yek' => 123]);
            })
            ->toThrow(new MultipleInvalid([
                'yek' => new Invalid('Extra key yek is not valid: 123 is not string'),
            ]));
        });
        it('throws MultipleInvalid when extra key is not allowed', function() {
            expect(function() {
                $schema = assert\dict([], [], []);
                $schema(['key' => 'foobar']);
            })
            ->toThrow(new MultipleInvalid([
                'key' => new Invalid('Extra key key not allowed'),
            ]));
        });
        it('throws MultipleInvalid when Invalid is thrown from deeper', function() {
            expect(function() {
                $schema = assert\dict([
                    'foo' => ['bar', 'baz'],
                ]);
                $schema([
                    'foo' => ['foo'],
                ]);
            })
            ->toThrow(new MultipleInvalid([
                'foo' => new Invalid('[foo]', null, null, 0, new Invalid('[0] Invalid value')),
            ]));
        });
        it('throws MultipleInvalid when MultipleInvalid is thrown from deeper', function() {
            expect(function() {
                $schema = assert\dict([
                    'foo' => assert\dict([
                        'bar' => 'foobar',
                    ]),
                ]);
                $schema([
                    'foo' => [
                        'bar' => 'foobaz',
                    ],
                ]);
            })
            ->toThrow(new MultipleInvalid([
                'foo' => new Invalid('[foo]', null, null, 0, new MultipleInvalid([
                    'bar' => new Invalid('[bar]', null, null, 0, new Invalid('"foobaz" is not foobar')),
                ])),
            ]));
        });
        it('throws MultipleInvalid when many errors are thrown', function() {
            expect(function() {
                $schema = assert\dict([
                    'bar' => 'foobar',
                    'baz' => 'foobaz',
                ]);
                $schema([
                    'bar' => 'foobaz',
                    'baz' => 'foobar',
                ]);
            })
            ->toThrow(new MultipleInvalid([
                'bar' => new Invalid('[bar]', null, null, 0, new Invalid('"foobaz" is not foobar')),
                'baz' => new Invalid('[baz]', null, null, 0, new Invalid('"foobar" is not foobaz')),
            ]));
        });
    });

    describe('dictkeys', function() {
        it('validates keys of an associative array', function() {
            $expected = ['zero', 'one', 'two'];

            $schema = assert\dictkeys(function(array $keys) use($expected) {
                expect($keys)->toBe($expected);
                return $keys;
            });

            $expected = array_combine($expected, [0, 1, 2]);
            $result = $schema($expected);

            expect($result)->toBe($expected);
        });
        it('returns new associative array with returned keys', function() {
            $schema = assert\dictkeys(function(array $keys) {
                return ['two'];
            });

            $result = $schema([
                'one' => 1,
                'two' => 2,
            ]);

            expect($result)->toBe([ 'two' => 2 ]);
        });
        it('throws Invalid when new keys are returned', function() {
            expect(function() {
                $schema = assert\dictkeys(function(array $keys) {
                    $keys[] = 'new';
                    return $keys;
                });

                $schema(['old' => 'foo']);
            })
            ->toThrow(new Invalid('Value for key new not found'));
        });
    });

    describe('file', function() {
        it('validates file structure', function() {
            $file = [
                'tmp_name' => '/tmp/phpUxcOty',
                'name'     => 'avatar.png',
                'type'     => 'image/png',
                'size'     => 73096,
                'error'    => 0,
            ];

            $schema = assert\file();
            $result = $schema($file);

            expect($result)->toBe($file);
        });
        it('throws Invalid on error', function() {
            foreach([
                [UPLOAD_ERR_INI_SIZE, 'File "avatar.png" exceeds upload limit'],
                [UPLOAD_ERR_FORM_SIZE, 'File "avatar.png" exceeds upload limit in form'],
                [UPLOAD_ERR_PARTIAL, 'File "avatar.png" was only partially uploaded'],
                [UPLOAD_ERR_NO_FILE, 'No file was uploaded'],
                [UPLOAD_ERR_CANT_WRITE, 'File "avatar.png" could not be written on disk'],
                [UPLOAD_ERR_NO_TMP_DIR, 'Missing temporary directory'],
                [UPLOAD_ERR_EXTENSION, 'File upload failed due to a PHP extension'],
            ] as list($err, $msg)) {
                expect(function() use($err) {
                    $schema = assert\file();
                    $schema([
                        'tmp_name' => '/tmp/phpUxcOty',
                        'name'     => 'avatar.png',
                        'type'     => 'image/png',
                        'size'     => 0,
                        'error'    => $err,
                    ]);
                })
                ->toThrow(new Invalid($msg));
            }
        });
    });

    describe('object', function() {
        it('validates public properties of an object', function() {
            $object = new \stdClass;
            $object->name = 'John';

            $schema = assert\object([ 'name' => assert\str() ]);
            $result = $schema($object);

            expect($result)->toBe($object);
        });
        it('validates class type', function() {
            $object = new \stdClass;
            $schema = assert\object([], 'stdClass');
            $result = $schema($object);

            expect($result)->toBe($object);
        });
        it('assigns new values of public properties to original object if filtered', function() {
            $object = new \stdClass;
            $object->name = 'J0hn';

            $expected = new \stdClass;
            $expected->name = 'John';

            $schema = assert\object([ 'name' => function($data, $path=null) { return 'John'; } ]);
            $result = $schema($object);

            expect($result)->toBeAnInstanceOf(stdClass::class);
            expect($result)->toEqual($expected);

            expect($object->name)->toBe('John');
        });
        it('returns same object without modifications made by filters when $byref=false', function() {
            $object = new \stdClass;
            $object->name = 'J0hn';

            $structure = [
                'name' => function($data, $path=null) {
                    return 'John';
                },
            ];

            $schema = assert\object($structure, null, false);
            $result = $schema($object);

            expect($object->name)->toBe('J0hn');
        });
    });

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
        it('throws Invalid on no valid value found when validator throws MultipleInvalid', function() {
            expect(function() {
                $schema = assert\any(function($data, $path=null) {
                    throw new MultipleInvalid([
                        new Invalid('Some error'),
                        new Invalid('Some more error')
                    ]);
                });
                $schema(42);
            })
            ->toThrow(new Invalid('No valid value found'));
        });
        it('throws Invalid with the first deep exception as previous', function() {
            expect(function() {
                $schema = assert\any(
                    array('type' => 'A', 'a-value' => assert\str()),
                    array('type' => 'B', 'b-value' => assert\int()),
                    array('type' => 'C', 'c-value' => assert\bool())
                );
                $schema(array('type' => 'C', 'c-value' => null));
            })
            ->toThrow(new Invalid('No valid value found', null, null, 0,
                new Invalid('{ [type]: "C" is not A, Extra key c-value not allowed }')
            ));
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
                ['url', 'http://www.example.org/'],
                ['email', 'john@example.org'],
                ['ip', '10.0.2.42'],
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
        it('throws LogicException when filter is not allowed', function() {
            expect(function() {
                $schema = assert\validate('callback');
            })
            ->toThrow(new LogicException('Filter "callback" not allowed'));
        });
        it('throws Invalid on invalid value', function() {
            expect(function() {
                $schema = assert\validate('int');
                $schema('hello');
            })
            ->toThrow(new Invalid('Expected int'));
        });
    });

    describe('datetime', function() {
        it('validates date-like input with a format', function() {
            foreach ([
                ['U', '1292177455'],
                ['Y-m-d H:i:s', '1987-11-10 06:42:02'],
            ] as list($format, $data)) {
                expect(assert\datetime($format)($data))->toBe($data);
            }
        });
        it('throws Invalid on non-string data', function() {
            expect(function() {
                $schema = assert\datetime('U');
                $schema(new \stdClass);
            })
            ->toThrow(new Invalid('Datetime format U for <stdClass> failed'));
        });
        it('throws Invalid on error', function() {
            expect(function() {
                $schema = assert\datetime('Y-m-d', true);
                var_dump($schema('1970-13-32'));
            })
            ->toThrow(new Invalid('Datetime format Y-m-d for "1970-13-32" failed: The parsed date was invalid'));
        });
        it('throws Invalid on warning when in strict-mode', function() {
            expect(function() {
                $schema = assert\datetime('j F Y G:i a', true);
                var_dump($schema('10 October 2018 19:30 pm'));
            })
            ->toThrow(new Invalid('Datetime format j F Y G:i a for "10 October 2018 19:30 pm" failed: The parsed time was invalid'));
        });
        it('throws MultipleInvalid on multiple errors/warnings', function() {
            expect(function() {
                $schema = assert\datetime('Y-m-d H:i:s', true);
                $schema('23:61:61');
            })
            ->toThrow(new MultipleInvalid([
                new Invalid('Datetime format Y-m-d H:i:s for "23:61:61" failed: Unexpected data found.'),
                new Invalid('Datetime format Y-m-d H:i:s for "23:61:61" failed: Unexpected data found.'),
                new Invalid('Datetime format Y-m-d H:i:s for "23:61:61" failed: Data missing'),
                new Invalid('Datetime format Y-m-d H:i:s for "23:61:61" failed: The parsed date was invalid'),
            ]));
        });
    });

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
                $schema('765');
            })
            ->toThrow(new Invalid('Value "765" doesn\'t follow /[a-z]/'));
        });
    });
});