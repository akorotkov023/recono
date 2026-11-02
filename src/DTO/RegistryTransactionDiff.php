<?php

namespace App\DTO;

class RegistryTransactionDiff
{
    /** @var RegistryTransactionDto[] */
    public array $regIds = [];

    /** @var string[] */
    public array $baseIds = [];

    public function addRegId(string $id, int $command): void {
        $this->regIds[] = new RegistryTransactionDto($id, $command);
    }

    public function addBaseId(string $id): void
    {
        $this->baseIds[] = $id;
    }

    public function getRegIds(): array {
        return array_map(fn(RegistryTransactionDto $item) => $item->id, $this->regIds);
    }

    public function getBaseIds(): array
    {
        return $this->baseIds;
    }

}
