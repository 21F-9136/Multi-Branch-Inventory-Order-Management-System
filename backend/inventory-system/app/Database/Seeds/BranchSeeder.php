<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $branches = [
            ['code' => 'KHI', 'name' => 'Karachi', 'address' => 'Shahrah-e-Faisal, PECHS Block 6, Karachi', 'status' => 'active'],
            ['code' => 'LHE', 'name' => 'Lahore', 'address' => 'Main Boulevard Gulberg III, Lahore', 'status' => 'active'],
            ['code' => 'ISB', 'name' => 'Islamabad', 'address' => 'Jinnah Avenue, Blue Area, Islamabad', 'status' => 'active'],
            ['code' => 'FSD', 'name' => 'Faisalabad', 'address' => 'D Ground, Peoples Colony 1, Faisalabad', 'status' => 'active'],
            ['code' => 'MUX', 'name' => 'Multan', 'address' => 'Cantt Commercial Area, Multan', 'status' => 'active'],
        ];

        foreach ($branches as $branch) {
            $existing = $this->db->table('branches')
                ->groupStart()
                ->where('code', $branch['code'])
                ->orWhere('name', $branch['name'])
                ->groupEnd()
                ->where('deleted_at', null)
                ->get()
                ->getFirstRow('array');

            if ($existing) {
                $this->db->table('branches')
                    ->where('id', (int) $existing['id'])
                    ->update([
                        'code' => $branch['code'],
                        'name' => $branch['name'],
                        'address' => $branch['address'],
                        'status' => $branch['status'],
                        'updated_at' => $now,
                    ]);
                continue;
            }

            $this->db->table('branches')->insert([
                'code' => $branch['code'],
                'name' => $branch['name'],
                'address' => $branch['address'],
                'status' => $branch['status'],
                'manager_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
