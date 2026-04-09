<?php

namespace App\Modules\Notifications\gRPC\Generated;

class EmailRequest
{
    private string $to = '';
    private string $subject = '';
    private string $template = '';
    /** @var array<string, string> */ private array $variables = [];

    public function setTo(string $var): static { $this->to = $var; return $this; }
    public function getTo(): string { return $this->to; }
    public function setSubject(string $var): static { $this->subject = $var; return $this; }
    public function getSubject(): string { return $this->subject; }
    public function setTemplate(string $var): static { $this->template = $var; return $this; }
    public function getTemplate(): string { return $this->template; }
    public function setVariables(array $var): static { $this->variables = $var; return $this; }
    public function getVariables(): array { return $this->variables; }
}
