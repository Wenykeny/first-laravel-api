<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::factory(10)->create();
        \App\Models\Task::factory(10)->create();
        \App\Models\Todo::factory(10)->create();
        // DB::table('users')->insert([
        //     'name' => Str::random(3),
        //     'email' => Str::random(3).'@gmail.com',
        //     'password' => Str::random(3),
        // ]);
    }
}
