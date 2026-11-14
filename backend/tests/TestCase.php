<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function createCatalogueTables(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('zones', function (Blueprint $table): void {
            $table->id();
            $table->string('nom');
        });
        Schema::create('communes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('zone_id');
            $table->string('nom');
            $table->string('code_postal');
        });
        Schema::create('lieux_retrait', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vendeur_id');
            $table->unsignedBigInteger('zone_id');
            $table->unsignedBigInteger('commune_id');
            $table->string('type');
            $table->string('adresse');
        });
        Schema::create('annonces', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vendeur_id');
            $table->string('type');
            $table->string('titre');
            $table->text('description');
            $table->decimal('prix', 10, 2);
            $table->string('image');
            $table->string('categorie');
            $table->string('etat_objet')->nullable();
            $table->unsignedBigInteger('lieu_retrait_id')->nullable();
            $table->dateTime('debut_retrait')->nullable();
            $table->dateTime('fin_retrait')->nullable();
        });
        Schema::create('repas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('annonce_id');
            $table->unsignedBigInteger('vendeur_id');
        });
        Schema::create('offres_repas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('repas_id');
            $table->unsignedBigInteger('annonce_id');
            $table->unsignedBigInteger('vendeur_id');
            $table->unsignedBigInteger('lieu_retrait_id');
            $table->integer('portions_restantes');
            $table->boolean('est_active')->default(true);
            $table->unsignedBigInteger('repas_actif_id')->nullable()
                ->storedAs('CASE WHEN est_active = 1 THEN repas_id ELSE NULL END')->unique();
            $table->dateTime('limite_achat');
            $table->dateTime('debut_retrait');
            $table->dateTime('fin_retrait');
        });
    }

    protected function seedCatalogueReferences(): void
    {
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Camille', 'email' => 'vendeur@example.test', 'password' => Hash::make('mot-de-passe-test'), 'remember_token' => 'jeton-prive'],
            ['id' => 2, 'name' => 'Noé', 'email' => 'autre@example.test', 'password' => Hash::make('mot-de-passe-test'), 'remember_token' => null],
        ]);
        DB::table('zones')->insert([['id' => 1, 'nom' => 'Namur'], ['id' => 2, 'nom' => 'Bruxelles']]);
        DB::table('communes')->insert([
            ['id' => 1, 'zone_id' => 1, 'nom' => 'Namur', 'code_postal' => '5000'],
            ['id' => 2, 'zone_id' => 2, 'nom' => 'Bruxelles', 'code_postal' => '1000'],
        ]);
        DB::table('lieux_retrait')->insert([
            ['id' => 1, 'vendeur_id' => 1, 'zone_id' => 1, 'commune_id' => 1, 'type' => 'DOMICILE', 'adresse' => 'Adresse fictive Namur'],
            ['id' => 2, 'vendeur_id' => 2, 'zone_id' => 2, 'commune_id' => 2, 'type' => 'POINT', 'adresse' => 'Point fictif Bruxelles'],
        ]);
    }
}
