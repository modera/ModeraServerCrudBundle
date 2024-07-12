<?php

namespace Modera\ServerCrudBundle\Hydration;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class BadHydrationResultException extends \RuntimeException
{
    /**
     * @var mixed Mixed value
     */
    private $result;

    private ?HydrationProfile $profile = null;

    private ?string $groupName = null;

    /**
     * @param mixed $result Mixed value
     */
    public static function create(string $message, $result = null, ?HydrationProfile $profile = null, ?string $groupName = null): self
    {
        $me = new self($message);
        $me->result = $result;
        $me->profile = $profile;
        $me->groupName = $groupName;

        return $me;
    }

    /**
     * @return mixed Mixed value
     */
    public function getResult()
    {
        return $this->result;
    }

    public function getProfile(): ?HydrationProfile
    {
        return $this->profile;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }
}
