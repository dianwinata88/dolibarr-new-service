<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Dolibarr-compatible API key authentication.
 *
 * Upstream accepts the key in the DOLAPIKEY header or the api_key query
 * parameter; the same applies here. Keys are compared against the
 * comma-separated DOLIBARR_API_KEYS env var.
 *
 * To migrate to OIDC/JWT later, replace this authenticator (and the
 * custom_authenticators entry in security.yaml) with an access-token
 * handler — everything downstream only relies on the authenticated
 * token, not on this class.
 */
final class ApiKeyAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    /** @var list<string> */
    private readonly array $apiKeys;

    public function __construct(
        #[Autowire('%env(DOLIBARR_API_KEYS)%')]
        string $apiKeys,
    ) {
        $this->apiKeys = array_values(array_filter(array_map(trim(...), explode(',', $apiKeys))));
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('DOLAPIKEY') || $request->query->has('api_key');
    }

    public function authenticate(Request $request): Passport
    {
        $apiKey = $request->headers->get('DOLAPIKEY') ?? (string) $request->query->get('api_key');

        if ('' === $apiKey || !$this->isValid($apiKey)) {
            throw new AuthenticationException('Invalid or missing API key.');
        }

        return new SelfValidatingPassport(
            new UserBadge($apiKey, static fn (): InMemoryUser => new InMemoryUser('api-client', null, ['ROLE_API'])),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['error' => 'Forbidden', 'message' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED,
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            ['error' => 'Unauthorized', 'message' => 'An API key is required (DOLAPIKEY header or api_key parameter).'],
            Response::HTTP_UNAUTHORIZED,
        );
    }

    private function isValid(string $apiKey): bool
    {
        foreach ($this->apiKeys as $knownKey) {
            if (hash_equals($knownKey, $apiKey)) {
                return true;
            }
        }

        return false;
    }
}
