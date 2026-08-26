<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $colors = ['Negro', 'Blanco', 'Café', 'Azul', 'Rojo', 'Beige', 'Gris', 'Rosa', 'Vino'];

        foreach ($colors as $color) {
            \Illuminate\Support\Facades\DB::table('colors')->insert([
                'name' => $color,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
