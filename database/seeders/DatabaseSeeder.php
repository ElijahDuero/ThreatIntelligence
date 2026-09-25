<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'operator@darkdump.local'],
            [
                'name' => 'Operator Prime',
                'password' => Hash::make('DarkDump@2026!'),
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@darkdump.io'],
            [
                'name' => 'Security Admin',
                'password' => Hash::make('password'),
            ]
        );
    }
}
