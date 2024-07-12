<?php

namespace Modera\ServerCrudBundle\Hydration;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class UnknownHydrationGroupException extends \RuntimeException
{
    private ?string $groupName = null;

    public function setGroupName(string $groupName): void
    {
        $this->groupName = $groupName;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }
}
