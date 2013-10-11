<?php

include 'plan.php';

use plan\Schema as plan;
use plan\assert;

class PlanTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers       ::type
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
            array(assert\boolean(), true, false),
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
     * @covers       ::scalar
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
     * @covers ::literal
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
     * @covers ::instance
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
     * @covers       ::seq
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
     * @covers                   ::seq
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
            assert\any(assert\email(), assert\str()),
            array('name' => assert\str(), 'email' => assert\email()),
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
     * @covers       ::dict
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
     * @covers ::dict
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

    public function testDictionaryDeepException()
    {
        $validator = assert\dict(array(
            'email' => assert\email(),
            'extra' => array(
                'emails' => array(assert\email()),
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
     * @covers ::any
     */
    public function testAny()
    {
        $validator = new plan(assert\any('true', 'false', assert\boolean()));

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
        $validator = new plan(assert\any('true', 'false', assert\boolean()));
        $validator(array('true'));
    }

    /**
     * @covers ::all
     */
    public function testAll()
    {
        $validator = new plan(assert\all(assert\str(), 'string'));
        $validated = $validator('string');

        $this->assertEquals('string', $validated);
    }

    /**
     * @covers       ::not
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
            array(assert\any(assert\str(), assert\boolean()), array()),
            array(assert\all(assert\str(), assert\length(2, 4)), array('a', 'b', 'c')),
            array(array(1, '1'), array(1, '1', 2, '2')),
        );
    }

    /**
     * @covers                   ::not
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
            array(assert\any(assert\str(), assert\boolean()), true),
            array(assert\all(assert\str(), assert\length(2, 4)), 'abc'),
            array(array(1, '1'), array(1, '1', 1, '1')),
        );
    }

    /**
     * @covers       ::length
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
     * @covers       ::validate
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
        $validator = new plan(assert\email());
        $validator(123);
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
}
