<?php

include 'plan.php';

class PlanTest extends \PHPUnit_Framework_TestCase
{
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

    public function testType()
    {
        $validator = plan(new StringType());

        $this->assertEquals('hello', $validator('hello'));
        $this->assertEquals('world', $validator('world'));
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
     * @expectedExceptionMessage 123 is not of type string
     */
    public function testTypeInvalid()
    {
        $validator = plan(new StringType());
        $validator(123);
    }
}