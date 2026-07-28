<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class AccountReservationsTest extends TestCase
{
    private array $sessionUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        // Create an authenticated session with database UUID
        $this->sessionUser = [
            'library.user' => [
                'id' => '00000000-0000-0000-0000-000000000001', // Placeholder UUID
                'name' => 'Test User',
                'email' => 'user@example.com',
                'role' => 'reader',
                'crmUserId' => 'crm-user-123',
            ],
        ];
    }

    public function test_reservations_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/account/reservations');

        $response->assertUnauthorized()
                 ->assertJsonStructure(['authenticated', 'message'])
                 ->assertJsonPath('authenticated', false);
    }

    public function test_reservations_with_invalid_status_filter_returns_empty_or_error(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->getJson('/api/v1/account/reservations?status=INVALID_STATUS');

        // Should either return empty results or a 422 validation error
        $this->assertThat(
            $response->status(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(422)
            )
        );

        // Ensure response has expected structure regardless of status
        if ($response->status() === 200) {
            $response->assertJsonStructure(['authenticated', 'data', 'meta']);
        } else {
            $response->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_reservations_with_pagination_params_success(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->getJson('/api/v1/account/reservations?page=1&per_page=10');

        $response->assertOk()
                 ->assertJsonStructure(['authenticated', 'data', 'meta'])
                 ->assertJsonPath('authenticated', true);
        $this->assertIsArray($response->json('data'));
    }

    public function test_reservations_data_items_have_required_structure(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->getJson('/api/v1/account/reservations');

        $response->assertOk()
                 ->assertJsonStructure(['authenticated', 'data', 'meta']);

        $data = $response->json('data');
        if (is_array($data) && count($data) > 0) {
            foreach ($data as $item) {
                $this->assertArrayHasKey('id', $item, 'Each reservation item must have an id');
                $this->assertArrayHasKey('status', $item, 'Each reservation item must have a status');
            }
        }
    }

    public function test_reservations_response_has_correct_authentication_flag(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->getJson('/api/v1/account/reservations');

        $response->assertOk()
                 ->assertJsonStructure(['authenticated', 'data', 'meta'])
                 ->assertJsonPath('authenticated', true);
    }

    public function test_reservations_unauthenticated_response_structure(): void
    {
        $response = $this->getJson('/api/v1/account/reservations');

        $response->assertUnauthorized();
        $this->assertTrue(
            $response->json('error') !== null ||
            $response->json('message') !== null,
            'Error response should contain error or message field'
        );
    }
}
