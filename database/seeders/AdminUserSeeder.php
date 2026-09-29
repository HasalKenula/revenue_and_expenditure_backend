<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin already exists to avoid duplicates
        if (!User::where('email', 'admin@finsystem.com')->exists()) {
            User::create([
                'name' => 'System Admin',
                'email' => 'admin@finsystem.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]);
        }
    }
}