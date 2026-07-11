<?php

declare(strict_types=1);

namespace Mirel\TowerSdk\Exception;

use Exception;

/**
 * MirelException — Custom exception for Mirel Tower API errors.
 *
 * Thrown when the API returns a non-success response (HTTP 4xx/5xx),
 * when rate limiting is hit, when token quota is exhausted, or when
 * any cURL / network error occurs during communication.
 *
 * @package Mirel\TowerSdk\Exception
 */
class MirelException extends Exception
{
    /** @var int|null HTTP status code from the API response */
    private ?int $httpCode;

    /** @var array|null Raw response data from the API */
    private ?array $responseData;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?int $httpCode = null,
        ?array $responseData = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
        $this->responseData = $responseData;
    }

    /**
     * Get the HTTP status code from the API response.
     */
    public function getHttpCode(): ?int
    {
        return $this->httpCode;
    }

    /**
     * Get the raw response data from the API.
     */
    public function getResponseData(): ?array
    {
        return $this->responseData;
    }
}
