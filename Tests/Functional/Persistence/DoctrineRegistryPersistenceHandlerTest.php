<?php

namespace Modera\ServerCrudBundle\Tests\Functional\Persistence;

require_once __DIR__.'/../../Fixtures/Entity/entities.php';

use Modera\ServerCrudBundle\Persistence\DoctrinePersistenceHandler;
use Modera\ServerCrudBundle\Persistence\DoctrineRegistryPersistenceHandler;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class DoctrineRegistryPersistenceHandlerTest extends DoctrinePersistenceHandlerTest
{
    /**
     * @return DoctrinePersistenceHandler
     */
    protected function getHandler()
    {
        return self::$container->get('modera_server_crud.persistence.doctrine_registry_handler');
    }

    public function testServiceExistence()
    {
        $this->assertInstanceOf(DoctrineRegistryPersistenceHandler::clazz(), $this->getHandler());
    }
}
