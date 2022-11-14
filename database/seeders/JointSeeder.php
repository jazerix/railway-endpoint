<?php

namespace Database\Seeders;

use App\Models\Joint;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use League\Csv\Statement;
use Location\Coordinate;
use Location\Distance\Vincenty;

class JointSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $csv = Reader::createFromPath(storage_path('railjoints.csv'));
        $csv->setDelimiter(";");
        $csv->setHeaderOffset(0);
        $statement = Statement::create();

        $chunk = new Collection();
        foreach($statement->process($csv) as $record) {
            $lat = str_replace(',', '.', $record['GPS_NORTH']);
            $long = str_replace(',', '.', $record['GPS_EAST']);
            $chunk[] = [
                'location'           => $record['Placering'],
                'technical_location' => $record['Teknisk plads'],
                'equipment'          => $record['Equipment'],
                'description'        => $record['Betegnelse'],
                'track_number'       => $record['Spornummer'],
                'from_kilometers'    => $record['Fra kilometer'],
                'coordinates'        => blank($lat) ? null : DB::raw("ST_SRID(POINT($long, $lat), 4326)"),
                'position_location'  => $record['Placering_Lokation']
            ];

            if($chunk->count() == 100) {
                Joint::insert($chunk->toArray());
                $chunk = new Collection();
            }
        }
    }
}
