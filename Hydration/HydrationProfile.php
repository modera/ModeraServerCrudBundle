<?php

namespace Modera\ServerCrudBundle\Hydration;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class HydrationProfile implements HydrationProfileInterface
{
    /**
     * @var bool
     */
    private $isGroupingNeeded;

    /**
     * @var array
     */
    private $groups = array();

    /**
     * @var mixed[]
     */
    private $extensionPoint;

    /**
     * @return string[]
     */
    public function getGroups()
    {
        return $this->groups;
    }

    // fluent interface:

    /**
     * @param bool $isGroupingNeeded
     *
     * @return HydrationProfile
     */
    public static function create($isGroupingNeeded = true)
    {
        $me = new self();
        $me->useGrouping($isGroupingNeeded);

        return $me;
    }

    /**
     * @return bool
     */
    public function isGroupingNeeded()
    {
        return $this->isGroupingNeeded;
    }

    /**
     * @param mixed[] $groups
     *
     * @return HydrationProfile
     */
    public function useGroups(array $groups)
    {
        $this->groups = $groups;

        return $this;
    }

    /**
     * @param string $extensionPoint
     *
     * @return HydrationProfile
     */
    public function useExtensionPoint($extensionPoint)
    {
        $this->extensionPoint = $extensionPoint;

        return $this;
    }

    /**
     * If TRUe then serialized data will be grouped under "profile-names". See Resources/doc/index.md for more
     * details.
     *
     * @param bool $isGroupingNeeded
     *
     * @return HydrationProfile
     */
    public function useGrouping($isGroupingNeeded)
    {
        if (!in_array($isGroupingNeeded, array(true, false), true)) {
            throw new \InvalidArgumentException(
                'Only TRUE or FALSE can be used as a parameter for %s::useGrouping($isGroupingNeeded) method',
                get_class($this)
            );
        }

        $this->isGroupingNeeded = $isGroupingNeeded;

        return $this;
    }
}
