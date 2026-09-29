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
        User::updateOrCreate(
            [
                'email' => env('ADMIN_EMAIL'),
            ],
            [
                'name' => 'Administrator',
                'password' => Hash::make(env('ADMIN_PASSWORD')),
                'role' => User::ROLE_ADMIN,
            ]
        );

<<<<<<< HEAD
        User::updateOrCreate(
            [
                'email' => env('ADMIN_EMAIL'),
            ],
            [
                'name' => 'Administrator',
                'password' => Hash::make(env('ADMIN_PASSWORD')),
                'role' => User::ROLE_ADMIN,
            ]
        );
=======
>>>>>>> 5dda833a70f4e6d016c5b572a7097833c4248005
    }
}
