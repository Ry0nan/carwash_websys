<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insertOrIgnore([
            'full_name'     => 'Super Admin',
            'email'         => 'admin@carwash.local',
            'password_hash' => Hash::make('Admin@1234'),
            'role'          => 'ADMIN',
            'status'        => 'ACTIVE',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
