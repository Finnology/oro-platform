<?php

namespace Oro\Bundle\QueryDesignerBundle\Tests\Unit\Fixtures\Models\CMS;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cms_users')]
class CmsUser
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    public $id;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public $status;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    public $username;

    #[ORM\Column(type: 'string', length: 255)]
    public $name;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: CmsAddress::class, cascade: ['persist'], orphanRemoval: true)]
    public $address;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CmsAddress::class, cascade: ['all'], orphanRemoval: true)]
    public $shippingAddresses;

    public function __construct()
    {
        $this->shippingAddresses = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress(CmsAddress $address)
    {
        if ($this->address !== $address) {
            $this->address = $address;
            $address->setUser($this);
        }
    }
}
