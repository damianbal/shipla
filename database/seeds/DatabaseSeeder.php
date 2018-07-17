<?php

use Illuminate\Database\Seeder;
use App\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UsersTableSeeder::class);

        // create user
        User::create([
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin@@@'),
            'name' => 'Admin'
        ]);
    }
}
