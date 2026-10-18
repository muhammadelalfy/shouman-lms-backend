<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\IdentityDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed reference data required in every environment. Demo/test data
     * belongs in factories, never here.
     */
    public function run(): void
    {
        $this->call(IdentityDatabaseSeeder::class);
    }
}
