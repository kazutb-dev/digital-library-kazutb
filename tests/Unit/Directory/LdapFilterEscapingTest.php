<?php

namespace Tests\Unit\Directory;

use App\Directory\LdapActiveDirectoryClient;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class LdapFilterEscapingTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function filterMetacharacters(): array
    {
        return [
            'asterisk' => ['*', '\\2a'],
            'opening parenthesis' => ['(', '\\28'],
            'closing parenthesis' => [')', '\\29'],
            'backslash' => ['\\', '\\5c'],
            'nul' => ["\0", '\\00'],
        ];
    }

    #[DataProvider('filterMetacharacters')]
    public function test_ldap_filter_metacharacters_are_escaped(string $input, string $expected): void
    {
        if (! function_exists('ldap_escape')) {
            $this->markTestSkipped('LDAP extension is not available in this PHP runtime.');
        }

        $escape = new ReflectionMethod(LdapActiveDirectoryClient::class, 'escape');

        $this->assertSame($expected, $escape->invoke(new LdapActiveDirectoryClient, $input));
    }
}
