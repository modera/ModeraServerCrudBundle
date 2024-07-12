<?php

namespace Modera\ServerCrudBundle\Hydration;

/**
 * @author    Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class UnknownHydrationProfileException extends \RuntimeException
{
    private ?string $profileName = null;

    public function setProfileName(string $profileName): void
    {
        $this->profileName = $profileName;
    }

    public function getProfileName(): ?string
    {
        return $this->profileName;
    }
}
