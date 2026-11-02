<?php

namespace App\Entity;

use App\Repository\BalancePartnersRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * BalancePartners
 */
#[ORM\Entity(repositoryClass: BalancePartnersRepository::class)]
#[ORM\Table(name: "balance_partners", schema: "reconciliation")]
class BalancePartners
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "partner_id", type: "bigint")]
    private ?int $partnerId = null;

    #[ORM\Column(name: "name", type: "string", length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: "counter_in", type: "bigint")]
    private ?string $counterIn = null;

    #[ORM\Column(name: "counter_out_old", type: "bigint")]
    private ?string $counterOutOld = null;

    #[ORM\Column(name: "counter_out_calculated", type: "bigint")]
    private ?string $counterOutCalculated = null;

    #[ORM\Column(type: "bigint")]
    private ?string $credit = null;

    #[ORM\Column(type: "bigint")]
    private ?string $balance = null;

    #[ORM\Column(name: "created_at", type: "datetime")]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartnerId(): ?int
    {
        return $this->partnerId;
    }

    public function setPartnerId(int $partnerId): self
    {
        $this->partnerId = $partnerId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCounterIn(): ?string
    {
        return $this->counterIn;
    }

    public function setCounterIn(string $counterIn): self
    {
        $this->counterIn = $counterIn;

        return $this;
    }

    public function getCounterOutOld(): ?string
    {
        return $this->counterOutOld;
    }

    public function setCounterOutOld(string $counterOutOld): self
    {
        $this->counterOutOld = $counterOutOld;

        return $this;
    }

    public function getCounterOutCalculated(): ?string
    {
        return $this->counterOutCalculated;
    }

    public function setCounterOutCalculated(string $counterOutCalculated): self
    {
        $this->counterOutCalculated = $counterOutCalculated;

        return $this;
    }

    public function getCredit(): ?string
    {
        return $this->credit;
    }

    public function setCredit(string $credit): self
    {
        $this->credit = $credit;

        return $this;
    }

    public function getBalance(): ?string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): self
    {
        $this->balance = $balance;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
