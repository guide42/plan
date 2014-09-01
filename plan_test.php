<?php

include 'plan.php';

use plan\Schema as plan;
use plan\Invalid;
use plan\InvalidList;
use plan\assert;
use plan\filter;

class PlanTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers       ::plan\assert\type
     * @dataProvider testTypeProvider
     */
    public function testType($instance, $test1, $test2)
    {
        $validator = new plan($instance);

        $this->assertEquals($test1, $validator($test1));
        $this->assertEquals($test2, $validator($test2));
    }

    public function testTypeProvider()
    {
        return array(
            array(assert\bool(), true, false),
            array(assert\int(), 0, PHP_INT_MAX),
            array(assert\float(), 0.0, 7.9999999999999991118),
            array(assert\str(), 'hello', 'world'),

            // XXX Disabled for the moment
            //array(new ArrayType(), array(), array_fill(0, 666, '666')),
            //array(new ObjectType(), new stdClass(), new SplStack()),
        );
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["\"123\" is not integer"]
     */
    public function testTypeInvalid()
    {
        $validator = new plan(assert\int());
        $validator('123');
    }

    /**
     * @covers       ::plan\assert\scalar
     * @dataProvider testScalarProvider
     */
    public function testScalar($test1, $test2)
    {
        $validator = new plan(assert\scalar());

        $this->assertEquals($test1, $validator($test1));
        $this->assertEquals($test2, $validator($test2));
    }

    public function testScalarProvider()
    {
        return array(
            array(true, false),
            array(0, PHP_INT_MAX),
            array(0.0, 7.9999999999999991118),
            array('hello', 'world'),
        );
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["[] is not scalar"]
     */
    public function testScalarInvalid()
    {
        $validator = new plan(assert\scalar());
        $validator(array());
    }

    /**
     * @covers ::plan\assert\literal
     */
    public function testLiteral()
    {
        $str = new plan('hello');
        $int = new plan(1234567);

        $this->assertEquals('hello', $str('hello'));
        $this->assertEquals(1234567, $int(1234567));
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["\"world\" is not \"hello\""]
     */
    public function testLiteralInvalid()
    {
        $validator = new plan('hello');
        $validator('world');
    }

    /**
     * @covers ::plan\assert\instance
     */
    public function testInstance()
    {
        $object = new \stdClass();

        $validator = new plan(assert\instance('\stdClass'));
        $validated = $validator($object);

        $this->assertEquals($object, $validated);
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["Expected \\stdClass (is ArrayObject)"]
     */
    public function testInstanceInvalid()
    {
        $validator = new plan(assert\instance('\stdClass'));
        $validator(new \ArrayObject());
    }

    /**
     * @covers       ::plan\assert\seq
     * @dataProvider testSequenceProvider
     */
    public function testSequence($schema, $input)
    {
        $validator = new plan($schema);
        $validated = $validator($input);

        $this->assertEquals($input, $validated);
    }

    public function testSequenceProvider()
    {
        return array(
            # Test 1: Values can be repeated by default
            array(array('foo', array('a' => 'b'), assert\int()),
                  array('foo', 'foo', array('a' => 'b'), 123, 321)),

            # Test 2: Empty schema, allow any data
            array(array(),
                  array('123', 123, 'abc' => 'def')),
        );
    }

    /**
     * @covers                   ::plan\assert\seq
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["Invalid value at index 0 (value is \"foobar\")"]
     */
    public function testSequenceInvalid()
    {
        $validator = new plan(array('foo', 'bar'));
        $validator(array('foobar'));
    }

    public function testSequenceDeepException()
    {
        $validator = assert\seq(array(
            assert\any(assert\validate('validate_email'), assert\str()),
            array('name' => assert\str(), 'email' => assert\validate('validate_email')),
        ));

        try {
            $validator(array(
                array('name' => 'John', 'email' => 'john@example.org'),
                array('name' => 'Jane', 'email' => 'ERROR'),
                'Other Doe <other@example.org>',
            ));
        } catch (\plan\Invalid $e) {
            $this->assertEquals('Invalid value at key email (value is "ERROR")', $e->getMessage());
            $this->assertEquals(array(1, 'email'), $e->getPath());

            return;
        }

        $this->fail('Exception Invalid not thrown');
    }

    /**
     * @covers       ::plan\assert\dict
     * @dataProvider testDictionaryProvider
     */
    public function testDictionary($schema, $input)
    {
        $validator = new plan($schema);
        $validated = $validator($input);

        $this->assertEquals($input, $validated);
    }

    public function testDictionaryProvider()
    {
        return array(
            # Test 1: Keys are not required by default
            array(array('key' => 'value'),
                  array()),

            # Test 2: Literal value
            array(array('key' => 'value'),
                  array('key' => 'value')),

            # Test 3: Type value
            array(array('key' => assert\str()),
                  array('key' => 'string')),

            # Test 4: Multidimensional array
            array(array('key' => array('foo' => 'bar')),
                  array('key' => array('foo' => 'bar'))),
        );
    }

    /**
     * @expectedException        \plan\Invalid
     * @expectedExceptionMessage Required key c not provided
     */
    public function testDictionaryRequired()
    {
        $validator = assert\dict(array('a' => 'b', 'c' => 'd'), true);
        $validator(array('a' => 'b', ));
    }

    /**
     * @expectedException        \plan\Invalid
     * @expectedExceptionMessage Required key two not provided
     */
    public function testDictionaryRequiredArray()
    {
        $dict = array('one' => '1', 'two' => '2');

        $validator = assert\dict($dict, array('two'));
        $validator(array());
    }

    /**
     * @covers ::plan\assert\dict
     */
    public function testDictionaryExtra()
    {
        $dict = array('foo' => 'foo', 'bar' => 'bar');

        $validator = assert\dict(array('foo' => 'foo'), false, true);
        $validated = $validator($dict);

        $this->assertEquals($dict, $validated);
    }

    /**
     * @expectedException        \plan\Invalid
     * @expectedExceptionMessage Extra key bar not allowed
     */
    public function testDictionaryExtraInvalid()
    {
        $validator = assert\dict(array('foo' => 'foo'), false, false);
        $validator(array('foo' => 'foo', 'bar' => 'bar'));
    }

    /**
     * @covers ::plan\assert\dict
     */
    public function testDictionaryExtraArray()
    {
        $dict = array('foo' => 'foo', 'bar' => 'bar');

        $validator = assert\dict(array('foo' => 'foo'), false, array('bar'));
        $validated = $validator($dict);

        $this->assertEquals($dict, $validated);
    }

    /**
     * @expectedException        \plan\Invalid
     * @expectedExceptionMessage Extra key bar not allowed
     */
    public function testDictionaryExtraArrayInvalid()
    {
        $validator = assert\dict(array(), false, array('foo'));
        $validator(array('foo' => 'foo', 'bar' => 'bar'));
    }

    public function testDictionaryDeepException()
    {
        $validator = assert\dict(array(
            'email' => assert\validate('validate_email'),
            'extra' => array(
                'emails' => array(assert\validate('validate_email')),
            ),
        ));

        try {
            $validator(array(
                'email' => 'john@example.org',
                'extra' => array(
                    'emails' => array('ERROR', 'mysecondemail@ymail.com')
                )
            ));
        } catch (\plan\Invalid $e) {
            $this->assertEquals('Invalid value at index 0 (value is "ERROR")', $e->getMessage());
            $this->assertEquals(array('extra', 'emails', 0), $e->getPath());

            return;
        }

        $this->fail('Exception Invalid not thrown');
    }

    /**
     * @covers ::plan\assert\any
     */
    public function testAny()
    {
        $validator = new plan(assert\any('true', 'false', assert\bool()));

        $this->assertEquals('true', $validator('true'));
        $this->assertEquals('false', $validator('false'));
        $this->assertEquals(true, $validator(true));
        $this->assertEquals(false, $validator(false));
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["No valid value found"]
     */
    public function testAnyInvalid()
    {
        $validator = new plan(assert\any('true', 'false', assert\bool()));
        $validator(array('true'));
    }

    /**
     * @covers ::plan\assert\all
     */
    public function testAll()
    {
        $validator = new plan(assert\all(assert\str(), 'string'));
        $validated = $validator('string');

        $this->assertEquals('string', $validated);
    }

    /**
     * @covers       ::plan\assert\not
     * @dataProvider testNotProvider
     */
    public function testNot($schema, $input)
    {
        $validator = new plan(assert\not($schema));
        $validated = $validator($input);

        $this->assertEquals($input, $validated);
    }

    public function testNotProvider()
    {
        return array(
            array(123, '123'),
            array(assert\str(), 123),
            array(assert\length(2, 4), array('a')),
            array(assert\any(assert\str(), assert\bool()), array()),
            array(assert\all(assert\str(), assert\length(2, 4)), array('a', 'b', 'c')),
            array(array(1, '1'), array(1, '1', 2, '2')),
        );
    }

    /**
     * @covers                   ::plan\assert\not
     * @dataProvider             testNotInvalidProvider
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["Validator passed"]
     */
    public function testNotInvalid($schema, $input)
    {
        $validator = new plan(assert\not($schema));
        $validator($input);
    }

    public function testNotInvalidProvider()
    {
        return array(
            array(123, 123),
            array(assert\str(), 'string'),
            array(assert\length(2, 4), array('a', 'b', 'c')),
            array(assert\any(assert\str(), assert\bool()), true),
            array(assert\all(assert\str(), assert\length(2, 4)), 'abc'),
            array(array(1, '1'), array(1, '1', 1, '1')),
        );
    }

    /**
     * @covers       ::plan\assert\length
     * @dataProvider testLengthProvider
     */
    public function testLength($input)
    {
        $validator = new plan(assert\length(2, 4));
        $validated = $validator($input);

        $this->assertEquals($input, $validated);
    }

    public function testLengthProvider()
    {
        return array(
            array('abc'),
            array(array('a', 'b', 'c')),
        );
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["Value must be at least 40"]
     */
    public function testLengthInvalidMin()
    {
        $validator = new plan(assert\length(40));
        $validator('Hello World');
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["Value must be at most 4"]
     */
    public function testLengthInvalidMax()
    {
        $validator = new plan(assert\length(2, 4));
        $validator('Hello World');
    }

    /**
     * @covers       ::plan\assert\validate
     * @dataProvider testValidateProvider
     */
    public function testValidate($filter, $test)
    {
        $validator = new plan(assert\validate($filter));
        $validated = $validator($test);

        $this->assertEquals($test, $validated);
    }

    public function testValidateProvider()
    {
        return array(
            array('int', '1234567'),
            array('boolean', 'true'),
            array('float', '7.9999999999999991118'),
            array('validate_url', 'http://www.example.org/'),
            array('validate_email', 'john@example.org'),
            array('validate_ip', '10.0.2.42'),
        );
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["Validation validate_email for 123 failed"]
     */
    public function testValidateInvalid()
    {
        $validator = new plan(assert\validate('validate_email'));
        $validator(123);
    }

    /**
     * @covers       ::plan\filter\type
     * @dataProvider testTypeFilterProvider
     */
    public function testTypeFilter($type, $expected, $test)
    {
        $validator = new plan(filter\type($type));
        $result = $validator($test);

        $this->assertEquals($expected, $result);
    }

    public function testTypeFilterProvider()
    {
        return array(
            array('bool', true, 'something true'),
            array('integer', 678, '0678 people are wrong'),
            array('float', 3.14, '3.14 < pi'),
            array('string', '3.1415926535898', pi()),
        );
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["Cannot cast \"123\" into unknown type"]
     */
    public function testTypeFilterInvalid()
    {
        $validator = new plan(filter\type('unknown type'));
        $validator('123');
    }

    /**
     * @covers       ::plan\filter\boolval
     * @dataProvider testBooleanProvider
     */
    public function testBoolean($expected, $test1, $test2, $test3)
    {
        $validator = new plan(filter\boolval());

        $this->assertEquals($expected, $validator($test1));
        $this->assertEquals($expected, $validator($test2));
        $this->assertEquals($expected, $validator($test3));
    }

    public function testBooleanProvider()
    {
        return array(
            array(true, array(1), 'true', new \stdClass()),
            array(false, array(), '', '0'),
        );
    }

    /**
     * @covers       ::plan\filter\intval
     * @dataProvider testIntegerProvider
     */
    public function testInteger($expected, $test1, $test2, $test3)
    {
        $validator = new plan(filter\intval());

        $this->assertEquals($expected, $validator($test1));
        $this->assertEquals($expected, $validator($test2));
        $this->assertEquals($expected, $validator($test3));
    }

    public function testIntegerProvider()
    {
        return array(
            array(42, '42', '042', '42e10'),
            array(34, '+34', 042, 0x22),
        );
    }

    /**
     * @covers       ::plan\filter\floatval
     * @dataProvider testFloatProvider
     */
    public function testFloat($expected, $test1, $test2, $test3)
    {
        $validator = new plan(filter\floatval());

        $this->assertEquals($expected, $validator($test1));
        $this->assertEquals($expected, $validator($test2));
        $this->assertEquals($expected, $validator($test3));
    }

    public function testFloatProvider()
    {
        return array(
            array(0, 'PI = 3.14', '$ 19.332,35-', '0,76'),
            array(1.999, '1.999,369', '0001.999', '1.99900000000000000000009'),
        );
    }

    /**
     * @covers       ::plan\filter\sanitize
     * @dataProvider testSanitizeProvider
     */
    public function testSanitize($filter, $expected, $invalid)
    {
        $validator = new plan(filter\sanitize($filter));
        $validated = $validator($invalid);

        $this->assertEquals($expected, $validated);
    }

    public function testSanitizeProvider()
    {
        return array(
            array('url', 'example.org', 'example¶.org'),
            array('email', 'john@example.org', '(john)@example¶.org'),
        );
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["Sanitization asd for \"asd\" failed"]
     */
    public function testSanitizeInvalid()
    {
        $validator = new plan(filter\sanitize('asd'));
        $validator('asd');
    }

    /**
     * @covers       ::plan\filter\vars
     * @dataProvider testVarsProvider
     */
    public function testVarsObject($recursive, $inscope, $expected, $object, $fix=null)
    {
        $validator = new plan(filter\vars($recursive, $inscope));
        $validated = $validator($object);

        if (\is_callable($fix)) {
            $fix($validated);
        }

        $this->assertEquals($expected, $validated);
    }

    public function testVarsProvider()
    {
        $tests = array();

        $arr = array(
            'name' => 'John',
            'age' => null,
            'dog' => array(
                'name' => 'Einstein',
            ),
        );

        $obj = new \stdClass();
        $obj->name = 'John';
        $obj->age = null;
        $obj->dog = new \stdClass();
        $obj->dog->name = 'Einstein';

        $tests[] = array(true, true, $arr, $obj);

        $arr = array('message' => 'ok', 'code' => 42, 'string' => '', 'file' => __FILE__, 'line' => __LINE__ + 1, 'previous' => null);
        $obj = new \Exception('ok', 42);

        $tests[] = array(false, false, $arr, $obj, function(&$array) {
            // Because we cannot preview the stacktrace,
            // is "fix" it by removing it from the
            // result array.
            unset($array['trace']);
        });

        return $tests;
    }

    /**
     * @expectedException        \plan\InvalidList
     * @expectedExceptionMessage Multiple invalid: ["Invalid value at key age (value is \"18 years old\")","Extra key sex not allowed","Required key name not provided"]
     */
    public function testMultipleInvalid()
    {
        $validator = assert\dict(array(
            'name' => assert\str(),
            'age'  => assert\int()), true);
        $validator(array(
            'age' => '18 years old',
            'sex' => 'female',
        ));
    }

    public function testCustomValidator()
    {
        $passwordStrength = function($data, $path=null)
        {
            $type = assert\str();
            $data = $type($data);

            $errors = array();

            if (strlen($data) < 8) {
                $errors[] = new Invalid(
                    'Password must be at least 8 characters'
                );
            }

            if (!preg_match('/[A-Z]/', $data)) {
                $errors[] = new Invalid(
                    'Password must contain at least one uppercase letter'
                );
            }

            if (!preg_match('/[a-z]/', $data)) {
                $errors[] = new Invalid(
                    'Password must contain at least one lowercase letter'
                );
            }

            if (!preg_match('/\d/', $data)) {
                $errors[] = new Invalid(
                    'Password must contain at least one digit'
                );
            }

            if (count($errors) > 0) {
                throw new InvalidList($errors);
            }

            return $data;
        };

        $validator = new plan($passwordStrength);
        $validated = $validator('heLloW0rld');

        try {
            $validator('badpwd');
        } catch (InvalidList $e) {
            $this->assertEquals(
                'Multiple invalid: ["Password must be at least 8 characters",' .
                                   '"Password must contain at least one uppercase letter",' .
                                   '"Password must contain at least one digit"]'
                , $e->getMessage()
            );
        }
    }
}
