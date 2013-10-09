<?php

include 'plan.php';

class PlanTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers       ::type
     * @dataProvider testTypeProvider
     */
    public function testType($instance, $test1, $test2)
    {
        $validator = plan($instance);

        $this->assertEquals($test1, $validator($test1));
        $this->assertEquals($test2, $validator($test2));
    }

    public function testTypeProvider()
    {
        return array(
            array(boolean(), true, false),
            array(int(), 0, PHP_INT_MAX),
            array(float(), 0.0, 7.9999999999999991118),
            array(str(), 'hello', 'world'),

            // XXX Disabled for the moment
            //array(new ArrayType(), array(), array_fill(0, 666, '666')),
            //array(new ObjectType(), new stdClass(), new SplStack()),
        );
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage '123' is not integer
     */
    public function testTypeInvalid()
    {
        $validator = plan(int());
        $validator('123');
    }

    /**
     * @covers ::scalar
     */
    public function testScalar()
    {
        $str = plan('hello');
        $int = plan(1234567);

        $this->assertEquals('hello', $str('hello'));
        $this->assertEquals(1234567, $int(1234567));
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage 'world' is not 'hello'
     */
    public function testScalarInvalid()
    {
        $validator = plan('hello');
        $validator('world');
    }

    /**
     * @covers       ::seq
     * @dataProvider testSequenceProvider
     */
    public function testSequence($schema, $input)
    {
        $validator = plan($schema);
        $validated = $validator($input);

        $this->assertEquals($input, $validated);
    }

    public function testSequenceProvider()
    {
        return array(
            # Test 1: Values can be repeated by default
            array(array('foo', array('a' => 'b'), int()),
                  array('foo', 'foo', array('a' => 'b'), 123, 321)),

            # Test 2: Empty schema, allow any data
            array(array(),
                  array('123', 123, 'abc' => 'def')),
        );
    }

    /**
     * @covers                   ::seq
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage Invalid 'foobar' value
     */
    public function testSequenceInvalid()
    {
        $validator = plan(array('foo', 'bar'));
        $validator(array('foobar'));
    }

    /**
     * @covers       ::dict
     * @dataProvider testDictionaryProvider
     */
    public function testDictionary($schema, $input)
    {
        $validator = plan($schema);
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
            array(array('key' => str()),
                  array('key' => 'string')),

            # Test 4: Multidimensional array
            array(array('key' => array('foo' => 'bar')),
                  array('key' => array('foo' => 'bar'))),
        );
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage Required key c not provided
     */
    public function testDictionaryRequired()
    {
        $validator = dict(array('a' => 'b', 'c' => 'd'), true);
        $validator(array('a' => 'b', ));
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage Required key two not provided
     */
    public function testDictionaryRequiredArray()
    {
        $dict = array('one' => '1', 'two' => '2');

        $validator = dict($dict, array('two'));
        $validator(array());
    }

    /**
     * @covers ::dict
     */
    public function testDictionaryExtra()
    {
        $dict = array('foo' => 'foo', 'bar' => 'bar');

        $validator = dict(array('foo' => 'foo'), false, true);
        $validated = $validator($dict);

        $this->assertEquals($dict, $validated);
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage Extra keys not allowed
     */
    public function testDictionaryExtraInvalid()
    {
        $validator = dict(array('foo' => 'foo'), false, false);
        $validator(array('foo' => 'foo', 'bar' => 'bar'));
    }

    /**
     * @covers ::any
     */
    public function testAny()
    {
        $validator = plan(any('true', 'false', boolean()));

        $this->assertEquals('true', $validator('true'));
        $this->assertEquals('false', $validator('false'));
        $this->assertEquals(true, $validator(true));
        $this->assertEquals(false, $validator(false));
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage No valid value found
     */
    public function testAnyInvalid()
    {
        $validator = plan(any('true', 'false', boolean()));
        $validator(array('true'));
    }

    /**
     * @covers ::all
     */
    public function testAll()
    {
        $validator = plan(all(str(), 'string'));
        $validated = $validator('string');

        $this->assertEquals('string', $validated);
    }

    /**
     * @covers       ::not
     * @dataProvider testNotProvider
     */
    public function testNot($schema, $input)
    {
        $validator = plan(not($schema));
        $validated = $validator($input);

        $this->assertEquals($input, $validated);
    }

    public function testNotProvider()
    {
        return array(
            array(123, '123'),
            array(str(), 123),
            array(length(2, 4), array('a')),
            array(any(str(), int(), boolean()), array()),
            array(all(str(), length(2, 4)), array('a', 'b', 'c')),
            array(array(1, '1'), array(1, '1', 2, '2')),
        );
    }

    /**
     * @covers                   ::not
     * @dataProvider             testNotInvalidProvider
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage Validator passed
     */
    public function testNotInvalid($schema, $input)
    {
        $validator = plan(not($schema));
        $validator($input);
    }

    public function testNotInvalidProvider()
    {
        return array(
            array(123, 123),
            array(str(), 'string'),
            array(length(2, 4), array('a', 'b', 'c')),
            array(any(str(), int(), boolean()), true),
            array(all(str(), length(2, 4)), 'abc'),
            array(array(1, '1'), array(1, '1', 1, '1')),
        );
    }

    /**
     * @covers       ::length
     * @dataProvider testLengthProvider
     */
    public function testLength($input)
    {
        $validator = plan(length(2, 4));
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
        $validator = plan(validate($filter));
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
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage Validation of type 'validate_email' failed
     */
    public function testValidateInvalid()
    {
        $validator = plan(email());
        $validator('not an email');
    }
}
