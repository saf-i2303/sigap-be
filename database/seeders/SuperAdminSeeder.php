<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash; // <-- Wajib import ini

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Super Admin Safina',
            'email' => 'safina@gmail.com',
            'password' => Hash::make('safina123'), 
            'role' => 'superadmin',
        ]);
    }
}