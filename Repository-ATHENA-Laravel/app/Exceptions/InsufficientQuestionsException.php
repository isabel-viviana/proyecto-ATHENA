<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientQuestionsException extends RuntimeException
{
    protected int $available;
    protected int $required;

    public function __construct(string $message, int $available = 0, int $required = 25)
    {
        parent::__construct($message, 422);
        $this->available = $available;
        $this->required = $required;
    }

    public function getAvailable(): int
    {
        return $this->available;
    }

    public function getRequired(): int
    {
        return $this->required;
    }
}
