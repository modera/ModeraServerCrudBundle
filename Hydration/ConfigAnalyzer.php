<?php

namespace Modera\ServerCrudBundle\Hydration;

/**
 * @internal
 *
 * @author Sergei Lissovski <sergei.lissovski@modera.org>
 * @copyright 2013 Modera Foundation
 */
class ConfigAnalyzer
{
    /**
     * @var array<string, mixed>
     */
    private array $rawConfig;

    /**
     * @param array<string, mixed> $rawConfig
     */
    public function __construct(array $rawConfig)
    {
        $this->rawConfig = $rawConfig;
    }

    /**
     * @throws UnknownHydrationProfileException
     */
    public function getProfileDefinition(string $profileName): HydrationProfile
    {
        $profiles = \is_array($this->rawConfig['profiles'] ?? null) ? $this->rawConfig['profiles'] : [];

        $isFound = isset($profiles[$profileName]);
        if (!$isFound) {
            if (\in_array($profileName, $profiles)) {
                /*
                 * When hydration config looks like this:
                 *
                 * array(
                 *     'groups' => array(
                 *         'list' => ['id', 'username']
                 *     ),
                 *     'profiles' => array(
                 *         'list'
                 *     )
                 * );
                 */
                return HydrationProfile::create(false)->useGroups([$profileName]);
            }
        }

        if (!$isFound) {
            $e = new UnknownHydrationProfileException(
                "Hydration profile '$profileName' is not found."
            );
            $e->setProfileName($profileName);

            throw $e;
        }

        /** @var string[]|HydrationProfile $profile */
        $profile = $profiles[$profileName];
        if (\is_array($profile)) {
            /*
             * Will be used when hydration config looks akin to the following:
             *
             *  array(
             *     'groups' => array(
             *         'author' => ['author.id', 'author.firstname', 'author.lastname'],
             *         'tags' => function() { ... }
             *     ),
             *     'profiles' => array(
             *         'mixed' => ['author', 'tags']
             *     )
             * );
             */
            return HydrationProfile::create()->useGroups($profile);
        }

        return $profile;
    }

    /**
     * @return callable|string[]|array<string, string>
     */
    public function getGroupDefinition(string $groupName)
    {
        $groups = \is_array($this->rawConfig['groups']) ? $this->rawConfig['groups'] : [];

        if (!isset($groups[$groupName])) {
            $e = new UnknownHydrationGroupException(
                "Hydration group '$groupName' is not found."
            );
            $e->setGroupName($groupName);

            throw $e;
        }

        return $groups[$groupName];
    }
}
