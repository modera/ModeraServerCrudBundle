<?php

namespace Modera\ServerCrudBundle\Tests\Functional;

require_once __DIR__.'/../Fixtures/Entity/entities.php';

use Doctrine\ORM\Tools\SchemaTool;
use Modera\FoundationBundle\Testing\FunctionalTestCase;

abstract class AbstractTestCase extends FunctionalTestCase
{
    /**
     * @var SchemaTool
     */
    private static $st;

    public static function doSetUpBeforeClass(): void
    {
        self::$st = new SchemaTool(self::$em);
        self::$st->createSchema([
            self::$em->getClassMetadata(DummyUser::class),
            self::$em->getClassMetadata(DummyGroup::class),
            self::$em->getClassMetadata(DummyCreditCard::class),
            self::$em->getClassMetadata(DummyAddress::class),
            self::$em->getClassMetadata(DummyCountry::class),
            self::$em->getClassMetadata(DummyCity::class),
            self::$em->getClassMetadata(DummyNote::class),
            self::$em->getClassMetadata(DummyOrder::class),
        ]);
    }

    public static function doTearDownAfterClass(): void
    {
        self::$st->dropSchema([
            self::$em->getClassMetadata(DummyUser::class),
            self::$em->getClassMetadata(DummyGroup::class),
            self::$em->getClassMetadata(DummyCreditCard::class),
            self::$em->getClassMetadata(DummyAddress::class),
            self::$em->getClassMetadata(DummyCountry::class),
            self::$em->getClassMetadata(DummyCity::class),
            self::$em->getClassMetadata(DummyNote::class),
            self::$em->getClassMetadata(DummyOrder::class),
        ]);
    }

    public static function createUsers(): void
    {
        $adminsGroup = new DummyGroup();
        $adminsGroup->name = 'admins';
        self::$em->persist($adminsGroup);

        $users = [];
        foreach (['john doe', 'jane doe', 'vassily pupkin'] as $fullname) {
            $exp = \explode(' ', $fullname);
            $user = new DummyUser();
            $user->firstname = $exp[0];
            $user->lastname = $exp[1];

            if ('john' == $exp[0]) {
                $adminsGroup->addUser($user);

                $hourPlus = new \DateTime('now');
                $hourPlus = $hourPlus->modify('+1 hour');
                $user->updatedAt = $hourPlus;

                $address = new DummyAddress();
                $address->country = new DummyCountry();
                $address->country->name = 'A';
                $address->street = 'foofoo';
                $address->zip = '1010';

                $user->address = $address;
            } else if ('jane' == $exp[0]) {
                $address = new DummyAddress();
                $address->country = new DummyCountry();
                $address->country->name = 'B';
                $address->zip = '2020';
                $address->street = 'Blahblah';

                $user->address = $address;
            }

            self::$em->persist($user);
            $users[] = $user;
        }

        $o1 = new DummyOrder();
        $o1->number = 'ORDER-1';
        $o1->user = $users[0];
        self::$em->persist($o1);

        $o2 = new DummyOrder();
        $o2->number = 'ORDER-2';
        $o2->user = $users[1];
        self::$em->persist($o2);

        self::$em->flush();
    }
}
