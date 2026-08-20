<?php

namespace App\Directory;

use App\Exceptions\ActiveDirectoryException;

final readonly class ActiveDirectoryService
{
    public function __construct(private ActiveDirectoryClientInterface $client) {}

    public function authenticate(string $identifier, string $password): ActiveDirectoryUser
    {
        $login = $this->normalizeLogin($identifier);
        $user = $this->client->findByLogin($login);
        if ($user === null || ! $user->enabled || ! $this->client->verifyCredentials($user->distinguishedName, $password)) {
            throw new ActiveDirectoryException('invalid_credentials');
        }

        return $user;
    }

    public function health(): ActiveDirectoryHealth
    {
        return $this->client->healthCheck();
    }

    /** @return list<ActiveDirectoryUser> */
    public function search(string $term, int $limit = 20): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2 || mb_strlen($term) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $term)) {
            return [];
        }

        return $this->client->search($term, $limit);
    }

    public function normalizeLogin(string $identifier): string
    {
        $identifier = mb_strtolower(trim($identifier));
        if (str_contains($identifier, '\\')) {
            $identifier = (string) strrchr($identifier, '\\');
            $identifier = ltrim($identifier, '\\');
        }
        if (str_contains($identifier, '@')) {
            [$identifier] = explode('@', $identifier, 2);
        }
        if ($identifier === '' || mb_strlen($identifier) > 128 || ! preg_match('/^[\pL\pN._-]+$/u', $identifier)) {
            throw new ActiveDirectoryException('invalid_credentials');
        }

        return $identifier;
    }
}
