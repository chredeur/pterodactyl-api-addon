<?php

namespace Chredeur\PterodactylApiAddon\Services;

use Pterodactyl\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Issues and consumes the single use tokens backing the SSO endpoint.
 *
 * Tokens live in the cache rather than the database so the addon ships no migration and
 * expiry is handled by the cache driver. Only a hash of the token is stored, so a dump of
 * the cache does not hand out usable sessions.
 */
class SsoTokenService
{
    /**
     * Tokens are meant to be redeemed by an immediate redirect, so the window is short.
     */
    public const TTL_SECONDS = 60;

    private const CACHE_PREFIX = 'pterodactyl-api-addon:sso:';

    public function __construct(private CacheRepository $cache)
    {
    }

    /**
     * Creates a token for the given user and returns its plaintext value.
     */
    public function issue(User $user, ?string $redirect = null): string
    {
        $token = bin2hex(random_bytes(32));

        $this->cache->put($this->key($token), [
            'user_id' => $user->id,
            'redirect' => $redirect,
        ], self::TTL_SECONDS);

        return $token;
    }

    /**
     * Returns the payload of a token and removes it, or null if it does not exist.
     *
     * pull() is a get followed by a forget. On a driver without a truly atomic pull two
     * simultaneous requests could in theory both read the token before either deletes it.
     * The window is a few milliseconds on a 32 byte random value, which is not a practical
     * concern, but it is the reason this is not presented as a hard guarantee.
     *
     * @return array{user_id: int, redirect: string|null}|null
     */
    public function consume(string $token): ?array
    {
        return $this->cache->pull($this->key($token));
    }

    private function key(string $token): string
    {
        return self::CACHE_PREFIX . hash('sha256', $token);
    }
}
