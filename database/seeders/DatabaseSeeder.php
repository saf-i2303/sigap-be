<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Eksekusi seeder secara modular dan rapi
        $this->call([
            SuperAdminSeeder::class,   
            AdminWilayahSeeder::class, 
            CategorySeeder::class,     
        ]);
    }
}