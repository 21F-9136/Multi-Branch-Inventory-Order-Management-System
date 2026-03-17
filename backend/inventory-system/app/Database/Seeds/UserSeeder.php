<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $branchRows = $this->db->table('branches')
            ->select('id, code, name')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        if ($branchRows === []) {
            throw new RuntimeException('No branches found. Run BranchSeeder first.');
        }

        $branchMap = [];
        foreach ($branchRows as $branch) {
            $code = strtoupper((string) ($branch['code'] ?? ''));
            if ($code !== '') {
                $branchMap[$code] = (int) $branch['id'];
            }

            $nameKey = strtoupper(substr((string) ($branch['name'] ?? ''), 0, 3));
            if ($nameKey !== '' && !isset($branchMap[$nameKey])) {
                $branchMap[$nameKey] = (int) $branch['id'];
            }
        }

        $roleRows = $this->db->table('roles')->select('id, name')->get()->getResultArray();
        $roleMap = [];
        foreach ($roleRows as $role) {
            $roleMap[strtolower((string) $role['name'])] = (int) $role['id'];
        }

        $adminRoleId = $roleMap['admin'] ?? ($roleMap['super_admin'] ?? null);
        $managerRoleId = $roleMap['manager'] ?? null;
        $salesRoleId = $roleMap['sales'] ?? null;

        if ($managerRoleId === null || $salesRoleId === null) {
            throw new RuntimeException('Required roles are missing. Run RbacSeeder first.');
        }

        // Ensure admin user exists and is active.
        $adminId = $this->upsertUser(
            'admin@erp.com',
            'System Admin',
            null,
            $now
        );
        if ($adminRoleId !== null) {
            $this->attachRole($adminId, $adminRoleId, $now);
        }

        $managerSpecs = [
            ['email' => 'manager.karachi@erp.com', 'name' => 'Manager Karachi', 'branch_code' => 'KHI'],
            ['email' => 'manager.lahore@erp.com', 'name' => 'Manager Lahore', 'branch_code' => 'LHE'],
            ['email' => 'manager.islamabad@erp.com', 'name' => 'Manager Islamabad', 'branch_code' => 'ISB'],
            ['email' => 'manager.faisalabad@erp.com', 'name' => 'Manager Faisalabad', 'branch_code' => 'FSD'],
            ['email' => 'manager.multan@erp.com', 'name' => 'Manager Multan', 'branch_code' => 'MUX'],
        ];

        foreach ($managerSpecs as $spec) {
            $branchId = $branchMap[$spec['branch_code']] ?? null;
            if ($branchId === null) {
                continue;
            }

            $userId = $this->upsertUser($spec['email'], $spec['name'], $branchId, $now);
            $this->attachRole($userId, $managerRoleId, $now);

            $this->db->table('branches')
                ->where('id', $branchId)
                ->update([
                    'manager_id' => $userId,
                    'updated_at' => $now,
                ]);
        }

        $salesSpecs = [
            ['city' => 'karachi', 'branch_code' => 'KHI'],
            ['city' => 'lahore', 'branch_code' => 'LHE'],
            ['city' => 'islamabad', 'branch_code' => 'ISB'],
            ['city' => 'faisalabad', 'branch_code' => 'FSD'],
            ['city' => 'multan', 'branch_code' => 'MUX'],
        ];

        foreach ($salesSpecs as $spec) {
            $branchId = $branchMap[$spec['branch_code']] ?? null;
            if ($branchId === null) {
                continue;
            }

            for ($i = 1; $i <= 2; $i++) {
                $email = sprintf('sales.%s.%d@erp.com', $spec['city'], $i);
                $name = sprintf('Sales %s %d', ucfirst($spec['city']), $i);

                $userId = $this->upsertUser($email, $name, $branchId, $now);
                $this->attachRole($userId, $salesRoleId, $now);
            }
        }
    }

    private function upsertUser(string $email, string $name, ?int $branchId, string $now): int
    {
        $existing = $this->db->table('users')
            ->where('email', $email)
            ->where('deleted_at', null)
            ->get()
            ->getFirstRow('array');

        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);

        if ($existing) {
            $this->db->table('users')
                ->where('id', (int) $existing['id'])
                ->update([
                    'name' => $name,
                    'branch_id' => $branchId,
                    'is_active' => 1,
                    'password_hash' => $passwordHash,
                    'updated_at' => $now,
                ]);

            return (int) $existing['id'];
        }

        $this->db->table('users')->insert([
            'name' => $name,
            'email' => $email,
            'branch_id' => $branchId,
            'password_hash' => $passwordHash,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return (int) $this->db->insertID();
    }

    private function attachRole(int $userId, int $roleId, string $now): void
    {
        $exists = $this->db->table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->get()
            ->getFirstRow('array');

        if ($exists) {
            return;
        }

        $this->db->table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
