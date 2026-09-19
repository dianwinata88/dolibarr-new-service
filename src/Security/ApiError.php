<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Renders errors in the upstream Dolibarr REST API shape:
 *
 *     {"error": {"code": <http status>, "message": "<message>"}}
 *
 * (Luracast Restler's Compose::message() format.)
 */
final class ApiError
{
    public static function response(int $code, string $message): JsonResponse
    {
        return new JsonResponse(
            ['error' => ['code' => $code, 'message' => $message]],
            $code,
        );
    }
}
