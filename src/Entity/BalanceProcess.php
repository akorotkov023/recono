<?php

namespace App\Entity;

use App\Repository\BalanceProcessRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * BalanceProcess
 *
 * @ORM\Table(schema="reconciliation", name="balance_process")
 * @ORM\Entity(repositoryClass=BalanceProcessRepository::class)
 */
#[ORM\Entity(repositoryClass: BalanceProcessRepository::class)]
#[ORM\Table(name: "balance_process", schema: "reconciliation")]
class BalanceProcess
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
     * @var BalanceConfig
     * @ORM\ManyToOne(targetEntity="App\Entity\BalanceConfig", inversedBy="collect", fetch="EAGER")
     * @ORM\JoinColumns({
     *     @ORM\JoinColumn(name="balance_config_id", referencedColumnName="id")
     * })
     */
    #[ORM\ManyToOne(targetEntity: "App\Entity\BalanceConfig", fetch: "EAGER", inversedBy: "collect")]
    #[ORM\JoinColumn(name: "balance_config_id", referencedColumnName: "id")]
    private BalanceConfig $balanceConfigId;

    /**
     * @ORM\Column(name="total_amount", type="bigint")
     */
    #[ORM\Column(name: "total_amount", type: "bigint")]
    private ?int $totalAmount;

    /**
     * @ORM\Column(name="counter_in_was", type="bigint")
     */
    #[ORM\Column(name: "counter_in_was", type: "bigint")]
    private ?int $counterInWas;

    /**
     * @ORM\Column(name="counter_in_be", type="bigint")
     */
    #[ORM\Column(name: "counter_in_be", type: "bigint")]
    private ?int $counterInBe;

    /**
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(type: "string", length: 255)]
    private ?string $status;

    /**
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(type: "datetime")]
    private ?\DateTime $createdAt;

    /**
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(type: "datetime")]
    private ?\DateTime $onDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBalanceConfigId(): BalanceConfig
    {
        return $this->balanceConfigId;
    }

    public function setBalanceConfigId(BalanceConfig $balanceConfigId): self
    {
        $this->balanceConfigId = $balanceConfigId;

        return $this;
    }

    public function getTotalAmount(): ?int
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(int $totalAmount): self
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getCounterInWas(): ?int
    {
        return $this->counterInWas;
    }

    public function setCounterInWas(int $counterInWas): self
    {
        $this->counterInWas = $counterInWas;

        return $this;
    }

    public function getCounterInBe(): ?int
    {
        return $this->counterInBe;
    }

    public function setCounterInBe(int $counterInBe): self
    {
        $this->counterInBe = $counterInBe;

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

}
