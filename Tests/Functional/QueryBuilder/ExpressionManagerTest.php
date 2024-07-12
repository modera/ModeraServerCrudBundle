<?php

namespace Modera\ServerCrudBundle\Tests\Functional\QueryBuilder;

use Doctrine\ORM\Query\Expr\Join;
use Modera\ServerCrudBundle\QueryBuilder\ExpressionManager;
use Modera\ServerCrudBundle\QueryBuilder\Parsing\Expression;
use Modera\ServerCrudBundle\Tests\Functional\AbstractTestCase;
use Modera\ServerCrudBundle\Tests\Functional\DummyAddress;
use Modera\ServerCrudBundle\Tests\Functional\DummyCountry;
use Modera\ServerCrudBundle\Tests\Functional\DummyUser;

class ExpressionManagerTest extends AbstractTestCase
{
    private ExpressionManager $exprMgr;

    public function doSetUp(): void
    {
        $this->exprMgr = new ExpressionManager(DummyUser::class, self::$em);
    }

    public function testIsValidExpression()
    {
        $this->assertTrue($this->exprMgr->isValidExpression('address.zip'));
        $this->assertTrue($this->exprMgr->isValidExpression('address.street'));
        $this->assertTrue($this->exprMgr->isValidExpression('address.country'));
        $this->assertFalse($this->exprMgr->isValidExpression('address.foo'));

        $this->assertTrue($this->exprMgr->isValidExpression('address'));
        $this->assertTrue($this->exprMgr->isValidExpression('firstname'));
        $this->assertFalse($this->exprMgr->isValidExpression('bar'));
    }

    public function testResolveUnexistingAlias()
    {
        $this->assertNull($this->exprMgr->resolveAliasToExpression('jx'));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testAllocateAliasForNotExistingAssociation()
    {
        $this->exprMgr->allocateAlias('address.foo');
    }

    public function testAllocateAliasAndThenResolveAliasToExpression()
    {
        $alias = $this->exprMgr->allocateAlias('address.country');
        $this->assertNotNull($alias);
        $this->assertSame('address.country', $this->exprMgr->resolveAliasToExpression($alias));
    }

    public function testAllocateSeveralAliasesWhichShareTheSameRoot()
    {
        $countryAlias = $this->exprMgr->allocateAlias('address.country');
        $this->assertNotNull($countryAlias);

        $capitalAlias = $this->exprMgr->allocateAlias('address.country.capital');
        $this->assertNotNull($capitalAlias);

        $aliases = $this->exprMgr->getAllocatedAliasMap();
        $aliasesWitNoDuplicates = array_unique($aliases);

        $addressAlias = $this->exprMgr->allocateAlias('address');
        $this->assertNotNull($addressAlias);

        $this->assertEquals(count($aliases), count($aliasesWitNoDuplicates));
    }

    public function testGetDqlPropertyName()
    {
        $this->assertEquals('e.firstname', $this->exprMgr->getDqlPropertyName('firstname'));
        $this->assertEquals('j1.name', $this->exprMgr->getDqlPropertyName('address.country.name'));
        $this->assertEquals('j0.zip', $this->exprMgr->getDqlPropertyName('address.zip'));
    }

    /**
     * @group joining
     */
    public function testInjectJoins()
    {
        $qb = self::$em->createQueryBuilder();
        $qb->select('e')
           ->from(DummyUser::class, 'e');

        $addressCountryNameAlias = $this->exprMgr->getDqlPropertyName('address.country.name');
        $this->assertNotNull($addressCountryNameAlias);

        $this->exprMgr->injectJoins($qb);

        $dqlParts = $qb->getDQLParts();

        $this->assertArrayHasKey('e', $dqlParts['join']);
        $this->assertEquals(2, count($dqlParts['join']['e']));
        $this->assertEquals(3, count($dqlParts['select']));

        $injectedFetchAliases = array();
        foreach ($dqlParts['select'] as $select) {
            $injectedFetchAliases[] = (string)$select;
        };

        $this->assertTrue(
            in_array($this->exprMgr->resolveExpressionToAlias('address'), $injectedFetchAliases)
        );
        $this->assertTrue(
            in_array($this->exprMgr->resolveExpressionToAlias('address.country'), $injectedFetchAliases)
        );
    }

    public function testInjectJoinsWhenNoFetchingIsUsed()
    {
        $qb = self::$em->createQueryBuilder();
        $qb->select('e')
            ->from(DummyUser::class, 'e');

        $this->exprMgr->getDqlPropertyName('address.country.name');
        $this->exprMgr->injectJoins($qb, false);

        $dqlParts = $qb->getDQLParts();

        $this->assertEquals(2, count($dqlParts['join']['e']));
        $this->assertEquals(1, count($dqlParts['select']));
        $this->assertEquals($this->exprMgr->getRootAlias(), (string)$dqlParts['select'][0]);
    }

    /**
     * @group MPFE-839
     */
    public function testInjectJoinsWithOneSegment()
    {
        $qb = self::$em->createQueryBuilder();
        $qb->select('e')->from(DummyUser::class, 'e');

        $this->exprMgr->getDqlPropertyName('address.id');
        $this->exprMgr->getDqlPropertyName('creditCard.number');
        $this->exprMgr->injectJoins($qb, false);

        $dqlParts = $qb->getDQLParts();

        $this->assertEquals(2, count($dqlParts['join']['e']));
        $this->assertEquals(1, count($dqlParts['select']));
        $this->assertEquals($this->exprMgr->getRootAlias(), (string)$dqlParts['select'][0]);

        /* @var Join $ccJoin */
        $ccJoin = $dqlParts['join']['e'][1];

        $this->assertNotEquals('.', substr($ccJoin->getJoin(), 0, 1));
    }

    public function testInjectFetchJoins()
    {
        $qb = self::$em->createQueryBuilder();
        $qb->select('e')
            ->from(DummyUser::class, 'e');

        $this->exprMgr->injectFetchSelects($qb, array(new Expression('address.country')));

        $dqlParts = $qb->getDQLParts();

        $this->assertEquals(3, count($dqlParts['select']));
        $this->assertEquals(1, count($dqlParts['join']));
        $this->assertArrayHasKey($this->exprMgr->getRootAlias(), $dqlParts['join']);
        $this->assertEquals(2, count($dqlParts['join']['e']));
    }

    public function testGetMapping()
    {
        $addressMapping = $this->exprMgr->getMapping('address');
        $addressCountry  = $this->exprMgr->getMapping('address.country');
        $addressZip = $this->exprMgr->getMapping('address.zip');
        $firstname = $this->exprMgr->getMapping('firstname');

        $this->assertNotNull($addressMapping);
        $this->assertTrue(is_array($addressMapping));
        $this->assertArrayHasKey('targetEntity', $addressMapping);
        $this->assertEquals(DummyAddress::class, $addressMapping['targetEntity']);

        $this->assertNotNull($addressCountry);
        $this->assertTrue(is_array($addressCountry));
        $this->assertArrayHasKey('targetEntity', $addressCountry);
        $this->assertEquals(DummyCountry::class, $addressCountry['targetEntity']);

        $this->assertNotNull($addressZip);
        $this->assertTrue(is_array($addressZip));
        $this->assertArrayHasKey('fieldName', $addressZip);
        $this->assertEquals('zip', $addressZip['fieldName']);

        $this->assertNotNull($firstname);
        $this->assertTrue(is_array($firstname));
        $this->assertArrayHasKey('fieldName', $firstname);
        $this->assertEquals('firstname', $firstname['fieldName']);
    }

    public function testIsAssociation()
    {
        $this->assertTrue($this->exprMgr->isAssociation('address'));
        $this->assertTrue($this->exprMgr->isAssociation('address.country'));
        $this->assertFalse($this->exprMgr->isAssociation('address.zip'));
    }
}
