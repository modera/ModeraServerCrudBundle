<?php

namespace Modera\ServerCrudBundle\Tests\Unit\QueryBuilder\ResolvingAssociatedModelSortingField;

use Modera\ServerCrudBundle\QueryBuilder\ResolvingAssociatedModelSortingField\MutableSortingFieldResolver;

class MutableSortingFieldResolverTest extends \PHPUnit\Framework\TestCase
{
    public function testAddAndResolveMethods()
    {
        $s = new MutableSortingFieldResolver();

        $this->assertNull($s->resolve('foo', 'blah'));

        $s->add('FooEntity', 'fooProperty', 'result-yo');

        $this->assertEquals('result-yo', $s->resolve('FooEntity', 'fooProperty'));

        $this->assertNull($s->resolve('FooEntity', 'barProperty'));

        $s->add('FooEntity', 'barProperty', 'result-yo2');

        $this->assertEquals('result-yo2', $s->resolve('FooEntity', 'barProperty'));
    }
}
