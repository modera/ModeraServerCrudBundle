<?php

namespace Modera\ServerCrudBundle\Hydration;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
interface HydrationGroupInterface
{
    public function isAllowed(): bool;

    /**
     * @return array<string, mixed>
     */
    public function hydrate(object $entity): array;
}
