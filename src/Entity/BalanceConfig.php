<?php

namespace App\Entity;

use AllowDynamicProperties;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\BalanceConfigRepository;

#[AllowDynamicProperties]
#[ORM\Entity(repositoryClass: BalanceConfigRepository::class)]
#[ORM\Table(name: "balance_config", schema: "reconciliation")]
class BalanceConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "partner_id", type: "integer")]
    private int $partnerId;

    #[ORM\Column(name: "partner_name", type: "string", length: 255)]
    private string $partnerName;

    #[ORM\Column(name: "wallet_id", type: "integer")]
    private int $walletId;

    #[ORM\Column(name: "is_active", type: "boolean", nullable: true)]
    private bool $isActive;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $type = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $provider = null;

    #[ORM\Column(type: "string", length: 255)]
    private string $description;

    #[ORM\OneToMany(mappedBy: "balanceConfigId", targetEntity: "App\Entity\BalanceProcess")]
    private Collection $collect;

    public function __construct() {
        $this->collect = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCollect(): Collection
    {
        return $this->collect;
    }

    public function getPartnerId(): int
    {
        return $this->partnerId;
    }

    public function setPartnerId(int $partnerId): self
    {
        $this->partnerId = $partnerId;

        return $this;
    }

    public function getPartnerName(): string
    {
        return $this->partnerName;
    }

    public function setPartnerName(string $partnerName): self
    {
        $this->partnerName = $partnerName;

        return $this;
    }

    public function getWalletId(): int
    {
        return $this->walletId;
    }

    public function setWalletId(int $walletId): self
    {
        $this->walletId = $walletId;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription($description): self
    {
        $this->description = $description;

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

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }
}
