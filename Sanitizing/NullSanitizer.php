<?php

namespace Modera\ServerCrudBundle\Sanitizing;

/**
 * @internal
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2017 Modera Foundation
 */
class NullSanitizer implements SanitizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function sanitize(array $hydratedResult, $profile)
    {
        return $hydratedResult;
    }
}