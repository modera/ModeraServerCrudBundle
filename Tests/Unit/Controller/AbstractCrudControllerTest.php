<?php

namespace Modera\ServerCrudBundle\Tests\Unit\Controller;
use Modera\ServerCrudBundle\DataMapping\DataMapperInterface;
use Modera\ServerCrudBundle\DependencyInjection\ModeraServerCrudExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Modera\ServerCrudBundle\Controller\AbstractCrudController;
use Doctrine\Common\Util\Debug;

/**
 * @author    Alex Plaksin <alex.plaksin@modera.net>
 * @copyright 2016 Modera Foundation
 */
class AbstractCrudControllerTest extends \PHPUnit_Framework_TestCase
{
    public function testGetDataMapper_ContainerParameter()
    {
        $config = array('data_mapper' => 'configDefinedMapper');

        /** @var ContainerBuilder $container */
        $container = \Phake::partialMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $container->setParameter(ModeraServerCrudExtension::CONFIG_KEY, $config);
        $container->compile();

        \Phake::when($container)->get('configDefinedMapper')->thenReturn(true);

        /** @var AbstractCrudController $controller */
        $controller = \Phake::partialMock('Modera\ServerCrudBundle\Controller\AbstractCrudController');
        $controller->setContainer($container);

        \Phake::when($controller)->getConfig()->thenReturn(
            array( 'entity' => 'testValue', 'hydration' => 'testValue')
        );

        $this->assertTrue(\Phake::makeVisible($controller)->getDataMapper());

    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetDataMapper_InConfigParameter_ServiceNotPresentInDIContainer()
    {
        $config = array('data_mapper' => 'configDefinedMapper');

        /** @var ContainerBuilder $container */
        $container = \Phake::partialMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $container->setParameter(ModeraServerCrudExtension::CONFIG_KEY, $config);
        $container->compile();

        \Phake::when($container)->get('configDefinedMapper')->thenReturn(true);

        /** @var AbstractCrudController $controller */
        $controller = \Phake::partialMock('Modera\ServerCrudBundle\Controller\AbstractCrudController');
        $controller->setContainer($container);

        \Phake::when($controller)->getConfig()->thenReturn(
            array('data_mapper' => 'nonExistingService', 'entity' => 'testValue', 'hydration' => 'testValue')
        );

        \Phake::makeVisible($controller)->getDataMapper();
    }

    public function testGetDataMapper_InConfigParameter_AllOk()
    {
        $config = array('data_mapper' => 'configDefinedMapper');

        /** @var ContainerBuilder $container */
        $container = \Phake::partialMock('Symfony\Component\DependencyInjection\ContainerBuilder');
        $container->setParameter(ModeraServerCrudExtension::CONFIG_KEY, $config);
        $container->compile();

        \Phake::when($container)->get('configDefinedMapper')->thenReturn(false);
        \Phake::when($container)->has('existingService')->thenReturn(true);
        \Phake::when($container)->get('existingService')->thenReturn(true);

        /** @var AbstractCrudController $controller */
        $controller = \Phake::partialMock('Modera\ServerCrudBundle\Controller\AbstractCrudController');
        $controller->setContainer($container);

        \Phake::when($controller)->getConfig()->thenReturn(
            array('data_mapper' => 'existingService', 'entity' => 'testValue', 'hydration' => 'testValue')
        );

        $this->assertTrue(\Phake::makeVisible($controller)->getDataMapper());
    }
}
