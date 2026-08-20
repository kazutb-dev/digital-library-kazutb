<?php

namespace App\Directory;

use App\Exceptions\ActiveDirectoryException;

final class LdapActiveDirectoryClient implements ActiveDirectoryClientInterface
{
    public function healthCheck(): ActiveDirectoryHealth
    {
        $started = microtime(true);
        try {
            $connection = $this->serviceConnection();
            $result = @ldap_read($connection, $this->baseDn(), '(objectClass=*)', ['dn'], 0, 1, $this->timeout());
            if ($result === false) {
                throw new ActiveDirectoryException('base_search_failed');
            }
            @ldap_unbind($connection);

            return new ActiveDirectoryHealth(true, round((microtime(true) - $started) * 1000, 1));
        } catch (ActiveDirectoryException $exception) {
            return new ActiveDirectoryHealth(false, round((microtime(true) - $started) * 1000, 1), $exception->category);
        }
    }

    public function findByLogin(string $login): ?ActiveDirectoryUser
    {
        $filter = $this->andFilter($this->configuredUserFilter(), sprintf('(%s=%s)', $this->loginField(), $this->escape($login)));
        $users = $this->performSearch($filter, 2);
        if (count($users) > 1) {
            throw new ActiveDirectoryException('ambiguous_identity');
        }

        return $users[0] ?? null;
    }

    public function search(string $term, int $limit = 20): array
    {
        $escaped = $this->escape($term);
        $identity = '(|('.$this->loginField().'=*'.$escaped.'*)(displayName=*'.$escaped.'*)(mail=*'.$escaped.'*))';

        return $this->performSearch($this->andFilter($this->configuredUserFilter(), $identity), max(1, min(50, $limit)));
    }

    public function verifyCredentials(string $distinguishedName, string $password): bool
    {
        if ($password === '' || ! $this->withinBase($distinguishedName)) {
            return false;
        }
        $connection = $this->connection();
        $verified = @ldap_bind($connection, $distinguishedName, $password);
        $error = $verified ? 0 : ldap_errno($connection);
        @ldap_unbind($connection);
        if (! $verified && $error !== 49) {
            throw new ActiveDirectoryException('user_bind_failed');
        }

        return $verified;
    }

    /** @return list<ActiveDirectoryUser> */
    private function performSearch(string $filter, int $limit): array
    {
        $connection = $this->serviceConnection();
        $attributes = ['objectGUID', 'sAMAccountName', 'userPrincipalName', 'distinguishedName', 'displayName', 'givenName', 'sn', 'mail', 'telephoneNumber', 'department', 'title', 'employeeID', 'userAccountControl', 'memberOf'];
        $result = @ldap_search($connection, $this->baseDn(), $filter, $attributes, 0, $limit, $this->timeout());
        if ($result === false) {
            @ldap_unbind($connection);
            throw new ActiveDirectoryException('search_failed');
        }
        $entries = ldap_get_entries($connection, $result);
        @ldap_unbind($connection);
        $users = [];
        for ($index = 0; $index < (int) ($entries['count'] ?? 0); $index++) {
            $entry = $entries[$index];
            $dn = $this->value($entry, 'distinguishedname') ?: (string) ($entry['dn'] ?? '');
            if (! $this->withinBase($dn)) {
                continue;
            }
            $guid = $this->guid($entry['objectguid'][0] ?? null);
            $login = $this->value($entry, 'samaccountname');
            if ($guid === '' || $login === '' || $dn === '') {
                continue;
            }
            $uac = (int) ($this->value($entry, 'useraccountcontrol') ?: 0);
            $groups = [];
            for ($group = 0; $group < (int) ($entry['memberof']['count'] ?? 0); $group++) {
                $groups[] = (string) $entry['memberof'][$group];
            }
            $users[] = new ActiveDirectoryUser(
                objectGuid: $guid,
                samAccountName: $login,
                distinguishedName: $dn,
                enabled: ($uac & 2) === 0,
                userPrincipalName: $this->nullable($entry, 'userprincipalname'),
                displayName: $this->nullable($entry, 'displayname'),
                givenName: $this->nullable($entry, 'givenname'),
                surname: $this->nullable($entry, 'sn'),
                mail: $this->nullable($entry, 'mail'),
                telephoneNumber: $this->nullable($entry, 'telephonenumber'),
                department: $this->nullable($entry, 'department'),
                title: $this->nullable($entry, 'title'),
                employeeId: $this->nullable($entry, 'employeeid'),
                groups: $groups,
            );
        }

        return $users;
    }

    private function serviceConnection(): mixed
    {
        $connection = $this->connection();
        $dn = trim((string) config('active_directory.bind_dn'));
        $password = (string) config('active_directory.bind_password');
        if ($dn === '' || $password === '') {
            @ldap_unbind($connection);
            throw new ActiveDirectoryException('configuration_invalid');
        }
        if (! @ldap_bind($connection, $dn, $password)) {
            $error = ldap_errno($connection);
            @ldap_unbind($connection);
            throw new ActiveDirectoryException($error === 49 ? 'service_bind_failed' : 'secure_connection_failed');
        }

        return $connection;
    }

    private function connection(): mixed
    {
        $host = trim((string) config('active_directory.host'));
        if ($host === '' || preg_match('/[^A-Za-z0-9._:-]/', $host)) {
            throw new ActiveDirectoryException('configuration_invalid');
        }
        $useSsl = (bool) config('active_directory.use_ssl');
        $requireCertificate = (bool) config('active_directory.require_cert');
        $caCertificatePath = trim((string) config('active_directory.ca_cert_path'));
        if (! $useSsl || ! $requireCertificate) {
            throw new ActiveDirectoryException('configuration_invalid');
        }
        if ($requireCertificate && $caCertificatePath !== '') {
            if (! is_file($caCertificatePath) || ! is_readable($caCertificatePath)) {
                throw new ActiveDirectoryException('configuration_invalid');
            }
        }
        if (! extension_loaded('ldap')) {
            throw new ActiveDirectoryException('extension_unavailable');
        }
        if (! defined('LDAP_OPT_X_TLS_REQUIRE_CERT')
            || ! @ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND)) {
            throw new ActiveDirectoryException('configuration_invalid');
        }
        if ($caCertificatePath !== ''
            && (! defined('LDAP_OPT_X_TLS_CACERTFILE')
                || ! @ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $caCertificatePath))) {
            throw new ActiveDirectoryException('configuration_invalid');
        }
        $connection = @ldap_connect('ldaps://'.$host.':'.(int) config('active_directory.port'));
        if ($connection === false) {
            throw new ActiveDirectoryException('connection_failed');
        }
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, $this->timeout());

        return $connection;
    }

    private function configuredUserFilter(): string
    {
        $filter = trim((string) config('active_directory.user_filter'));
        if ($filter === '' || str_contains($filter, "\0") || ! str_starts_with($filter, '(') || ! str_ends_with($filter, ')')) {
            throw new ActiveDirectoryException('configuration_invalid');
        }

        return $filter;
    }

    private function loginField(): string
    {
        $field = trim((string) config('active_directory.login_field', 'samaccountname'));
        if (! preg_match('/^[A-Za-z][A-Za-z0-9-]{0,63}$/', $field)) {
            throw new ActiveDirectoryException('configuration_invalid');
        }

        return $field;
    }

    private function baseDn(): string
    {
        $base = trim((string) config('active_directory.base_dn'));
        if ($base === '') {
            throw new ActiveDirectoryException('configuration_invalid');
        }

        return $base;
    }

    private function withinBase(string $dn): bool
    {
        $dn = mb_strtolower(trim($dn));
        $base = mb_strtolower($this->baseDn());

        return $dn === $base || str_ends_with($dn, ','.$base);
    }

    private function timeout(): int
    {
        return max(1, min(30, (int) config('active_directory.timeout', 5)));
    }

    private function escape(string $value): string
    {
        return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
    }

    private function andFilter(string $left, string $right): string
    {
        return '(&'.$left.$right.')';
    }

    private function value(array $entry, string $key): string
    {
        return trim((string) ($entry[mb_strtolower($key)][0] ?? ''));
    }

    private function nullable(array $entry, string $key): ?string
    {
        $value = $this->value($entry, $key);

        return $value === '' ? null : $value;
    }

    private function guid(mixed $binary): string
    {
        if (! is_string($binary) || strlen($binary) !== 16) {
            return '';
        }
        $parts = unpack('V1a/v1b/v1c/H16d', $binary);

        return sprintf('%08x-%04x-%04x-%s-%s', $parts['a'], $parts['b'], $parts['c'], substr($parts['d'], 0, 4), substr($parts['d'], 4));
    }
}
