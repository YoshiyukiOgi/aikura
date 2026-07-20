<?php

namespace App\Exceptions\StateMachine;

use DomainException;

class InvalidStatusTransitionException extends DomainException
{
    public static function forTransition(string $machine, string $from, string $to): self
    {
        return new self("Status transition [{$machine}: {$from} -> {$to}] is not allowed.");
    }

    public static function forUnknownMachine(string $machine): self
    {
        return new self("State machine [{$machine}] is not defined.");
    }

    public static function forMissingStatus(string $modelClass): self
    {
        return new self("Model [{$modelClass}] does not have a status value.");
    }
}

