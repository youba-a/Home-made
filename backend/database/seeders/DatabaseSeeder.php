<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Les données de démonstration nécessitent MySQL.');
        }

        $this->call(CommuneSeeder::class);
        DB::unprepared(file_get_contents(database_path('donnees_demo.sql')));
    }
}
