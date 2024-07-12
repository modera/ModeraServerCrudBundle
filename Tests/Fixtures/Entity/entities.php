<?php

namespace Modera\ServerCrudBundle\Tests\Functional;

use Doctrine\ORM\Mapping as Orm;
use Doctrine\Common\Collections\ArrayCollection;
use Modera\ServerCrudBundle\DataMapping\PreferencesAwareUserInterface;
use Modera\ServerCrudBundle\QueryBuilder\ResolvingAssociatedModelSortingField\QueryOrder;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @Orm\Entity
 */
class DummyUser implements UserInterface, PreferencesAwareUserInterface
{
    /**
     * @Orm\Column(type="integer")
     * @Orm\Id
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column(type="string")
     */
    public $firstname;

    /**
     * @Orm\Column(type="string")
     */
    public $lastname;

    /**
     * @Orm\Column(type="string", nullable=true)
     */
    public $email = null;

    /**
     * @Orm\Column(type="boolean")
     */
    public $isActive = true;

    /**
     * @Orm\Column(type="integer")
     */
    public $accessLevel = 0;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    public ?\DateTimeInterface $updatedAt = null;

    /**
     * @var DummyAddress
     * @Orm\OneToOne(targetEntity="DummyAddress", cascade={"PERSIST"})
     */
    public $address;

    /**
     * @var ArrayCollection
     * @Orm\OneToMany(targetEntity="DummyNote", mappedBy="user")
     */
    public $notes;

    /**
     * @Orm\ManyToOne(targetEntity="DummyCreditCard")
     */
    public $creditCard = null;

    /**
     * @Orm\ManyToMany(targetEntity="DummyGroup", inversedBy="users")
     */
    public $groups;

    /**
     * @Orm\Column(type="integer", nullable=true)
     */
    public $price = 0;

    /**
     * @Orm\Column(type="json", nullable=false)
     */
    public $meta = [];

    public function __construct()
    {
        $this->notes = new ArrayCollection();
        $this->groups = new ArrayCollection();
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;
    }

    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
    }

    public function addNote(DummyNote $note)
    {
        if (!$this->notes->contains($note)) {
            $note->setUser($this);
            $this->notes[] = $note;
        }
    }

    public function setActive($isActive)
    {
        $this->isActive = $isActive;
    }

    public function setAccessLevel($accessLevel)
    {
        $this->accessLevel = $accessLevel;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function setMeta($meta)
    {
        $this->meta = $meta;
    }

    public function getPreferences(): array
    {
        return [
            PreferencesAwareUserInterface::SETTINGS_DATE_FORMAT => 'd.m.y',
            PreferencesAwareUserInterface::SETTINGS_DATETIME_FORMAT => 'd.m.y H:i',
        ];
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return 'password';
    }

    public function getSalt(): string
    {
        return 'salt';
    }

    public function eraseCredentials(): void
    {
    }

    public function getUsername(): string
    {
        return \implode('-', [
            $this->id,
            $this->firstname,
            $this->lastname,
        ]);
    }

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }

    public function __toString(): string
    {
        return $this->getUsername();
    }
}

/**
 * @Orm\Entity
 */
class DummyGroup
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column(type="string")
     */
    public $name;

    /**
     * @Orm\ManyToMany(targetEntity="DummyUser", mappedBy="groups")
     */
    public $users;

    public function addUser(DummyUser $user)
    {
        $user->groups->add($this);
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }
    }

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }
}

/**
 * @Orm\Entity
 */
class DummyCreditCard
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column(type="integer")
     */
    public $number;
}

/**
 * @Orm\Entity
 *
 * @QueryOrder("zip")
 */
class DummyAddress
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column
     */
    public $zip;

    /**
     * @Orm\Column
     */
    public $street;

    /**
     * @var DummyCountry
     * @Orm\ManyToOne(targetEntity="DummyCountry", cascade={"PERSIST"})
     */
    public $country;

    /**
     * @var DummyCity
     *
     * @Orm\ManyToOne(targetEntity="DummyCity", cascade={"PERSIST"})
     */
    public $city;
}

/**
 * @Orm\Entity
 */
class DummyCountry
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column
     */
    public $name;

    /**
     * @Orm\OneToOne(targetEntity="DummyCity")
     */
    public $capital = null;
}

/**
 * @Orm\Entity
 */
class DummyCity
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column
     */
    public $name;
}

/**
 * @Orm\Entity
 */
class DummyNote
{
    /**
     * @Orm\Column(type="integer")
     * @Orm\Id
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @Orm\Column(type="string")
     */
    public $text;

    /**
     * @var DummyUser
     * @Orm\ManyToOne(targetEntity="DummyUser", inversedBy="notes")
     * @Orm\JoinColumn(name="user_id", referencedColumnName="id")
     */
    private $user;

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setText($text)
    {
        $this->text = $text;
    }

    public function setUser(DummyUser $user)
    {
        $this->user = $user;
    }
}

/**
 * @Orm\Entity
 */
class DummyOrder
{
    /**
     * @Orm\Id
     * @Orm\Column(type="integer")
     * @Orm\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @var DummyUser
     *
     * @Orm\ManyToOne(targetEntity="DummyUser")
     */
    public $user;

    /**
     * @Orm\Column(type="string")
     */
    public $number;
}
