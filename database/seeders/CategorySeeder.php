<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = ['Zapatos Casuales', 'Zapatos de Vestir', 'Tenis Deportivos', 'Botas', 'Botines', 'Sandalias', 'Tacones', 'Mocasines'];

        foreach ($categories as $category) {
            \Illuminate\Support\Facades\DB::table('categories')->insert([
                'name' => $category,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
