<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MasterDemoSeeder extends Seeder
{
    public function run()
    {
        // Ensure roles/permissions/admin baseline exists.
        $this->call('RbacSeeder');

        // ERP demo population in strict dependency order.
        $this->call('BranchSeeder');
        $this->call('UserSeeder');
        $this->call('ProductSeeder');
        $this->call('InventorySeeder');
        $this->call('OrderSeeder');
    }
}
