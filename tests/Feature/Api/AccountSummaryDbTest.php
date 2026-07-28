<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountSummaryDbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');
    }

    public function test_account_summary_requires_session_auth(): void
    {
        $response = $this->getJson('/api/v1/account/summary');

        $response
            ->assertStatus(401)
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_account_summary_returns_linked_reader_profile_when_email_matches(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for account summary endpoint test.');
        }

        $email = DB::table('app.reader_contacts')
            ->where('contact_type', 'EMAIL')
            ->whereNotNull('value_normalized_key')
            ->value('value_normalized_key');

        if (! is_string($email) || $email === '') {
            $this->markTestSkipped('No reader email found to test account summary linkage.');
        }

        $response = $this
            ->withSession([
                'library.user' => [
                    'id' => 'crm-user-1',
                    'name' => 'Session User',
                    'ad_login' => $email,
                    'role' => 'reader',
                    'email' => $email,
                ],
            ])
            ->getJson('/api/v1/account/summary');

        $response
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('data.reader.linked', true)
            ->assertJsonPath('data.stats.readerProfilesFound', 1)
            ->assertJsonStructure([
                'authenticated',
                'data' => [
                    'user' => ['id', 'name', 'ad_login', 'role', 'email'],
                    'reader' => ['linked', 'id', 'fullName', 'legacyCode', 'registrationAt', 'reregistrationAt', 'needsReview', 'primaryEmail'],
                    'stats' => ['readerProfilesFound', 'readerContacts', 'openReaderReviewTasks'],
                ],
                'source',
            ]);
    }

    private function canUseLivePgsql(): bool
    {
        try {
            DB::connection('pgsql')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
