<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommuneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $communesByZone = [
            'Namur' => [
                'Namur' => '5000',
                'Andenne' => '5300',
                'Floreffe' => '5150',
                'Gembloux' => '5030',
                'Profondeville' => '5170',
            ],
            'Bruxelles' => [
                'Bruxelles' => '1000',
                'Etterbeek' => '1040',
                'Ixelles' => '1050',
                'Schaerbeek' => '1030',
                'Uccle' => '1180',
            ],
            'Liège' => [
                'Liège' => '4000',
                'Ans' => '4430',
                'Fléron' => '4620',
                'Herstal' => '4040',
                'Seraing' => '4100',
            ],
            'Mons' => [
                'Mons' => '7000',
                'Ghlin' => '7011',
                'Jemappes' => '7012',
            ],
            'Charleroi' => [
                'Charleroi' => '6000',
                'Marcinelle' => '6001',
                'Gosselies' => '6041',
            ],
            'Anvers' => [
                'Anvers' => '2000',
                'Deurne' => '2100',
                'Merksem' => '2170',
            ],
            'Gand' => [
                'Gand' => '9000',
                'Mariakerke' => '9030',
                'Gentbrugge' => '9050',
            ],
            'Bruges' => [
                'Bruges' => '8000',
                'Sint-Andries' => '8200',
                'Assebroek' => '8310',
            ],
        ];

        DB::transaction(function () use ($communesByZone): void {
            foreach ($communesByZone as $zone => $communes) {
                DB::table('zones')->insertOrIgnore(['nom' => $zone]);
                $zoneId = DB::table('zones')->where('nom', $zone)->value('id');

                $rows = [];
                foreach ($communes as $name => $postalCode) {
                    $rows[] = [
                        'zone_id' => $zoneId,
                        'nom' => $name,
                        'code_postal' => $postalCode,
                    ];
                }

                DB::table('communes')->insertOrIgnore($rows);
            }
        });
    }
}
