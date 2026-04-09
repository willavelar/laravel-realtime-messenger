<?php

namespace App\Modules\Notifications\gRPC\Generated;

class PushRequest
{
    private array $device_tokens = [];
    private string $title = '';
    private string $body = '';
    /** @var array<string, string> */ private array $data = [];

    public function setDeviceTokens(array $var): static { $this->device_tokens = $var; return $this; }
    public function getDeviceTokens(): array { return $this->device_tokens; }
    public function setTitle(string $var): static { $this->title = $var; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setBody(string $var): static { $this->body = $var; return $this; }
    public function getBody(): string { return $this->body; }
    public function setData(array $var): static { $this->data = $var; return $this; }
    public function getData(): array { return $this->data; }
}
