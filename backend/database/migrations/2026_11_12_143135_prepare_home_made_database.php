<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Cette installation utilise MySQL.');
        }

        $sql = preg_replace('/^\s*--[^\r\n]*/m', '', file_get_contents(database_path('home_made.sql')));
        $tables = [];

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if (preg_match('/^CREATE TABLE (\w+)/', $statement, $match)) {
                $tables[$match[1]] = $statement;
                if (! Schema::hasTable($match[1])) {
                    DB::statement($statement);
                }
            }
        }

        if (! Schema::hasColumn('annonces', 'lieu_retrait_id')) {
            Schema::table('annonces', function (Blueprint $table): void {
                $table->unsignedBigInteger('lieu_retrait_id')->nullable();
                $table->dateTime('debut_retrait')->nullable();
                $table->dateTime('fin_retrait')->nullable();
            });

            DB::statement("UPDATE annonces a
                JOIN users u ON u.id = a.vendeur_id
                JOIN (SELECT vendeur_id, MIN(id) AS lieu_id FROM lieux_retrait GROUP BY vendeur_id) l
                    ON l.vendeur_id = a.vendeur_id
                SET a.lieu_retrait_id = l.lieu_id,
                    a.debut_retrait = TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
                    a.fin_retrait = TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
                WHERE a.type = 'OBJET' AND u.email IN (
                    'vendeur.demo@example.test', 'atelier.demo@example.test', 'cuisine.demo@example.test'
                )");

            if (DB::table('annonces')->where('type', 'OBJET')->whereNull('lieu_retrait_id')->exists()) {
                throw new RuntimeException('Renseigner le retrait des objets existants avant de poursuivre.');
            }

            DB::statement("ALTER TABLE annonces
                ADD KEY idx_annonces_lieu_vendeur (lieu_retrait_id, vendeur_id),
                ADD CONSTRAINT fk_annonces_lieu FOREIGN KEY (lieu_retrait_id, vendeur_id)
                    REFERENCES lieux_retrait (id, vendeur_id),
                ADD CONSTRAINT chk_annonces_retrait CHECK (
                    (type = 'OBJET' AND lieu_retrait_id IS NOT NULL
                        AND debut_retrait IS NOT NULL AND fin_retrait IS NOT NULL AND debut_retrait < fin_retrait)
                    OR (type = 'REPAS' AND lieu_retrait_id IS NULL
                        AND debut_retrait IS NULL AND fin_retrait IS NULL)
                )");
        }

        if (Schema::hasColumn('offres_repas', 'limite_validation')) {
            if (DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
                ->where('CONSTRAINT_NAME', 'chk_offres_horaires')->exists()) {
                DB::statement('ALTER TABLE offres_repas DROP CHECK chk_offres_horaires');
            }
            Schema::table('offres_repas', function (Blueprint $table): void {
                $table->renameColumn('limite_validation', 'limite_achat');
            });
        }

        if (! DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('CONSTRAINT_NAME', 'chk_offres_horaires')->exists()) {
            DB::statement('ALTER TABLE offres_repas ADD CONSTRAINT chk_offres_horaires
                CHECK (limite_achat < debut_retrait AND debut_retrait < fin_retrait)');
        }

        if (! Schema::hasColumn('offres_repas', 'est_active')) {
            Schema::table('offres_repas', function (Blueprint $table): void {
                $table->boolean('est_active')->default(false);
            });

            DB::statement('UPDATE offres_repas o
                JOIN (SELECT repas_id, MIN(id) AS offre_id FROM offres_repas GROUP BY repas_id) selection
                    ON selection.repas_id = o.repas_id
                SET o.est_active = (o.id = selection.offre_id)');

            DB::statement("UPDATE offres_repas o
                JOIN annonces a ON a.id = o.annonce_id
                JOIN users u ON u.id = a.vendeur_id
                SET o.limite_achat = TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '15:00:00'),
                    o.debut_retrait = TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '17:00:00'),
                    o.fin_retrait = TIMESTAMP(DATE_ADD(UTC_DATE(), INTERVAL 2 DAY), '18:00:00')
                WHERE o.est_active = TRUE AND u.email IN (
                    'vendeur.demo@example.test', 'atelier.demo@example.test', 'cuisine.demo@example.test'
                )");

            DB::statement('ALTER TABLE offres_repas
                MODIFY est_active BOOLEAN NOT NULL DEFAULT TRUE,
                ADD repas_actif_id BIGINT UNSIGNED GENERATED ALWAYS AS (
                    CASE WHEN est_active = TRUE THEN repas_id ELSE NULL END
                ) STORED,
                ADD UNIQUE KEY uq_offres_repas_actif (repas_actif_id),
                ADD CONSTRAINT chk_offres_active CHECK (est_active IN (FALSE, TRUE))');
        }

        if (Schema::hasColumn('commandes', 'decision_en_cours')) {
            if (DB::table('commandes')->exists() || DB::table('operations_disponibilite')->exists()) {
                throw new RuntimeException('Un historique de commandes exige une migration de données dédiée.');
            }
            Schema::drop('commandes');
            DB::statement($tables['commandes']);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Cette migration conserve la base existante et ne se supprime pas par rollback.');
    }
};
