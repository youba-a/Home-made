<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnoncePublicationTest extends TestCase
{
    public function test_a_visitor_cannot_publish_an_object(): void
    {
        $this->postJson('/api/v1/annonces')->assertUnauthorized();
    }

    public function test_a_connected_seller_can_publish_an_object_with_a_photo(): void
    {
        $this->createCatalogueTables();
        $this->seedCatalogueReferences();
        Storage::fake('public');
        $this->travelTo(Carbon::parse('2029-01-01'));

        $response = $this->actingAs(User::findOrFail(1))->post('/api/v1/annonces', $this->objectData(), [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.vendeur.id', 1)
            ->assertJsonPath('data.zone', 'Namur')
            ->assertJsonPath('data.disponible', true)
            ->assertJsonMissingPath('data.retrait.lieu.adresse');

        [$photo] = Storage::disk('public')->allFiles('produits');
        $this->assertTrue(Storage::disk('public')->exists($photo));

        $this->assertDatabaseHas('annonces', [
            'id' => $response->json('data.id'),
            'vendeur_id' => 1,
            'titre' => 'Lampe en bois',
            'image' => '/storage/'.$photo,
            'debut_retrait' => '2030-02-01 13:00:00',
        ]);

        $this->getJson('/api/v1/annonces?q=Lampe')
            ->assertOk()
            ->assertJsonPath('data.0.id', $response->json('data.id'));
    }

    public function test_an_unknown_commune_does_not_create_an_announcement_or_photo(): void
    {
        $this->createCatalogueTables();
        $this->seedCatalogueReferences();
        Storage::fake('public');

        $this->actingAs(User::findOrFail(1))->post('/api/v1/annonces', [
            ...$this->objectData(),
            'code_postal' => '9999',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('commune');

        $this->assertDatabaseMissing('annonces', ['titre' => 'Lampe en bois']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_seller_can_publish_a_meal_with_its_first_offer(): void
    {
        $this->createCatalogueTables();
        $this->seedCatalogueReferences();
        Storage::fake('public');
        $this->travelTo(Carbon::parse('2029-01-01'));

        $data = [
            ...$this->objectData(),
            'type' => 'REPAS',
            'titre' => 'Lasagnes maison',
            'portions_restantes' => 5,
            'limite_achat' => '2030-02-01T12:00',
        ];

        $this->actingAs(User::findOrFail(1))->post('/api/v1/annonces', [
            ...$data,
            'limite_achat' => $data['debut_retrait'],
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limite_achat');
        $this->assertDatabaseMissing('annonces', ['titre' => 'Lasagnes maison']);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $response = $this->post('/api/v1/annonces', [
            ...$data,
            'image' => UploadedFile::fake()->image('lasagnes.png'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'REPAS')
            ->assertJsonPath('data.zone', 'Namur')
            ->assertJsonPath('data.disponible', true)
            ->assertJsonMissingPath('data.offres_repas.0.lieu.adresse');

        $id = $response->json('data.id');
        $this->assertDatabaseHas('annonces', ['id' => $id, 'lieu_retrait_id' => null]);
        $this->assertDatabaseHas('repas', ['annonce_id' => $id, 'vendeur_id' => 1]);
        $this->assertDatabaseHas('offres_repas', [
            'annonce_id' => $id,
            'portions_restantes' => 5,
            'est_active' => true,
            'limite_achat' => '2030-02-01 11:00:00',
        ]);
        $this->assertCount(1, Storage::disk('public')->allFiles('produits'));

        $this->createOrdersTable();
        $offerId = DB::table('offres_repas')->where('annonce_id', $id)->value('id');
        $url = '/api/v1/mes-annonces/'.$id;
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.adresse', 'Adresse de test')
            ->assertJsonPath('data.commune_id', 1)
            ->assertJsonPath('data.limite_achat', '2030-02-01T12:00');
        unset($data['image'], $data['commune'], $data['code_postal']);
        $data['commune_id'] = 2;
        $data['portions_restantes'] = 7;
        $this->post($url, [...$data, '_method' => 'PATCH'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.commune', 'Bruxelles');
        $this->assertDatabaseHas('offres_repas', ['id' => $offerId, 'portions_restantes' => 7]);

        DB::table('commandes')->insert(['annonce_id' => $id, 'statut' => 'CONFIRMEE']);
        $this->patchJson($url, $data)->assertConflict();
    }

    public function test_only_the_owner_can_edit_an_unsold_object_and_keep_its_photo(): void
    {
        $this->createCatalogueTables();
        $this->seedCatalogueReferences();
        $this->createOrdersTable();
        Storage::fake('public');
        $this->travelTo(Carbon::parse('2029-01-01'));
        $data = $this->objectData();
        $seller = User::findOrFail(1);
        $id = $this->actingAs($seller)->post('/api/v1/annonces', $data, ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');
        $url = '/api/v1/mes-annonces/'.$id;
        $photo = DB::table('annonces')->where('id', $id)->value('image');
        unset($data['image']);

        $this->actingAs(User::findOrFail(2))->getJson($url)->assertNotFound();
        $this->patchJson($url, $data)->assertNotFound();

        $this->actingAs($seller)->patchJson($url, [
            ...$data,
            'type' => 'REPAS',
            'titre' => 'Lampe rénovée',
        ])->assertOk()->assertJsonPath('data.type', 'OBJET');
        $this->assertDatabaseHas('annonces', ['id' => $id, 'titre' => 'Lampe rénovée', 'image' => $photo]);

        DB::table('annonces')->where('id', $id)->update(['etat_objet' => 'VENDU']);
        $this->patchJson($url, $data)->assertConflict();
    }

    private function createOrdersTable(): void
    {
        Schema::create('commandes', function (Blueprint $table): void {
            $table->unsignedBigInteger('annonce_id');
            $table->string('statut');
        });
    }

    private function objectData(): array
    {
        return [
            'type' => 'OBJET',
            'titre' => 'Lampe en bois',
            'description' => 'Une lampe fabriquée à la main.',
            'categorie' => 'Décoration',
            'prix' => '30.00',
            'image' => UploadedFile::fake()->image('lampe.png'),
            'type_lieu' => 'POINT',
            'adresse' => 'Adresse de test',
            'commune' => 'Namur',
            'code_postal' => '5000',
            'debut_retrait' => '2030-02-01T14:00',
            'fin_retrait' => '2030-02-01T15:00',
        ];
    }
}
