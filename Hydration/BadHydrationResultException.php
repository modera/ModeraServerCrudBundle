<?php

namespace Modera\ServerCrudBundle\Hydration;

/**
 * @since 2.53.0
 *
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2016 Modera Foundation
 */
class BadHydrationResultException extends \RuntimeException
{
    /**
     * @var mixed
     */
    private $result;

    /**
     * @var mixed
     */
    private $profile;

    /**
     * @var string
     */
    private $groupName;

    /**
     * @param string $message
     * @param mixed  $result
     * @param mixed  $profile
     * @param string $groupName
     *
     * @return BadHydrationResultException
     */
    public static function create($message, $result = null, $profile = null, $groupName = null)
    {
        $me = new static($message);
        $me->result = $result;
        $me->profile = $profile;
        $me->groupName = $groupName;

        return $me;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @return string
     */
    public function getGroupName()
    {
        return $this->groupName;
    }
}
