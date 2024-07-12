<?php

namespace Modera\ServerCrudBundle\Hydration;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class HydrationProfile implements HydrationProfileInterface
{
    private bool $isGroupingNeeded = true;

    /**
     * @var string[]
     */
    private array $groups = [];

    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    // fluent interface:

    public static function create(bool $isGroupingNeeded = true): self
    {
        $me = new self();
        $me->useGrouping($isGroupingNeeded);

        return $me;
    }

    public function isGroupingNeeded(): bool
    {
        return $this->isGroupingNeeded;
    }

    /**
     * @param string[] $groups
     */
    public function useGroups(array $groups): self
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * If TRUE then serialized data will be grouped under "profile-names". See Resources/doc/index.md for more details.
     */
    public function useGrouping(bool $isGroupingNeeded): self
    {
        if (!\in_array($isGroupingNeeded, [true, false], true)) {
            throw new \InvalidArgumentException(\sprintf('Only TRUE or FALSE can be used as a parameter for %s::useGrouping($isGroupingNeeded) method', \get_class($this)));
        }

        $this->isGroupingNeeded = $isGroupingNeeded;

        return $this;
    }
}
