<?php

namespace App\Entity;

use App\Repository\RegistryConfigRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RegistryConfigRepository::class)]
#[ORM\Table(name: "registry_config", schema: "reconciliation")]
class RegistryConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive = true;

    #[ORM\Column(type: "datetime")]
    private DateTime $createdAt;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private string $provider;

    #[ORM\Column(name: "provider_wallet_id", type: "integer")]
    private int $providerWalletId;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: "string", length: 255, unique: true)]
    private ?string $slug;

    #[ORM\Column(type: 'string', length: 255, options: ['default' => 'UTC-3'])]
    private ?string $utc = 'UTC-3';

    #[ORM\OneToMany(mappedBy: "registryConfigId", targetEntity: "App\Entity\RegistryCommand")]
    private Collection $collect;

    public function __construct() {
        $this->collect = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderWalletId(): int
    {
        return $this->providerWalletId;
    }

    public function setProviderWalletId(int $providerWalletId): self
    {
        $this->providerWalletId = $providerWalletId;

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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getUtc(): ?string
    {
        return $this->utc;
    }

    public function setUtc(string $utc): self
    {
        $this->utc = $utc;

        return $this;
    }
}
