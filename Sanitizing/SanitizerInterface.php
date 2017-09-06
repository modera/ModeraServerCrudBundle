<?php

namespace Modera\ServerCrudBundle\Sanitizing;

/**
 * Implementation of this interface are used to sanitize (remove tainted data like HTML tags) data
 * after it has been hydrated and before it is sent back to client-side.
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2017 Modera Foundation
 */
interface SanitizerInterface
{
    /**
     * @param array  $hydratedResult
     * @param string $profile
     *
     * @return mixed
     */
    public function sanitize(array $hydratedResult, $profile);
}