<?php

namespace App\Entity;

use App\Repository\RegistryCommandRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=RegistryCommandRepository::class)
 * @ORM\Table(schema="reconciliation")
 */
#[ORM\Entity(repositoryClass: RegistryCommandRepository::class)]
#[ORM\Table(schema: "reconciliation")]
class RegistryCommand
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
     * @var RegistryConfig
     * @ORM\ManyToOne(targetEntity="App\Entity\RegistryConfig", inversedBy="collect", fetch="EAGER")
     * @ORM\JoinColumns({
     *     @ORM\JoinColumn(name="registry_config_id", referencedColumnName="id")
     * })
     */
    #[ORM\ManyToOne(targetEntity: "App\Entity\RegistryConfig", fetch: "EAGER", inversedBy: "collect")]
    #[ORM\JoinColumn(name: "registry_config_id", referencedColumnName: "id")]
    private RegistryConfig $registryConfigId;


    /**
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(type: "string", length: 255)]
    private ?string $status;

    /**
     * @ORM\Column(type="bigint")
     */
    #[ORM\Column(name: "total_amount", type: "bigint")]
    private ?int $totalAmount;

    /**
     * @ORM\Column(type="integer")
     */
    #[ORM\Column(name: "total_count", type: "integer")]
    private ?int $totalCount;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $description;

    /**
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(name: "created_at" ,type: "datetime")]
    private ?\DateTime $createdAt;

    /**
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(name: "on_date", type: "datetime")]
    private ?\DateTime $onDate;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\RegistryTransactions", mappedBy="commandId")
     */
    #[ORM\OneToMany(mappedBy: "registryConfigId", targetEntity: "App\Entity\RegistryTransactions")]
    private Collection $collect;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRegistryConfigId(): RegistryConfig
    {
        return $this->registryConfigId;
    }

    public function setRegistryConfigId(RegistryConfig $registryConfigId): self
    {
        $this->registryConfigId = $registryConfigId;

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

    public function getTotalAmount(): ?int
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(int $totalAmount): self
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getTotalCount(): ?int
    {
        return $this->totalCount;
    }

    public function setTotalCount(int $totalCount): self
    {
        $this->totalCount = $totalCount;

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
