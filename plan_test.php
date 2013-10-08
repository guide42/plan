<?php

include 'plan.php';

class PlanTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers ScalarValidator::__invoke
     */
    public function testScalar()
    {
        $validator = plan('hello');
        $result = $validator('hello');

        $this->assertEquals('hello', $result);
    }

    /**
     * @expectedException        InvalidException
     * @expectedExceptionMessage 'world' is not 'hello'
     */
    public function testScalarInvalid()
    {
        $validator = plan('hello');
        $validator('world');
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
     * @expectedException        SchemaException
     * @expectedExceptionMessage Unknown type ravioli
     */
    public function testTypeUnknown()
    {
        plan(new Type('ravioli'));
    }

    /**
     * @expectedException        InvalidException
     * @expectedExceptionMessage '123' is not of type integer
     */
    public function testTypeInvalid()
    {
        $validator = plan(new IntegerType());
        $validator('123');
    }
}