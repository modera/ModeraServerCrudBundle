<?php

namespace Modera\ServerCrudBundle\DataMapping\MethodInvocation;

/**
 * @author Sergei Vizel <sergei.vizel@modera.org>
 * @copyright 2024 Modera Foundation
 */
interface MethodInvocationParametersProviderInterface
{
    /**
     * @return array<?object>
     */
    public function getParameters(string $fqcn, string $methodName): array;
}
