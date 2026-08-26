<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sizes = ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44'];

        foreach ($sizes as $size) {
            \Illuminate\Support\Facades\DB::table('sizes')->insert([
                'size' => $size,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
