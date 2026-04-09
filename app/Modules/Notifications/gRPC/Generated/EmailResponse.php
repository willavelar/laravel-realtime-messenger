<?php

namespace App\Modules\Notifications\gRPC\Generated;

class EmailResponse
{
    private bool $success = false;
    private string $message = '';

    public function setSuccess(bool $var): static { $this->success = $var; return $this; }
    public function getSuccess(): bool { return $this->success; }
    public function setMessage(string $var): static { $this->message = $var; return $this; }
    public function getMessage(): string { return $this->message; }
}
