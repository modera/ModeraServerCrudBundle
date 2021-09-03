<?php

namespace Modera\ServerCrudBundle\Tests\Unit\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Modera\ServerCrudBundle\Persistence\DoctrineRegistryPersistenceHandler;
use Modera\ServerCrudBundle\Tests\Functional\DummyUser;
use Sli\ExtJsIntegrationBundle\QueryBuilder\ExtjsQueryBuilder;
use Symfony\Bridge\Doctrine\RegistryInterface;

class DummyAddress
{
    public $id;

    public function __construct($id = null)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }
}

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class DoctrineRegistryPersistenceHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testUpdateBatch()
    {
        $user1 = new DummyUser();
        $address1 = new DummyAddress('foo-address');
        $address2 = new DummyAddress('bar-address');

        $em1 = $this->createDummyEntityManager(); // responsible for user1
        $em2 = $this->createDummyEntityManager(); // responsible for address1, address2

        $registry = $this->createDummyRegistry(array(
            get_class($user1) => $em1,
            get_class($address1) => $em2,
        ));

        $handler = new DoctrineRegistryPersistenceHandler($registry, \Phake::mock(ExtjsQueryBuilder::class));

        $handler->updateBatch([$user1, $address1, $address2]);

        // calls to flush() are expected to be aggregated
        \Phake::verify($em1, \Phake::times(1))
            ->flush()
        ;
        \Phake::verify($em2, \Phake::times(1))
            ->flush()
        ;

        \Phake::verify($em1, \Phake::times(1))
            ->persist($user1)
        ;

        \Phake::verify($em2, \Phake::times(1))
            ->persist($address1)
        ;
        \Phake::verify($em2, \Phake::times(1))
            ->persist($address2)
        ;
    }

    public function testRemove()
    {
        $user1 = new DummyUser();
        $address1 = new DummyAddress('foo-address');
        $address2 = new DummyAddress('bar-address');

        $em1 = $this->createDummyEntityManager(); // responsible for user1
        $em2 = $this->createDummyEntityManager(); // responsible for address1, address2

        $registry = $this->createDummyRegistry(array(
            get_class($user1) => $em1,
            get_class($address1) => $em2,
        ));

        $handler = new DoctrineRegistryPersistenceHandler($registry, \Phake::mock(ExtjsQueryBuilder::class));

        $handler->remove([$user1, $address1, $address2]);

        // calls to flush() are expected to be aggregated
        \Phake::verify($em1, \Phake::times(1))
            ->flush()
        ;
        \Phake::verify($em2, \Phake::times(1))
            ->flush()
        ;

        \Phake::verify($em1, \Phake::times(1))
            ->remove($user1)
        ;

        \Phake::verify($em2, \Phake::times(1))
            ->remove($address1)
        ;
        \Phake::verify($em2, \Phake::times(1))
            ->remove($address2)
        ;
    }

    private function createDummyRegistry(array $classToEntityManagersMapping)
    {
        $r = \Phake::mock(RegistryInterface::class);

        $meta = \Phake::mock(ClassMetadata::class);
        \Phake::when($meta)
            ->getSingleIdentifierFieldName()
            ->thenReturn('id')
        ;

        foreach ($classToEntityManagersMapping as $entityClass => $em) {
            \Phake::when($r)
                ->getManagerForClass($entityClass)
                ->thenReturn($em)
            ;

            \Phake::when($em)
                ->getClassMetadata($entityClass)
                ->thenReturn($meta)
            ;
        }

        return $r;
    }

    private function createDummyEntityManager()
    {
        return \Phake::mock(EntityManagerInterface::class);
    }
}
