<?php

namespace Modera\ServerCrudBundle\Tests\Unit\QueryBuilder\Parsing;

use Modera\ServerCrudBundle\QueryBuilder\Parsing\Filter;
use Modera\ServerCrudBundle\QueryBuilder\Parsing\OrFilter;

class OrFilterTest extends \PHPUnit\Framework\TestCase
{
    public function testHowWellItWorksWithValidInput()
    {
        $f = new OrFilter(array(
            array('property' => 'user.firstname', 'value' => 'like:Se%'),
            array('property' => 'user.lastname', 'value' => 'like:Li%')
        ));

        $this->assertTrue($f->isValid());

        $filters = $f->getFilters();

        $this->assertEquals(2, count($filters));
        $this->assertInstanceOf(Filter::class, $filters[0]);
        $this->assertInstanceOf(Filter::class, $filters[1]);
        $this->assertEquals('user.firstname', $filters[0]->getProperty());
        $this->assertEquals('like', $filters[0]->getComparator());
        $this->assertEquals('Se%', $filters[0]->getValue());
        $this->assertEquals('user.lastname', $filters[1]->getProperty());
        $this->assertEquals('like', $filters[1]->getComparator());
        $this->assertEquals('Li%', $filters[1]->getValue());

        $compiled = $f->compile();

        $this->assertTrue(is_array($compiled));

    }

    public function testHowWellBadInputIsHandled()
    {
        $f = new OrFilter(array(
            array('property' => 'firstname'),
            array('value' => 'like:foo%'),
            array('property' => 'lastname', 'value' => 'foo'),
            array('property' => 'id', 'value' => 'eq:1')
        ));

        $this->assertFalse($f->isValid());

        $filters = $f->getFilters();

        $this->assertEquals(4, count($filters));
    }
}
