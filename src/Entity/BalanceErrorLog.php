<?php

namespace App\Entity;

use App\Repository\BalanceErrorLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=BalanceErrorLogRepository::class)
 * @ORM\Table(schema="reconciliation")
 */
#[ORM\Entity(repositoryClass: BalanceErrorLogRepository::class)]
#[ORM\Table(schema: "reconciliation")]
class BalanceErrorLog
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    /**
     * @ORM\Column(name="process_id", type="integer")
     */
    #[ORM\Column(name: "process_id", type: "integer")]
    private int $processId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $description;

    /**
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(name: "created_at", type: "datetime")]
    private ?\DateTime $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProcessId(): ?int
    {
        return $this->processId;
    }

    public function setProcessId(int $processId): self
    {
        $this->processId = $processId;

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

}
