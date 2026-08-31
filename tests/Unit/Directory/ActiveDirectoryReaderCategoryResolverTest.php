<?php

namespace Tests\Unit\Directory;

use App\Directory\ActiveDirectoryReaderCategoryResolver;
use App\Directory\ActiveDirectoryUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActiveDirectoryReaderCategoryResolverTest extends TestCase
{
    /** @return iterable<string, array{ActiveDirectoryUser, string}> */
    public static function directoryIdentities(): iterable
    {
        yield 'teacher title' => [self::identity(title: 'Associate Professor'), 'teacher'];
        yield 'teacher group' => [self::identity(groups: ['CN=Faculty,OU=Groups,DC=example,DC=test']), 'teacher'];
        yield 'student group' => [self::identity(groups: ['CN=Students,OU=Groups,DC=example,DC=test']), 'student'];
        yield 'employee department' => [self::identity(department: 'University Administration'), 'employee'];
        yield 'unknown corporate identity' => [self::identity(), 'employee'];
        yield 'ambiguous student teacher is least privilege' => [
            self::identity(title: 'Lecturer', groups: ['CN=Students,OU=Groups,DC=example,DC=test']),
            'student',
        ];
    }

    #[DataProvider('directoryIdentities')]
    public function test_it_uses_only_directory_owned_audience_evidence(ActiveDirectoryUser $identity, string $expected): void
    {
        $this->assertSame($expected, (new ActiveDirectoryReaderCategoryResolver)->resolve($identity));
    }

    /** @param list<string> $groups */
    private static function identity(?string $department = null, ?string $title = null, array $groups = []): ActiveDirectoryUser
    {
        return new ActiveDirectoryUser(
            objectGuid: '12345678-1234-1234-1234-123456789abc',
            samAccountName: 'reader01',
            distinguishedName: 'CN=Reader,OU=Users,DC=example,DC=test',
            enabled: true,
            department: $department,
            title: $title,
            groups: $groups,
        );
    }
}
