<?php

namespace App\Entity;

use App\Repository\EmailConfigRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailConfigRepository::class)]
#[ORM\Table(name: "email_config", schema: "reconciliation")]
class EmailConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(name: "partner_id", type: "json")]
    private $partnerId = [];

    #[ORM\Column(name: "email_to", type: "json")]
    private $emailTo = [];

    #[ORM\Column(name: "email_cc", type: "json")]
    private $emailCc = [];

    #[ORM\Column(name: "type", type: "json")]
    private $type = [];

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private string $slug;

    #[ORM\Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive = null;

    #[ORM\Column(name: "created_at", type: "datetime")]
    private \DateTime $createdAt;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $format = null;

    #[ORM\Column(type: 'string', length: 255, options: ['default' => 'UTC-3'])]
    private ?string $utc = 'UTC-3';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $provider = [];

    #[ORM\Column(type: 'json')]
    private $terminal = [];

    public array $balance;

    #[ORM\OneToMany(mappedBy: "emailConfigId", targetEntity: "App\Entity\EmailProcess")]
    private Collection $collect;

    #[ORM\Column(name: "is_total_amount_checked", type: "boolean", options: ["default" => false])]
    private bool $isTotalAmountChecked = false;

    #[ORM\Column(name: "is_refund_checked", type: "boolean", options: ["default" => false])]
    private bool $isRefundChecked = false;

    #[ORM\Column(name: "registry_type", type: "string", length: 50, nullable: true)]
    private ?string $registryType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartnerId(): array
    {
        $partnerId = is_array($this->partnerId) ? $this->partnerId : [];
        $partnerId[] = 0;

        return array_unique($partnerId);
    }

    public function setPartnerId(array $partnerId): self
    {
        $this->partnerId = $partnerId;

        return $this;
    }

    public function getEmailTo(): array
    {
        $email = is_array($this->emailTo) ? $this->emailTo : [];
        $email[] = '';

        return array_unique($email);
    }

    public function setEmailTo(array $emailTo): self
    {
        $this->emailTo = $emailTo;

        return $this;
    }

    public function getEmailCc(): array
    {
        $email = is_array($this->emailCc) ? $this->emailCc : [];
        $email[] = '';

        return array_unique($email);
    }

    public function setEmailCc(array $emailCc): self
    {
        $this->emailCc = $emailCc;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getIsTotalAmountChecked(): ?bool
    {
        return $this->isTotalAmountChecked;
    }

    public function getIsRefundChecked(): ?bool
    {
        return $this->isRefundChecked;
    }

    public function getRegistryType(): ?string
    {
        return $this->registryType;
    }

    public function setRegistryType(?string $registryType): self
    {
        $this->registryType = $registryType;

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

    public function getType(): array
    {
        $type = is_array($this->type) ? $this->type : [];
        $type[] = '';

        return array_unique($type);
    }

    public function setType(array $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): self
    {
        $this->format = $format;

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

    public function getTerminal(): array
    {
        $terminal = is_array($this->terminal) ? $this->terminal : [];
        $terminal[] = 0;

        return array_unique($terminal);
    }

    public function setTerminal(array $terminal): self
    {
        $this->terminal = $terminal;

        return $this;
    }

    public function getProvider(): array
    {
        $provider = is_array($this->provider) ? $this->provider : [];

        return array_unique($provider);
    }

    public function setProvider(array $provider): self
    {
        $this->provider = $provider;

        return $this;
    }
}
