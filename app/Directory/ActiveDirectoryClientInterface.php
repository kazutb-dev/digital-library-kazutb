<?php

namespace App\Directory;

interface ActiveDirectoryClientInterface
{
    public function healthCheck(): ActiveDirectoryHealth;

    public function findByLogin(string $login): ?ActiveDirectoryUser;

    /** @return list<ActiveDirectoryUser> */
    public function search(string $term, int $limit = 20): array;

    public function verifyCredentials(string $distinguishedName, string $password): bool;
}
