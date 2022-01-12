# ModeraServerCrudBundle

The bundle provides a set of tools that simplifies building applications which need to operate with data coming
from client-side. These operations are supported:

 * Creating new records
 * Validating data ( both Symfony validation and domain validation )
 * Querying data - single record, batch
 * Removing record(s)
 * Getting default values that can be used on client-side as a template for a new record

What this bundle does:

 * Provides a super-type controller that you can inherit from to harness power of all aforementioned operations
 * Integrates a powerful querying language where you define queries using JSON - now you can safely build queries
   on client-side
 * Hydration package - this component provides a nice way of converting your entities to data-structure that can
   be understood by client-side logic
 * Provides a simple yet powerful client-server communication protocol
 * Simplifies functional testing of your controller

## Installation

### Step 1: Download the Bundle

``` bash
composer require modera/server-crud-bundle:4.x-dev
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md) of the Composer documentation.

### Step 2: Enable the Bundle

This bundle should be automatically enabled by [Flex](https://symfony.com/doc/current/setup/flex.html).
In case you don't use Flex, you'll need to manually enable the bundle by
adding the following line in the `config/bundles.php` file of your project:

``` php
<?php
// config/bundles.php

return [
    // ...
    Modera\ServerCrudBundle\ModeraServerCrudBundle::class => ['all' => true],
];
```

## Documentation

For detailed documentation describing how to use this bundle and its components please read `Resources/doc/index.md`.

## Licensing

This bundle is under the MIT license. See the complete license in the bundle:
Resources/meta/LICENSE
