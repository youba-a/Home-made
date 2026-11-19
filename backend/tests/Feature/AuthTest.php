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
            'first_name' => 'Nouvelle',
            'last_name' => 'Cliente',
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
            'first_name' => 'Autre',
            'last_name' => 'Personne',
            'email' => 'VENDEUR@EXAMPLE.TEST',
            'password' => 'mot-de-passe-test',
            'password_confirmation' => 'mot-de-passe-test',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 2);
        $this->assertGuest();
    }

    public function test_a_new_account_can_register_with_a_structured_profile(): void
    {
        $this->getJson('/api/v1/communes')->assertOk()->assertJsonFragment([
            'id' => 1, 'nom' => 'Namur', 'code_postal' => '5000',
        ]);

        $this->postJson('/api/v1/auth/inscription', [
            'first_name' => 'Marie Claire',
            'last_name' => 'Van den Berg',
            'commune_id' => 1,
            'email' => 'marie@example.test',
            'password' => 'mot-de-passe-test',
            'password_confirmation' => 'mot-de-passe-test',
        ])->assertCreated()
            ->assertJsonPath('data.display_name', 'Marie Claire Van den Berg')
            ->assertJsonPath('data.first_name', 'Marie Claire')
            ->assertJsonPath('data.last_name', 'Van den Berg')
            ->assertJsonPath('data.commune.nom', 'Namur');
        $this->assertDatabaseHas('users', ['email' => 'marie@example.test', 'first_name' => 'Marie Claire']);
    }

    public function test_only_a_connected_user_can_update_their_own_profile(): void
    {
        $data = ['first_name' => 'Camille', 'last_name' => 'Martin', 'commune_id' => 2];
        $this->patchJson('/api/v1/auth/profil', $data)->assertUnauthorized();

        $this->actingAs(User::findOrFail(1));
        $this->patchJson('/api/v1/auth/profil', [...$data, 'id' => 2, 'commune_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors('commune_id');
        $this->patchJson('/api/v1/auth/profil', [...$data, 'id' => 2])
            ->assertOk()->assertJsonPath('data.commune.nom', 'Bruxelles');
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.display_name', 'Camille Martin');
        $this->assertDatabaseHas('users', ['id' => 1, 'first_name' => 'Camille', 'last_name' => 'Martin']);
        $this->assertDatabaseHas('users', ['id' => 2, 'first_name' => 'Noé', 'commune_id' => null]);

        $this->patchJson('/api/v1/auth/profil', [...$data, 'commune_id' => null])
            ->assertOk()->assertJsonPath('data.commune', null);
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $this->postJson('/api/v1/auth/inscription', [
            'first_name' => 'Marie',
            'last_name' => 'Cliente',
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
        ])->assertOk()->assertJsonPath('data.display_name', 'Camille')->assertJsonMissingPath('data.password');

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
