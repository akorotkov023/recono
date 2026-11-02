<?php

namespace App\Entity;

use App\Repository\CollationIssuesLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CollationIssuesLogRepository::class)
 * @ORM\Table(schema="reconciliation")
 */
#[ORM\Entity(repositoryClass: CollationIssuesLogRepository::class)]
#[ORM\Table(schema: "reconciliation")]
class CollationIssuesLog
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    #[ORM\Column(type: "integer")]
    private ?int $commandId;

    /**
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(type: "datetime")]
    private ?\DateTime $createdAt;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $processingId;

    /**
     * @ORM\Column(name="provider_id", type="string", length=255, nullable=true)
     */
    #[ORM\Column(name: "provider_id", type: "string", length: 255, nullable: true)]
    private ?string $providerId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $description;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommandId(): ?int
    {
        return $this->commandId;
    }

    public function setCommandId(int $commandId): self
    {
        $this->commandId = $commandId;

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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getProcessingId(): ?string
    {
        return $this->processingId;
    }

    public function setProcessingId(?string $processingId): self
    {
        $this->processingId = $processingId;

        return $this;
    }

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(?string $providerId): self
    {
        $this->providerId = $providerId;

        return $this;
    }

}

