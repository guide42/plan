<?php declare(strict_types=1);

use plan\{Invalid, filter};

describe('filter', function() {
    describe('type', function() {
        it('casts to bool, int, float, string types', function() {
            foreach ([
                ['bool', true, 'something true'],
                ['int', 678, '0678 people are wrong'],
                ['float', 3.14, '3.14 < pi'],
                ['string', '3.1415926535898', pi()],
            ] as list($type, $expected, $data)) {
                $schema = filter\type($type);
                $result = $schema($data);
    
                expect($result)->toBe($expected);
            }
        });
        it('casts to erronous integer when over PHP_INT_MAX', function() {
            $schema = filter\type('integer');
            $result = $schema(PHP_INT_MAX + 1);

            expect($result)->not->toBe(PHP_INT_MAX + 1);
        });
        it('throws Invalid when cannot be transformed to type', function() {
            expect(function() {
                $schema = filter\type('unknown type');
                $schema(123);
            })
            ->toThrow(new Invalid('Cannot cast 123 into unknown type'));
        });
    });

    describe('boolval', function() {
        it('casts to true', function() {
            $schema = filter\boolval();
            foreach ([
                [1],
                'true',
                new \stdClass(),
            ] as $data) {
                expect($schema($data))->toBe(true);
            }
        });
        it('casts to false', function() {
            $schema = filter\boolval();
            foreach ([
                [],
                '',
                0,
            ] as $data) {
                expect($schema($data))->toBe(false);
            }
        });
    });

    describe('intval', function() {
        it('casts to integer', function() {
            foreach ([
                [42, '42', '042', '42i10'],
                [34, '+34', 042, 0x22],
            ] as list($expected, $data0, $data1, $data2)) {
                $schema = filter\intval();

                expect($schema($data0))->toBe($expected);
                expect($schema($data1))->toBe($expected);
                expect($schema($data2))->toBe($expected);
            }
        });
        it('casts to integer in other than ten base', function() {
            $schema = filter\intval(2);
            $result = $schema('00101010');

            expect($result)->toBe(42);
        });
    });

    describe('floatval', function() {
        it('casts to float', function() {
            foreach ([
                [0.0, 'PI = 3.14', '$ 19.332,35-', '0,76'],
                [1.999, '1.999,369', '0001.999', '1.99900000000000000000009'],
            ] as list($expected, $data0, $data1, $data2)) {
                $schema = filter\floatval();

                expect($schema($data0))->toBe($expected);
                expect($schema($data1))->toBe($expected);
                expect($schema($data2))->toBe($expected);
            }
        });
    });

    describe('sanitize', function() {
        it('returns sanitized url using filter\\url', function() {
            $schema = filter\url();
            $result = $schema('example¶.org');

            expect($result)->toBe('example.org');
        });
        it('returns sanitized email using filter\\email', function() {
            $schema = filter\email();
            $result = $schema('(john)@example¶.org');

            expect($result)->toBe('john@example.org');
        });
        it('returns sanitized float allowing dot (.) as fraction separator using filter\\float', function() {
            $schema = filter\float();
            $result = $schema('$2.2');

            expect($result)->toBe('2.2');
        });
        it('returns sanitized int using filter\\int', function() {
            $schema = filter\int();
            $result = $schema('99.9');

            expect($result)->toBe('999');
        });
        it('returns sanitized string without encoding quotes using filter\\str', function() {
            $schema = filter\str();
            $result = $schema("\n1 and 'two'");

            expect($result)->toBe("\n1 and 'two'");
        });
        it('throws LogicException when filter is not allowed', function() {
            expect(function() {
                $schema = filter\sanitize('callback');
            })
            ->toThrow(new LogicException('Filter "callback" not allowed'));
        });
        it('throws Invalid when sanitization fails', function() {
            expect(function() {
                $schema = filter\sanitize('string');
                $a = $schema(new \stdClass);
            })
            ->toThrow(new Invalid('Sanitization string failed'));
        });
    });

    describe('vars', function() {
        it('returns associative array composed of object\'s public keys and values', function() {
            $obj = new \stdClass();
            $obj->name = 'John';
            $obj->age = null;

            $dog = new \stdClass();
            $dog->name = 'Einstein';

            $obj->dog = $dog;

            $schema = filter\vars();
            $result = $schema($obj);

            $arr = array(
                'name' => 'John',
                'age' => null,
                'dog' => $dog,
            );

            expect($result)->toBe($arr);
        });
        it('returns associative array recursivly composed of object\'s public keys and values', function() {
            $obj = new \stdClass();
            $obj->name = 'John';
            $obj->age = null;
            $obj->dog = new \stdClass();
            $obj->dog->name = 'Einstein';

            $schema = filter\vars(true);
            $result = $schema($obj);

            $arr = array(
                'name' => 'John',
                'age' => null,
                'dog' => array(
                    'name' => 'Einstein',
                ),
            );

            expect($result)->toBe($arr);
        });
        it('returns associative array composed of all object\'s keys and values', function() {
            $obj = new class {
                private $secret = 'hunter2';
                protected $seed = 1234;
                public $yes = true;
            };

            $schema = filter\vars(false, false);
            $result = $schema($obj);

            $arr = array(
                'secret' => 'hunter2',
                'seed' => 1234,
                'yes' => true,
            );

            expect($result)->toBe($arr);
        });
    });

    describe('datetime', function() {
        it('cast to \DateTime from data matching format', function() {
            foreach ([
                ['Y', '2009'],
                ['Y-m', '2009-02'],
                ['m/Y', '02/2009'],
                ['d/m/y', '23/02/09'],
                ['H', '23'],
                ['H:i', '23:59'],
                ['H:i:s', '23:59:59'],
                ['P', '+03:00'],
                ['T', 'UTC'],
            ] as list($format, $data)) {
                $schema = filter\datetime($format);
                $result = $schema($data);
    
                expect($result)->toBeAnInstanceOf(DateTimeImmutable::class);
                expect($result->format($format))->toBe($data);
            }
        });
    });

    describe('intl', function() {
        describe('chars', function() {
            $data = [
                [true, true, true, true, 'hEl1o W0rld'], // filter\alnum(true)
                [true, false, false, false, 'hlorld'],
                [true, true, false, false, 'hEloWrld'], // filter\alpha(false)
                [true, false, true, false, 'hl1o0rld'],
                [true, false, false, true, 'hlo rld'],
                [false, true, false, false, 'EW'],
                [false, true, true, false, 'E1W0'],
                [false, true, false, true, 'E W'],
                [false, false, true, false, '10'],
                [false, false, true, true, '1 0'],
                [false, false, false, true, ' '],
            ];

            it('returns a copy of the string input keeping only the wanted chars', function() use($data) {
                foreach ($data as list($lower, $upper, $number, $whitespace, $expected)) {
                    $schema = filter\intl\chars($lower, $upper, $number, $whitespace);
                    $result = $schema('hEl1o ☃W0rld');

                    expect($result)->toBe($expected);
                }
            });
            it('returns a copy of the string input keeping only the wanted chars when unicode support is not available', function() use($data) {
                allow('plan\util\has_pcre_unicode_support')->toBeCalled()->andReturn(false);

                foreach ($data as list($lower, $upper, $number, $whitespace, $expected)) {
                    $schema = filter\intl\chars($lower, $upper, $number, $whitespace);
                    $result = $schema('hEl1o ☃W0rld');

                    expect($result)->toBe($expected);
                }
            });
        });

        describe('alpha', function() {
            it('returns alphabetic characters', function() {
                $schema = filter\intl\alpha();
                $result = $schema('☃W0rLd');

                expect($result)->toBe('WrLd');
            });
        });

        describe('alnum', function() {
            it('returns alphanumeric and numbers only', function() {
                $schema = filter\intl\alnum();
                $result = $schema('☃W0rLd');

                expect($result)->toBe('W0rLd');
            });
        });
    });
});