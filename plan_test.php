<?php

include 'plan.php';

class PlanTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers ::type
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
            array(bool(), true, false),
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
     * @covers ArrayValidator::__invoke
     * @dataProvider testArrayProvider
     */
    public function testArray($in, $out)
    {
        $validator = plan($in);
        $result = $validator($out);

        $this->assertEquals($out, $result);
    }

    public function testArrayProvider()
    {
        return array(
            # Test 1: Keys are not required by default
            array(array('key' => 'value'),
                  array()),

            # Test 2: Literal value
            array(array('key' => 'value'),
                  array('key' => 'value')),

            # Test 3: Type value
            array(array('key' => new StringType()),
                  array('key' => 'string')),

            # Test 4: Multidimensional array
            array(array('key' => array('foo' => 'bar')),
                  array('key' => array('foo' => 'bar'))),
        );
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage Schema is not an array
     */
    public function testArrayNotArrayException()
    {
        new ArrayValidator(123);
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage Required key c not provided
     */
    public function testArrayRequired()
    {
        $validator = new ArrayValidator(array('a' => 'b', 'c' => 'd'), true);
        $validator(array('a' => 'b', ));
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage Required key two not provided
     */
    public function testArrayRequiredArray()
    {
        $array = array('one' => '1', 'two' => '2');

        $validator = new ArrayValidator($array, array('two'));
        $validator(array());
    }

    /**
     * @covers ArrayValidator::__invoke
     */
    public function testArrayExtra()
    {
        $validator = new ArrayValidator(array('foo' => 'foo'), false, true);
        $result = $validator(array('foo' => 'foo', 'bar' => 'bar'));

        $this->assertEquals(array('foo' => 'foo', 'bar' => 'bar'), $result);
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage Extra keys not allowed
     */
    public function testArrayExtraInvalid()
    {
        $validator = new ArrayValidator(array('foo' => 'foo'), false, false);
        $validator(array('foo' => 'foo', 'bar' => 'bar'));
    }

    /**
     * @covers ::seq
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
            # Test 1: One value is required
            array(array('foo', array('a' => 'b'), new IntegerType()),
                  array('foo')),

            # Test 2: Values can be repeated by default
            array(array('foo', array('a' => 'b'), new IntegerType()),
                  array('foo', 'foo', array('a' => 'b'), 123, 321)),

            # Test 3: Empty schema, allow any data
            array(array(),
                  array('123', 123, 'abc' => 'def')),
        );
    }
}