<?php

namespace Pterodactyl\Exceptions\Service\GameQuery;

use Illuminate\Http\Response;
use Pterodactyl\Exceptions\DisplayException;

class GameQueryException extends DisplayException
{
    public function __construct(
        string $message,
        private int $statusCode = Response::HTTP_BAD_GATEWAY,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $previous, self::LEVEL_WARNING);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
