<?php

namespace Modera\ServerCrudBundle\Tests\Functional;

use Doctrine\ORM\Mapping as Orm;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Orm\Entity
 */
class DummyUser
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
     * @var ArrayCollection
     * @Orm\OneToMany(targetEntity="DummyNote", mappedBy="user")
     */
    public $notes;

    public function __construct()
    {
        $this->notes = new ArrayCollection();
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
