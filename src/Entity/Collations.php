<?php

namespace App\Entity;

use App\Repository\CollationsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=CollationsRepository::class)
 * @ORM\Table(schema="reconciliation")
 */
#[ORM\Entity(repositoryClass: CollationsRepository::class)]
#[ORM\Table(schema: "reconciliation")]
class Collations
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
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(type: "string", length: 255)]
    private ?string $processingId;

    /**
     * @ORM\Column(type="integer")
     */
    #[ORM\Column(type: "integer")]
    private ?int $commandId;

    /**
     * @ORM\Column(type="datetime_immutable")
     */
    #[ORM\Column(type: "datetime_immutable")]
    private ?\DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProcessingId(): ?string
    {
        return $this->processingId;
    }

    public function setProcessingId(string $processingId): self
    {
        $this->processingId = $processingId;

        return $this;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
