<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Dolibarr-compatible API key authentication.
 *
 * Replicates DolibarrApiAccess::__isAllowed() (htdocs/api/class/api_access.class.php):
 * the key is read from, in order of precedence:
 *   - the `api_key` query parameter (deprecated upstream, kept for compatibility)
 *   - the `DOLAPIKEY` query parameter
 *   - the `DOLAPIKEY` HTTP header (recommended, overrides the query string)
 *   - the `Authorization: Bearer <key>` header, only when nothing else was sent
 *
 * Keys are configured via the DOLIBARR_API_KEYS env var, a JSON map of
 * key => {"login": string, "entity": int} — the static equivalent of the
 * upstream `llx_user.api_key`/`llx_user.entity` columns. For local
 * convenience a bare comma-separated list of keys is also accepted and maps
 * to login `api-client`, entity `1`.
 *
 * Every authenticated request is scoped to the key's `entity` (upstream
 * `$conf->entity`). As upstream, a `DOLAPIENTITY` request header may assert
 * the entity and is rejected with 401 when it differs from the key's own.
 * Downstream code reads the scope through App\Security\EntityContext or the
 * `_dolibarr_entity` / `_dolibarr_login` request attributes.
 *
 * To migrate to OIDC/JWT later, replace this authenticator (and the
 * custom_authenticators entry in security.yaml) with an access-token
 * handler — everything downstream only relies on the authenticated
 * ApiClientUser token, not on this class.
 */
final class ApiKeyAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    /** @var array<string, array{login: string, entity: int}> */
    private readonly array $apiKeys;

    public function __construct(
        #[Autowire('%env(DOLIBARR_API_KEYS)%')]
        string $apiKeys,
    ) {
        $this->apiKeys = self::parseKeys($apiKeys);
    }

    public function supports(Request $request): bool
    {
        return null !== $this->extractApiKey($request);
    }

    public function authenticate(Request $request): Passport
    {
        $apiKey = $this->extractApiKey($request);
        \assert(null !== $apiKey);

        if (1 === preg_match('/^dolcrypt:/i', $apiKey)) {
            throw new DolibarrAuthenticationException(
                Response::HTTP_SERVICE_UNAVAILABLE,
                'Bad value for the API key. An API key should not start with dolcrypt:',
            );
        }

        $info = $this->findKey($apiKey);
        if (null === $info) {
            // Upstream deliberately returns one generic message whatever failed
            // (unknown key, disabled user, bad validity) as anti brute-force
            // protection, after a 1s sleep. Same shape here.
            sleep(1);
            throw new DolibarrAuthenticationException(
                Response::HTTP_UNAUTHORIZED,
                sprintf(
                    'Error user not valid (not found with api key or bad status or bad validity dates)'
                    . ' (conf->entity=%d)',
                    $this->requestedEntity($request),
                ),
            );
        }

        $entity = $info['entity'];
        if ($request->headers->has('DOLAPIENTITY')) {
            $requested = (int) $request->headers->get('DOLAPIENTITY', '0');
            if ($requested !== $entity) {
                throw new DolibarrAuthenticationException(
                    Response::HTTP_UNAUTHORIZED,
                    sprintf(
                        'functions_isallowed::check_user_api_key Authentication KO for \'%s\':'
                        . ' Token not valid (may be a typo or a wrong entity)',
                        $info['login'],
                    ),
                );
            }
        }

        $client = new ApiClientUser($info['login'], $entity);

        return new SelfValidatingPassport(
            new UserBadge($info['login'], static fn (): ApiClientUser => $client),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof ApiClientUser) {
            $request->attributes->set('_dolibarr_login', $user->getLogin());
            $request->attributes->set('_dolibarr_entity', $user->getEntity());
        }

        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $status = $exception instanceof DolibarrAuthenticationException
            ? $exception->getStatusCode()
            : Response::HTTP_UNAUTHORIZED;

        return ApiError::response($status, $exception->getMessageKey());
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return ApiError::response(
            Response::HTTP_UNAUTHORIZED,
            "Failed to login to API. Neither parameter 'HTTP_DOLAPIKEY' nor 'Authentication: Bearer'"
            . ' found on HTTP header (and no parameter DOLAPIKEY in URL).',
        );
    }

    /**
     * @return array<string, array{login: string, entity: int}>
     */
    private static function parseKeys(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (\is_array($decoded)) {
            $keys = [];
            foreach ($decoded as $key => $info) {
                $keys[(string) $key] = [
                    'login' => (string) (\is_array($info) ? ($info['login'] ?? 'api-client') : $info),
                    'entity' => \is_array($info) ? (int) ($info['entity'] ?? 1) : 1,
                ];
            }

            return $keys;
        }

        // Backward-compatible fallback: bare comma-separated key list, entity 1.
        $keys = [];
        foreach (explode(',', $raw) as $key) {
            $key = trim($key);
            if ('' !== $key) {
                $keys[$key] = ['login' => 'api-client', 'entity' => 1];
            }
        }

        return $keys;
    }

    /**
     * Extraction order mirrors upstream: query api_key, then query DOLAPIKEY,
     * then the DOLAPIKEY header wins; Authorization: Bearer is only a
     * fallback when nothing else was provided.
     */
    private function extractApiKey(Request $request): ?string
    {
        $apiKey = $request->query->get('api_key');
        if ($request->query->has('DOLAPIKEY')) {
            $apiKey = $request->query->get('DOLAPIKEY');
        }

        if ($request->headers->has('DOLAPIKEY')) {
            $apiKey = $request->headers->get('DOLAPIKEY');
        } elseif (null === $apiKey || '' === $apiKey) {
            $authorization = $request->headers->get('Authorization');
            if (null !== $authorization) {
                $apiKey = preg_replace('/^Bearer\s+/i', '', $authorization);
            }
        }

        if (null === $apiKey) {
            return null;
        }

        // dol_string_nounprintableascii(): strip non printable-ascii chars
        $apiKey = preg_replace('/[^\x20-\x7E]/', '', $apiKey) ?? '';

        return '' === $apiKey ? null : $apiKey;
    }

    /**
     * @return array{login: string, entity: int}|null
     */
    private function findKey(string $apiKey): ?array
    {
        $match = null;
        foreach ($this->apiKeys as $knownKey => $info) {
            if (hash_equals($knownKey, $apiKey)) {
                $match = $info;
            }
        }

        return $match;
    }

    private function requestedEntity(Request $request): int
    {
        $entity = (int) $request->headers->get('DOLAPIENTITY', '0');

        return $entity > 0 ? $entity : 1;
    }
}
