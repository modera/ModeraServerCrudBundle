<?php

namespace Modera\ServerCrudBundle\Tests\Unit\NewValuesFactory;

use Modera\ServerCrudBundle\NewValuesFactory\DefaultNewValuesFactory;

class DummyEntity
{
}

class AnotherDummyEntity
{
    public static function formatNewValues(array $params, array $config)
    {
        return array(
            'params' => $params,
            'config' => $config,
        );
    }
}

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2014 Modera Foundation
 */
class DefaultNewValuesFactoryTest extends \PHPUnit\Framework\TestCase
{
    public function testGetValues()
    {
        $container = $this->createMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $nvf = new DefaultNewValuesFactory($container);

        $inputParams = array('input-params');
        $inputConfig = array('entity' => DummyEntity::class);

        $this->assertSame(array(), $nvf->getValues($inputParams, $inputConfig));

        // ---

        $inputConfig = array('entity' => AnotherDummyEntity::class);

        $expectedResult = array(
            'params' => $inputParams,
            'config' => $inputConfig,
        );

        $this->assertSame($expectedResult, $nvf->getValues($inputParams, $inputConfig));
    }
}
