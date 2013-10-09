<?php

include 'plan.php';

class PlanTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers CallableValidator::__invoke
     */
    public function testCallable()
    {
        $validator = plan(function($data)
        {
            $type = new StringType();
            $data = $type($data);

            if (strtolower($data) !== $data) {
                throw new \UnexpectedValueException(
                    sprintf('%s is not lowercase', var_export($data, true))
                );
            }

            return $data;
        });

        $this->assertEquals('hello', $validator('hello'));
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage Schema is not callable
     */
    public function testCallableException()
    {
        new CallableValidator('hello');
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
     * @covers SequenceValidator::__invoke
     * @dataProvider testSequenceProvider
     */
    public function testSequence($in, $out)
    {
        $validator = plan($in);
        $result = $validator($out);

        $this->assertEquals($out, $result);
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

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage Schema is not an array
     */
    public function testSequenceNotArrayException()
    {
        new SequenceValidator(123);
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage Schema is not a sequence
     */
    public function testSequenceNotSequenceException()
    {
        new SequenceValidator(array('foo' => 'bar'));
    }

    /**
     * @covers Type::__invoke
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
            array(new BooleanType(), true, false),
            array(new IntegerType(), 0, PHP_INT_MAX),
            array(new DoubleType(), 0.0, 7.9999999999999991118),
            array(new StringType(), 'hello', 'world'),
            array(new ArrayType(), array(), array_fill(0, 666, '666')),
            array(new ObjectType(), new stdClass(), new SplStack()),
        );
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage Unknown type ravioli
     */
    public function testTypeUnknown()
    {
        plan(new Type('ravioli'));
    }

    /**
     * @expectedException        \UnexpectedValueException
     * @expectedExceptionMessage '123' is not of type integer
     */
    public function testTypeInvalid()
    {
        $validator = plan(new IntegerType());
        $validator('123');
    }
}