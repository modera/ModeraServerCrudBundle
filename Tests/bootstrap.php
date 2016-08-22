<?php

use Doctrine\Common\Annotations\AnnotationRegistry;

$loaderFiles = [
    __DIR__.'/../vendor/autoload.php',
    __DIR__.'/../../../../vendor/autoload.php', // monolith repository
];

$enabledLoaderFile = null;
foreach ($loaderFiles as $loaderFile) {
    if (file_exists($loaderFile)) {
        $enabledLoaderFile = $loaderFile;

        break;
    }
}

if (!$enabledLoaderFile) {
    throw new \LogicException('Unable to find loader files, looked in these locations: '.implode(', ', $loaderFiles));
}

/* @var \Composer\Autoload\ClassLoader $loader */
$loader = require $loaderFile;

$loader->addPsr4('Modera\ServerCrudBundle\Tests\Fixtures\Bundle\\', __DIR__.'/Fixtures/Bundle');

AnnotationRegistry::registerLoader(array($loader, 'loadClass'));
