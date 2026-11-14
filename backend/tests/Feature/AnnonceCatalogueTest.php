<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnnonceCatalogueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createCatalogueTables();
        $this->seedCatalogueReferences();

        $annonces = [];

        for ($id = 1; $id <= 18; $id++) {
            $type = $id <= 10 ? 'OBJET' : 'REPAS';

            $annonces[] = [
                'id' => $id,
                'vendeur_id' => $id > 14 ? 2 : 1,
                'type' => $type,
                'titre' => 'Annonce '.$id,
                'description' => 'Annonce de test',
                'prix' => '12.00',
                'image' => '/storage/produits/demo-bougie.jpg',
                'categorie' => 'Test',
                'etat_objet' => $type === 'OBJET' ? 'DISPONIBLE' : null,
                'lieu_retrait_id' => $type === 'OBJET' ? 1 : null,
                'debut_retrait' => $type === 'OBJET' ? '2030-01-01 17:00:00' : null,
                'fin_retrait' => $type === 'OBJET' ? '2030-01-01 18:00:00' : null,
            ];
        }

        DB::table('annonces')->insert($annonces);
        foreach (range(11, 18) as $id) {
            DB::table('repas')->insert([
                'id' => $id,
                'annonce_id' => $id,
                'vendeur_id' => $id > 14 ? 2 : 1,
            ]);

            DB::table('offres_repas')->insert([
                'id' => $id,
                'repas_id' => $id,
                'annonce_id' => $id,
                'vendeur_id' => $id > 14 ? 2 : 1,
                'lieu_retrait_id' => $id > 14 ? 2 : 1,
                'portions_restantes' => 10,
                'limite_achat' => '2030-01-01 15:00:00',
                'debut_retrait' => '2030-01-01 17:00:00',
                'fin_retrait' => '2030-01-01 18:00:00',
            ]);
        }
    }

    #[DataProvider('catalogueFilters')]
    public function test_it_paginates_after_filtering(
        ?string $type,
        int $total,
        int $count,
        int $lastPage,
    ): void {
        $url = '/api/v1/annonces'.($type === null ? '' : '?type='.$type);

        $response = $this->getJson($url)
            ->assertOk()
            ->assertJsonCount($count, 'data')
            ->assertJsonPath('meta.total', $total)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', $lastPage);

        if ($type !== null) {
            foreach ($response->json('data') as $annonce) {
                $this->assertSame($type, $annonce['type']);
            }
        }

        if ($lastPage === 1) {
            $response->assertJsonPath('links.next', null);
        }
    }

    /**
     * @return array<string, array{?string, int, int, int}>
     */
    public static function catalogueFilters(): array
    {
        return [
            'tout le catalogue' => [null, 18, 12, 2],
            'tous les repas' => ['REPAS', 8, 8, 1],
            'toutes les creations' => ['OBJET', 10, 10, 1],
        ];
    }

    public function test_it_keeps_the_type_in_pagination_links(): void
    {
        $repas = (array) DB::table('annonces')->where('type', 'REPAS')->first();
        unset($repas['id']);

        DB::table('annonces')->insert(array_fill(0, 5, $repas));

        $response = $this->getJson('/api/v1/annonces?type=REPAS')
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.total', 13)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertStringContainsString('type=REPAS', $response->json('links.next'));

        $this->getJson($response->json('links.next'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'REPAS')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 13);
    }

    public function test_search_finds_a_product_beyond_the_first_page(): void
    {
        $this->getJson('/api/v1/annonces?q=18')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 18)
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_zone_and_type_filters_are_combined_before_pagination(): void
    {
        $this->getJson('/api/v1/annonces?zone_id=2&type=REPAS')->assertOk()
            ->assertJsonCount(4, 'data')->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.zone', 'Bruxelles');
    }

    public function test_it_rejects_an_unknown_type(): void
    {
        $this->getJson('/api/v1/annonces?type=INCONNU')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }
}
