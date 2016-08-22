<?php


class ModeraServerCrudBundleAppKernel extends \Modera\FoundationBundle\Testing\AbstractFunctionalKernel
{
    public function registerBundles()
    {
        return array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),

            new Sli\AuxBundle\SliAuxBundle(),
            new Sli\ExtJsIntegrationBundle\SliExtJsIntegrationBundle(),

            new Modera\ServerCrudBundle\ModeraServerCrudBundle(),
            new Modera\ServerCrudBundle\Tests\Fixtures\Bundle\ModeraServerCrudDummyBundle(),
        );
    }
}
