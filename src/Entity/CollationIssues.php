<?php

namespace App\Entity;

use App\Repository\CollationIssuesLogRepository;
use App\Repository\CollationIssuesRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CollationIssuesRepository::class)
 * @ORM\Table(schema="reconciliation")
 */
#[ORM\Entity(repositoryClass: CollationIssuesRepository::class)]
#[ORM\Table(schema: "reconciliation")]
class CollationIssues
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "integer")]
    private ?int $commandId;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $type;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $description;

    #[ORM\Column(type: "datetime")]
    private ?\DateTime $createdAt;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $processingId;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $providerId;

    #[ORM\Column(type: "datetime", precision: 6)]
    private ?\DateTime $started;

    #[ORM\Column(type: "string", length: 50)]
    private ?string $status;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

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

    public function getStarted(): ?\DateTime
    {
        return $this->started;
    }

    public function setStarted(\DateTime $started): self
    {
        $this->started = $started;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }
}
