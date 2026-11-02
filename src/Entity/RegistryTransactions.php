<?php

namespace App\Entity;

use App\Repository\RegistryTransactionsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RegistryTransactionsRepository::class)
 * @ORM\Table(schema="reconciliation")
 */
#[ORM\Entity(repositoryClass: RegistryTransactionsRepository::class)]
#[ORM\Table(schema: "reconciliation")]
class RegistryTransactions
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
     * @var RegistryCommand
     * @ORM\ManyToOne(targetEntity="App\Entity\RegistryCommand", inversedBy="collect", fetch="EAGER")
     * @ORM\JoinColumns({
     *     @ORM\JoinColumn(name="command_id", referencedColumnName="id")
     * })
     */
    #[ORM\ManyToOne(targetEntity: "App\Entity\RegistryCommand", fetch: "EAGER", inversedBy: "collect")]
    #[ORM\JoinColumn(name: "command_id", referencedColumnName: "id")]
    private RegistryCommand $commandId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $type;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $processingId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $providerId;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $amount;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $fee;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $cardnumber;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $approveCode;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTime $transactionDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommandId(): RegistryCommand
    {
        return $this->commandId;
    }

    public function setCommandId(RegistryCommand $commandId): self
    {
        $this->commandId = $commandId;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

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

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getFee(): ?int
    {
        return $this->fee;
    }

    public function setFee(?int $fee): self
    {
        $this->fee = $fee;

        return $this;
    }

    public function getCardnumber(): ?string
    {
        return $this->cardnumber;
    }

    public function setCardnumber(?string $cardnumber): self
    {
        $this->cardnumber = $cardnumber;

        return $this;
    }

    public function getApproveCode(): ?string
    {
        return $this->approveCode;
    }

    public function setApproveCode(?string $approveCode): self
    {
        $this->approveCode = $approveCode;

        return $this;
    }

    public function getTransactionDate(): ?\DateTime
    {
        return $this->transactionDate;
    }

    public function setTransactionDate(?\DateTime $transactionDate): self
    {
        $this->transactionDate = $transactionDate;

        return $this;
    }
}
