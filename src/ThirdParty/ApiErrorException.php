<?php

declare(strict_types=1);

namespace App\ThirdParty;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A Dolibarr RestException equivalent: carried status code + message + extra
 * keys merged into the `{"error": {...}}` body (upstream merges error arrays,
 * e.g. `{"error":{"code":500,"message":"...","0":"sql error"}}`).
 */
final class ApiErrorException extends HttpException
{
    /** @var array<int|string, string> */
    private readonly array $extra;

    /**
     * @param array<int|string, string>|string $extra extra entries appended to
     *        the error object (numeric keys keep upstream's "0","1" indexes)
     */
    public function __construct(int $statusCode, string $message = '', array|string $extra = [], ?\Throwable $previous = null)
    {
        $this->extra = is_array($extra) ? $extra : [$extra];

        parent::__construct($statusCode, $message, $previous);
    }

    /**
     * @return array<int|string, string>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * Upstream error envelope.
     *
     * @return array{error: array{code: int, message: string}}
     */
    public function toBody(): array
    {
        $body = ['code' => $this->getStatusCode(), 'message' => $this->getMessage()];
        foreach ($this->extra as $key => $value) {
            $body[$key] = $value;
        }

        return ['error' => $body];
    }
}
