<?php

namespace App\Services\Admin;

use RuntimeException;
use Symfony\Component\Process\Process;

class VerifiedBackupService
{
    /** @return list<array<string,mixed>> */
    public function backups(): array
    {
        $files = glob($this->directory().'/*.dump') ?: [];
        rsort($files);

        return array_map(function (string $path): array {
            $manifest = $this->readManifest($path);

            return [
                'name' => basename($path),
                'size' => filesize($path) ?: 0,
                'modified_at' => date(DATE_ATOM, filemtime($path) ?: time()),
                'sha256' => $manifest['sha256'] ?? hash_file('sha256', $path),
                'verified' => ($manifest['sha256'] ?? null) === hash_file('sha256', $path),
                'restore_test' => $manifest['restore_test'] ?? null,
            ];
        }, $files);
    }

    /** @return array<string,mixed> */
    public function create(): array
    {
        $this->ensureDirectory();
        $name = 'digital_library_'.now('UTC')->format('Ymd\THis\Z').'_'.bin2hex(random_bytes(3)).'.dump';
        $path = $this->directory().'/'.$name;
        $process = $this->process(['pg_dump', '--host='.$this->host(), '--port='.$this->port(), '--username='.$this->username(), '--dbname='.$this->database(), '--format=custom', '--file='.$path]);
        $process->mustRun();
        $toc = $this->process(['pg_restore', '--list', $path]);
        $toc->mustRun();
        file_put_contents($path.'.restore.list', $toc->getOutput(), LOCK_EX);
        $manifest = [
            'source_database' => $this->database(),
            'created_at' => now('UTC')->toIso8601String(),
            'size' => filesize($path),
            'sha256' => hash_file('sha256', $path),
            'toc_readable' => true,
            'restore_test' => null,
        ];
        file_put_contents($path.'.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

        return ['name' => $name, ...$manifest];
    }

    /** @return array<string,mixed> */
    public function restoreToTest(string $name): array
    {
        $path = $this->resolve($name);
        $manifest = $this->readManifest($path);
        $hash = hash_file('sha256', $path);
        if (! is_string($hash) || ! hash_equals((string) ($manifest['sha256'] ?? ''), $hash)) {
            throw new RuntimeException('Backup checksum verification failed.');
        }
        $target = 'digital_library_restore_'.now('UTC')->format('Ymd_His').'_'.bin2hex(random_bytes(2)).'_test';
        $this->process(['createdb', '--host='.$this->host(), '--port='.$this->port(), '--username='.$this->username(), $target])->mustRun();
        $this->process(['pg_restore', '--host='.$this->host(), '--port='.$this->port(), '--username='.$this->username(), '--dbname='.$target, '--no-owner', '--no-privileges', $path], 600)->mustRun();

        $pdo = new \PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s', $this->host(), $this->port(), $target), $this->username(), $this->password(), [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $counts = [];
        foreach (['bibliographic_records', 'book_copies', 'users', 'migrations'] as $table) {
            $counts[$table] = (int) $pdo->query('select count(*) from '.$table)->fetchColumn();
        }
        $manifest['restore_test'] = ['database' => $target, 'verified_at' => now('UTC')->toIso8601String(), 'counts' => $counts];
        file_put_contents($path.'.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

        return $manifest['restore_test'];
    }

    private function process(array $command, int $timeout = 300): Process
    {
        $process = new Process($command, base_path(), ['PGPASSWORD' => $this->password()]);
        $process->setTimeout($timeout);

        return $process;
    }

    private function resolve(string $name): string
    {
        if ($name !== basename($name) || ! preg_match('/^[A-Za-z0-9_.-]+\.dump$/', $name)) {
            throw new RuntimeException('Invalid backup name.');
        }
        $path = realpath($this->directory().'/'.$name);
        $root = realpath($this->directory());
        if ($path === false || $root === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Backup not found.');
        }

        return $path;
    }

    /** @return array<string,mixed> */
    private function readManifest(string $path): array
    {
        $raw = is_file($path.'.json') ? file_get_contents($path.'.json') : false;
        $manifest = $raw === false ? null : json_decode($raw, true);

        return is_array($manifest) ? $manifest : [];
    }

    private function ensureDirectory(): void
    {
        if (! is_dir($this->directory()) && ! mkdir($concurrentDirectory = $this->directory(), 0770, true) && ! is_dir($concurrentDirectory)) {
            throw new RuntimeException('Backup directory unavailable.');
        }
    }

    private function directory(): string
    {
        return storage_path('app/backups/admin');
    }

    private function host(): string
    {
        return (string) config('database.connections.pgsql.host');
    }

    private function port(): string
    {
        return (string) config('database.connections.pgsql.port');
    }

    private function database(): string
    {
        return (string) config('database.connections.pgsql.database');
    }

    private function username(): string
    {
        return (string) config('database.connections.pgsql.username');
    }

    private function password(): string
    {
        return (string) config('database.connections.pgsql.password');
    }
}
