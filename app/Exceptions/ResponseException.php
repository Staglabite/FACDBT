<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResponseException extends Exception
{
    protected $data;

    protected $httpCode;

    /**
     * Create a new custom exception instance.
     */
    public function __construct(
        string $message = 'Internal server error',
        int $httpCode = 500,
        mixed $data = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->data = $data;
        $this->httpCode = $httpCode;
    }

    /**
     * Get the returned data.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Get the HTTP status code.
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $this->getMessage(),
        ];

        // Add data to response if it exists
        if ($this->data !== null) {
            $response['data'] = $this->data;
        }

        // Add error code if it's not 0
        if ($this->getCode() !== 0) {
            $response['error_code'] = $this->getCode();
        }

        return response()->json($response, $this->httpCode);
    }

    /**
     * Static factory methods for common HTTP errors
     */
    public static function fromStatusCode(string $message, mixed $data = null, $statusCode = 500): self
    {
        return match ($statusCode) {
            400 => new self($message ?? 'Bad Request', 400, $data),
            401 => new self($message ?? 'Unauthorized', 401, $data),
            403 => new self($message ?? 'Forbidden', 403, $data),
            404 => new self($message ?? 'Not Found', 404, $data),
            422 => new self($message ?? 'Unprocessable Entity', 422, $data),
            default => new self($message ?? 'Internal Server Error', 500, $data)
        };
    }
}
