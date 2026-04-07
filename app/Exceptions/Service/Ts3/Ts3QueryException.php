<?php

namespace Pterodactyl\Exceptions\Service\Ts3;

use Illuminate\Http\Response;
use Pterodactyl\Exceptions\DisplayException;

class Ts3QueryException extends DisplayException
{
    private int $statusCode;

    public function __construct(
        string $message,
        int $statusCode = Response::HTTP_BAD_GATEWAY,
        ?\Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;

        parent::__construct($message, $previous, self::LEVEL_WARNING);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
