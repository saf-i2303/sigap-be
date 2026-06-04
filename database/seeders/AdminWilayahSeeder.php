<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminWilayahSeeder extends Seeder
{
    public function run(): void
    {
        // Admin Wilayah Tapos
        User::create([
            'name'     => 'Admin Wilayah Tapos',
            'email'    => 'tapos@gmail.com',
            'password' => Hash::make('password123'),
            'role'     => 'admin',
            'wilayah'  => 'tapos',
        ]);

        // Admin Wilayah Depok 2
        User::create([
            'name'     => 'Admin Wilayah Depok 2',
            'email'    => 'depok2@gmail.com',
            'password' => Hash::make('password123'),
            'role'     => 'admin',
            'wilayah'  => 'depok_2',
        ]);
    }
}