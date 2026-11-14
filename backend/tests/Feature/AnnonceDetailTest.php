<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnonceDetailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createCatalogueTables();
        $this->seedCatalogueReferences();
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', 'UTC'));

        $this->createAnnonce(1, 'OBJET', 1, 'Bougie', '12.00');
        $this->createAnnonce(2, 'REPAS', 1, 'Couscous', '8.50');
        $this->createAnnonce(3, 'REPAS', 2, 'Soupe', '6.00');

        DB::table('repas')->insert([
            ['id' => 1, 'annonce_id' => 2, 'vendeur_id' => 1],
            ['id' => 2, 'annonce_id' => 3, 'vendeur_id' => 2],
        ]);

        $this->createOffre(1);
        $this->createOffre(2, [
            'repas_id' => 2,
            'annonce_id' => 3,
            'vendeur_id' => 2,
            'lieu_retrait_id' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_a_visitor_can_read_an_object_and_its_public_seller_details(): void
    {
        $this->getJson('/api/v1/annonces/1')
            ->assertOk()
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.titre', 'Bougie')
            ->assertJsonPath('data.prix', '12.00')
            ->assertJsonPath('data.vendeur', ['id' => 1, 'nom' => 'Camille'])
            ->assertJsonMissingPath('data.vendeur.email')
            ->assertJsonMissingPath('data.vendeur.password')
            ->assertJsonMissingPath('data.vendeur.remember_token')
            ->assertJsonCount(0, 'data.offres_repas');
    }

    public function test_a_meal_only_contains_its_own_offers_and_pickup_details(): void
    {
        $this->getJson('/api/v1/annonces/2')
            ->assertOk()
            ->assertJsonPath('data.titre', 'Couscous')
            ->assertJsonCount(1, 'data.offres_repas')
            ->assertJsonPath('data.offres_repas.0.id', 1)
            ->assertJsonPath('data.offres_repas.0.portions_restantes', 10)
            ->assertJsonPath('data.offres_repas.0.disponible', true)
            ->assertJsonPath('data.offres_repas.0.debut_retrait', '2026-11-13T17:00:00.000000Z')
            ->assertJsonPath('data.offres_repas.0.lieu_retrait.type', 'DOMICILE')
            ->assertJsonPath('data.offres_repas.0.lieu_retrait.zone.nom', 'Namur')
            ->assertJsonPath('data.offres_repas.0.lieu_retrait.commune.code_postal', '5000')
            ->assertJsonMissingPath('data.offres_repas.0.lieu_retrait.adresse');

        $this->assertDatabaseHas('lieux_retrait', [
            'id' => 1,
            'adresse' => 'Adresse fictive Namur',
        ]);
    }

    public function test_a_connected_user_cannot_read_an_exact_address_in_a_public_product(): void
    {
        $this->actingAs(User::findOrFail(2))
            ->getJson('/api/v1/annonces/2')
            ->assertOk()
            ->assertJsonPath('data.offres_repas.0.lieu_retrait.commune.nom', 'Namur')
            ->assertJsonMissingPath('data.offres_repas.0.lieu_retrait.adresse');
    }

    public function test_expired_and_empty_offers_are_unavailable_without_changing_stock(): void
    {
        foreach ([
            ['portions_restantes' => 0, 'limite_achat' => '2026-11-13 15:00:00'],
            ['portions_restantes' => 10, 'limite_achat' => '2026-11-11 15:00:00'],
            ['portions_restantes' => 10, 'limite_achat' => '2026-11-12 12:00:00'],
        ] as $attributes) {
            DB::table('offres_repas')->where('id', 1)->update($attributes);
            $this->getJson('/api/v1/annonces/2')->assertOk()
                ->assertJsonCount(1, 'data.offres_repas')
                ->assertJsonPath('data.offres_repas.0.disponible', false);
            $this->assertDatabaseHas('offres_repas', ['id' => 1, ...$attributes]);
        }
    }

    public function test_old_inactive_offers_are_not_public(): void
    {
        $this->createOffre(3, ['est_active' => false]);
        $this->getJson('/api/v1/annonces/2')->assertOk()
            ->assertJsonCount(1, 'data.offres_repas')
            ->assertJsonMissingPath('data.offres_repas.0.lieu_retrait.adresse');
    }

    public function test_an_unknown_announcement_returns_a_json_404(): void
    {
        $this->getJson('/api/v1/annonces/999')->assertNotFound();
    }

    public function test_a_non_numeric_identifier_returns_a_json_404(): void
    {
        $this->getJson('/api/v1/annonces/inconnu')->assertNotFound();
    }

    private function createAnnonce(int $id, string $type, int $vendeurId, string $titre, string $prix): void
    {
        DB::table('annonces')->insert([
            'id' => $id,
            'vendeur_id' => $vendeurId,
            'type' => $type,
            'titre' => $titre,
            'description' => 'Annonce de test',
            'prix' => $prix,
            'image' => '/storage/produits/demo-bougie.jpg',
            'categorie' => 'Test',
            'etat_objet' => $type === 'OBJET' ? 'DISPONIBLE' : null,
            'lieu_retrait_id' => $type === 'OBJET' ? 1 : null,
            'debut_retrait' => $type === 'OBJET' ? '2026-11-13 17:00:00' : null,
            'fin_retrait' => $type === 'OBJET' ? '2026-11-13 18:00:00' : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createOffre(int $id, array $attributes = []): void
    {
        DB::table('offres_repas')->insert(array_replace([
            'id' => $id,
            'repas_id' => 1,
            'annonce_id' => 2,
            'vendeur_id' => 1,
            'lieu_retrait_id' => 1,
            'portions_restantes' => 10,
            'limite_achat' => '2026-11-13 15:00:00',
            'debut_retrait' => '2026-11-13 17:00:00',
            'fin_retrait' => '2026-11-13 18:00:00',
        ], $attributes));
    }
}
