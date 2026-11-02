<?php


namespace App\Message;

class ExtractedRegistryMessage
{
    private string $provider;

    public function __construct(string $provider)
    {
        $this->provider = $provider;
    }

    /**
     * @return string
     */
    public function getProvider(): string
    {
        return $this->provider;
    }
}
