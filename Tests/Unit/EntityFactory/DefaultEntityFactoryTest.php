<?php

namespace Modera\ServerCrudBundle\Tests\Unit\EntityFactory;

use Modera\ServerCrudBundle\EntityFactory\DefaultEntityFactory;

class DummyClassWithNoMandatoryArgumentsConstructor
{
    public $arg1;

    public function __construct($arg1 = 'default-value')
    {
        $this->arg1 = $arg1;
    }
}

class DummyClassWithMandatoryConstructorArgs
{
    public $arg1 = 'foo';

    public function __construct($arg1)
    {
        $this->arg1 = $arg1;
    }
}

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class DefaultEntityFactoryTest extends \PHPUnit\Framework\TestCase
{
    /* @var DefaultEntityFactory */
    private $factory;

    public function setUp(): void
    {
        $this->factory = new DefaultEntityFactory();
    }

    public function testCreateWithoutConstructor()
    {
        $object = $this->factory->create(array(), array('entity' => 'stdClass'));

        $this->assertInstanceOf('stdClass', $object);
    }

    public function testCreateWithConstructorWithNoMandatoryParameters()
    {
        /* @var DummyClassWithNoMandatoryArgumentsConstructor $object */
        $object = $this->factory->create(array(), array('entity' => DummyClassWithNoMandatoryArgumentsConstructor::class));

        $this->assertInstanceOf(DummyClassWithNoMandatoryArgumentsConstructor::class, $object);
        $this->assertEquals('default-value', $object->arg1);
    }

    public function testCreateWithConstructorWithMandatoryParameters()
    {
        /* @var DummyClassWithMandatoryConstructorArgs $object */
        $object = $this->factory->create(array(), array('entity' => DummyClassWithMandatoryConstructorArgs::class));

        $this->assertInstanceOf(DummyClassWithMandatoryConstructorArgs::class, $object);
        $this->assertEquals('foo', $object->arg1);
    }
}
