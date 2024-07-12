<?php

namespace Modera\ServerCrudBundle\Tests\Unit\QueryBuilder\Parsing;

use Modera\ServerCrudBundle\QueryBuilder\Parsing\OrderExpression;

class OrderExpressionTest extends \PHPUnit\Framework\TestCase
{
    public function testParsingBasics()
    {
        $expr = new OrderExpression(array('property' => 'foo', 'direction' => 'ASC'));

        $this->assertEquals('foo', $expr->getProperty());
        $this->assertEquals('ASC', $expr->getDirection());

        $expr = new OrderExpression(array());

        $this->assertNull($expr->getProperty());
        $this->assertNull($expr->getDirection());
    }

    public function testValidation()
    {
        $expr = new OrderExpression(array('property' => 'foo', 'direction' => 'ASC'));

        $this->assertTrue($expr->isValid());

        $expr = new OrderExpression(array('property' => 'foo.bar', 'direction' => 'DESC'));

        $this->assertTrue($expr->isValid());

        $expr = new OrderExpression(array('property' => 'foo', 'direction' => 'XXX'));

        $this->assertFalse($expr->isValid());

        $expr = new OrderExpression(array());

        $this->assertFalse($expr->isValid());

        $expr = new OrderExpression(array('property' => null, 'direction' => null));

        $this->assertFalse($expr->isValid());

        $expr = new OrderExpression(array('property' => 'foo'));

        $this->assertFalse($expr->isValid());

        $expr = new OrderExpression(array('direction' => 'ASC'));

        $this->assertFalse($expr->isValid());

        $expr = new OrderExpression(array('property' => 'xxx', 'direction' => 'asc'));

        $this->assertTrue($expr->isValid());
    }
}
