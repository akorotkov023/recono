<?php

namespace App\Entity;

use App\Repository\EmailProcessRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * EmailProcess
 *
 * @ORM\Table(schema="reconciliation", name="email_process")
 * @ORM\Entity(repositoryClass=EmailProcessRepository::class)
 */
#[ORM\Entity(repositoryClass: EmailProcessRepository::class)]
#[ORM\Table(name: "email_process", schema: "reconciliation")]
class EmailProcess
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
     * @var EmailConfig
     * @ORM\ManyToOne(targetEntity="App\Entity\EmailConfig", inversedBy="collect", fetch="EAGER")
     * @ORM\JoinColumns({
     *     @ORM\JoinColumn(name="email_config_id", referencedColumnName="id")
     * })
     */
    #[ORM\ManyToOne(targetEntity: "App\Entity\EmailConfig", fetch: "EAGER", inversedBy: "collect")]
    #[ORM\JoinColumn(name: "email_config_id", referencedColumnName: "id")]
    private EmailConfig $emailConfigId;

    /**
     * @ORM\Column(name="total_amount", type="json")
     */
    #[ORM\Column(name: "total_amount", type: "json")]
    private $totalAmount = [];

    /**
     * @ORM\Column(name="balance", type="json")
     */
    #[ORM\Column(name: "balance", type: "json")]
    private $balance = [];

    /**
     * @ORM\Column(name="regs", type="json")
     */
    #[ORM\Column(name: "regs", type: "json")]
    private $regs = [];

    /**
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(type: "string", length: 255)]
    private ?string $status;

    /**
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(name: "created_at",type: "datetime")]
    private ?\DateTime $createdAt;

    /**
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(type: "datetime")]
    private ?\DateTime $onDate;

    /**
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(type: "string", length: 255)]
    private ?string $description;

    #[ORM\Column(name: "count", type: "json")]
    private $count = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmailConfigId(): EmailConfig
    {
        return $this->emailConfigId;
    }

    public function setEmailConfigId(EmailConfig $emailConfigId): self
    {
        $this->emailConfigId = $emailConfigId;

        return $this;
    }

    public function getTotalAmount(): array
    {
        $totalAmount = is_array($this->totalAmount) ? $this->totalAmount : [];
        $totalAmount[] = 0;

        return array_unique($totalAmount);
    }

    public function setTotalAmount(array $totalAmount): self
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getBalance(): array
    {
        $balance = is_array($this->balance) ? $this->balance : [];

        return array_unique($balance);
    }

    public function setBalance(array $balance): self
    {
        $this->balance = $balance;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

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

    public function getOnDate(): ?\DateTime
    {
        return $this->onDate;
    }

    public function setOnDate(\DateTime $onDate): self
    {
        $this->onDate = $onDate;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getRegs(): array
    {
        $regs = is_array($this->regs) ? $this->regs : [];
        $regs[] = '';

        return array_unique($regs);
    }

    public function setRegs(array $regs): self
    {
        $this->regs = $regs;

        return $this;
    }

    public function getCount(): array
    {
        $count = is_array($this->count) ? $this->count : [];
        $count[] = 0;

        return array_unique($count);
    }

    public function setCount(array $count): self
    {
        $this->count = $count;

        return $this;
    }
}
