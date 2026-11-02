<?php


namespace App\Message;

class ChangeStatusMessage
{
    private string $token;
    private string $id;
    private string $status;
    private string $description;


    public function __construct(string $token, string $id, string $status, string $description = null)
    {
        $this->token = $token;
        $this->id = $id;
        $this->status = $status;
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function gettoken(): string
    {
        return $this->token;
    }

    /**
     * @return string
     */
    public function getid(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

}
