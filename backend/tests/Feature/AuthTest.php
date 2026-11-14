<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createCatalogueTables();
        $this->seedCatalogueReferences();
    }

    public function test_visitors_can_read_their_anonymous_session_and_request_csrf(): void
    {
        $this->getJson('/api/v1/auth/me')->assertOk()->assertExactJson(['data' => null]);
        $this->getJson('/api/v1/auth/csrf')->assertOk()->assertJsonStructure(['token']);
    }

    public function test_registration_hashes_the_password_and_opens_a_session(): void
    {
        $response = $this->postJson('/api/v1/auth/inscription', [
            'name' => 'Nouvelle cliente',
            'email' => 'CLIENTE@EXAMPLE.TEST',
            'password' => 'mot-de-passe-test',
            'password_confirmation' => 'mot-de-passe-test',
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'cliente@example.test');
        $response->assertJsonMissingPath('data.password');
        $user = User::where('email', 'cliente@example.test')->sole();
        $this->assertTrue(Hash::check('mot-de-passe-test', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_registration_rejects_an_existing_email_even_with_uppercase(): void
    {
        $this->postJson('/api/v1/auth/inscription', [
            'name' => 'Autre personne',
            'email' => 'VENDEUR@EXAMPLE.TEST',
            'password' => 'mot-de-passe-test',
            'password_confirmation' => 'mot-de-passe-test',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 2);
        $this->assertGuest();
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $this->postJson('/api/v1/auth/inscription', [
            'name' => 'Cliente',
            'email' => 'cliente@example.test',
            'password' => 'mot-de-passe-test',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 2);
    }

    public function test_login_and_logout_use_the_session(): void
    {
        $this->postJson('/api/v1/auth/connexion', [
            'email' => 'VENDEUR@EXAMPLE.TEST',
            'password' => 'mot-de-passe-test',
        ])->assertOk()->assertJsonPath('data.name', 'Camille')->assertJsonMissingPath('data.password');

        $this->assertAuthenticated();
        $this->postJson('/api/v1/auth/deconnexion')->assertOk();
        $this->assertGuest();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertExactJson(['data' => null]);
    }

    public function test_invalid_credentials_do_not_open_a_session(): void
    {
        $this->postJson('/api/v1/auth/connexion', [
            'email' => 'vendeur@example.test',
            'password' => 'incorrect',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertGuest();
    }
}
