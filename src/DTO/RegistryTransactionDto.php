<?php

namespace App\DTO;

class RegistryTransactionDto
{
    public string $id;
    public int $command;

    public function __construct(string $id, int $command) {
        $this->id = $id;
        $this->command = $command;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getCommand(): int
    {
        return $this->command;
    }
}
