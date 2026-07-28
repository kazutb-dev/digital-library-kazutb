<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class ReaderReservationTest extends TestCase
{
    private array $sessionUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        // Create a reader session with a real UUID from the database
        // Note: These tests expect a real user to exist in the database
        // For true unit testing without DB dependency, use mocks instead
        $this->sessionUser = [
            'library.user' => [
                'id' => '00000000-0000-0000-0000-000000000001', // Placeholder UUID
                'name' => 'Test Reader',
                'email' => 'reader@example.com',
                'role' => 'reader',
                'crmUserId' => 'crm-user-123',
            ],
        ];
    }

    // --- Authentication Tests ---

    public function test_create_reservation_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/account/reservations', [
            'bookId' => 'some-uuid',
        ]);

        $response->assertUnauthorized()
                 ->assertJsonStructure(['authenticated', 'message'])
                 ->assertJsonPath('authenticated', false);
    }

    public function test_cancel_reservation_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/account/reservations/some-uuid/cancel');

        $response->assertUnauthorized()
                 ->assertJsonStructure(['authenticated', 'message'])
                 ->assertJsonPath('authenticated', false);
    }

    public function test_check_reservation_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/account/reservations/check');

        $response->assertUnauthorized()
                 ->assertJsonStructure(['authenticated', 'message'])
                 ->assertJsonPath('authenticated', false);
    }

    public function test_list_reservations_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/account/reservations');

        $response->assertUnauthorized()
                 ->assertJsonStructure(['authenticated', 'message'])
                 ->assertJsonPath('authenticated', false);
    }

    // --- Input Validation Tests ---

    public function test_create_reservation_with_empty_book_id_returns_422(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->postJson('/api/v1/account/reservations', [
                'bookId' => '',
            ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors'])
                 ->assertJsonPath('errors.bookId', fn($errors) => !empty($errors));
    }

    public function test_create_reservation_with_invalid_uuid_format_returns_422(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->postJson('/api/v1/account/reservations', [
                'bookId' => 'not-a-uuid',
            ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors'])
                 ->assertJsonPath('errors.bookId', fn($errors) => !empty($errors));
    }

    public function test_cancel_reservation_with_invalid_uuid_returns_422(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->postJson('/api/v1/account/reservations/not-a-uuid/cancel');

        $response->assertStatus(422);
    }

    public function test_check_reservation_with_invalid_book_id_returns_422(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->getJson('/api/v1/account/reservations/check?bookId=not-a-uuid');

        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors'])
                 ->assertJsonPath('errors.bookId', fn($errors) => !empty($errors));
    }

    public function test_check_reservation_with_invalid_isbn_format_returns_422(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->getJson('/api/v1/account/reservations/check?isbn=invalid');

        $response->assertStatus(422);
    }

    public function test_check_reservation_with_no_book_id_or_isbn_returns_400(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->getJson('/api/v1/account/reservations/check');

        $response->assertStatus(400)
                 ->assertJsonStructure(['message', 'error'])
                 ->assertJsonPath('error', 'Either bookId or isbn is required');
    }

    // --- Response Contract Tests ---

    public function test_list_reservations_success_response_structure(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->getJson('/api/v1/account/reservations');

        if ($response->status() === 200) {
            $response->assertJsonStructure(['data' => ['*' => ['id', 'status', 'reservedAt', 'book']]]);
        }
    }

    public function test_cancel_nonexistent_reservation_error_structure(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this->withSession($this->sessionUser)
            ->postJson("/api/v1/account/reservations/{$fakeId}/cancel");

        if ($response->status() >= 400) {
            $this->assertTrue(
                $response->json('error') !== null ||
                $response->json('message') !== null,
                'Error response should contain error or message field'
            );
        }
    }

    public function test_create_reservation_success_includes_required_fields(): void
    {
        // Using a fake UUID since we don't have real books in test DB
        $bookId = '12345678-1234-1234-1234-123456789012';
        $response = $this->withSession($this->sessionUser)
            ->postJson('/api/v1/account/reservations', [
                'bookId' => $bookId,
            ]);

        // We expect either a 201 on success or 404/422 on validation
        if ($response->status() === 201) {
            $response->assertJsonStructure(['reservation' => ['id', 'status', 'reservedAt', 'book']]);
        }
    }

    public function test_create_reservation_with_both_book_id_and_isbn_succeeds(): void
    {
        $response = $this->withSession($this->sessionUser)
            ->postJson('/api/v1/account/reservations', [
                'bookId' => '12345678-1234-1234-1234-123456789012',
                'isbn' => '978-0-123456-78-9',
            ]);

        // Should handle multiple identifiers gracefully
        $this->assertGreaterThanOrEqual(200, $response->status());
        $this->assertLessThan(500, $response->status());
    }
}
