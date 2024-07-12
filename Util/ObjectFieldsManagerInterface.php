<?php

namespace Modera\ServerCrudBundle\Util;

interface ObjectFieldsManagerInterface
{
    /**
     * @param mixed[] $args Values a getter method must be invoked with. Each element of the array will correspond
     *                      to argument of the method.
     *
     * @return mixed Mixed value
     */
    public function get(object $object, string $key, array $args = []);

    /**
     * @param mixed[] $args Values a setter method must be invoked with. Each element of the array will correspond
     *                      to argument of the method.
     *
     * @return mixed Mixed value
     */
    public function set(object $object, string $key, array $args = []);
}
