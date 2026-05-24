<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'superAdmin',
            'email' => 'safina@gmail.com',
            'password' => 'safina123',
            'role' => 'superadmin',
        ]);
    }
}