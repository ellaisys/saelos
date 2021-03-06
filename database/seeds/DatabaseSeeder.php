<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            InstallSeeder::class,
            FieldTableSeeder::class,
            StageSeeder::class,
            StatusSeeder::class
        ]);
    }
}
