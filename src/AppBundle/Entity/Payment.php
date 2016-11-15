<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="payment")
 */
class Payment
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @ORM\Column(type="string", length=28, unique=true)
     */
    public $paypalId;

    /**
     * @ORM\Column(type="string", length=50)
     */
    public $state;

    /**
     * @ORM\Column(type="datetime")
     */
    public $timeCreated;

    /**
     * @ORM\Column(type="datetime")
     */
    public $timeUpdated;

    /**
     * @ORM\Column(type="string", length=86)
     */
    public $redirectUrl;
}
