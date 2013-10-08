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

    public function testScalarInvalid()
    {
        $this->setExpectedException('InvalidException',
                                    '\'world\' is not \'hello\'');

        $validator = plan('hello');
        $validator('world');
    }

    public function testType()
    {
        $validator = plan(new StringType());

        $this->assertEquals('hello', $validator('hello'));
        $this->assertEquals('world', $validator('world'));
    }

    public function testTypeUnknown()
    {
        $this->setExpectedException('SchemaException', 'Unknown type ravioli');

        plan(new Type('ravioli'));
    }

    public function testTypeInvalid()
    {
        $this->setExpectedException('InvalidException',
                '123 is not of type string');

        $validator = plan(new StringType());
        $validator(123);
    }
}