<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Seeder;
use Config\Database;

class RbacSeeder extends Seeder
{
    public function run()
    {
        /** @var BaseConnection $db */
        $db = Database::connect();

        $roles = [
            'super_admin' => 'System super administrator',
            'manager'     => 'Branch manager',
            'sales'       => 'Sales user',
        ];

        $permissions = [
            'dashboard.view'  => 'View dashboard',
            'reports.view'    => 'View reports',

            'branches.read'   => 'View branches',
            'branches.manage' => 'Manage branches',

            'users.read'      => 'View users',
            'users.manage'    => 'Manage users',

            'products.read'   => 'View products',
            'products.manage' => 'Manage products',

            'skus.read'       => 'View SKUs',
            'skus.manage'     => 'Manage SKUs',

            'inventory.read'  => 'View inventory',
            'inventory.manage'=> 'Manage inventory',

            'orders.read'     => 'View orders',
            'orders.create'   => 'Create orders',
            'orders.place'    => 'Place orders',
        ];

        // Insert roles
        foreach ($roles as $name => $desc) {
            $existing = $db->table('roles')->where('name', $name)->get()->getFirstRow('array');
            if ($existing) continue;
            $db->table('roles')->insert([
                'name'        => $name,
                'description' => $desc,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        // Insert permissions
        foreach ($permissions as $code => $name) {
            $existing = $db->table('permissions')->where('code', $code)->get()->getFirstRow('array');
            if ($existing) continue;
            $db->table('permissions')->insert([
                'code'        => $code,
                'name'        => $name,
                'description' => null,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        // Build id maps
        $roleRows = $db->table('roles')->select('id,name')->get()->getResultArray();
        $permRows = $db->table('permissions')->select('id,code')->get()->getResultArray();

        $roleId = [];
        foreach ($roleRows as $r) {
            $roleId[(string) $r['name']] = (int) $r['id'];
        }

        $permId = [];
        foreach ($permRows as $p) {
            $permId[(string) $p['code']] = (int) $p['id'];
        }

        $grant = function (string $roleName, array $permissionCodes) use ($db, $roleId, $permId) {
            $rid = $roleId[$roleName] ?? null;
            if (!$rid) return;

            foreach ($permissionCodes as $code) {
                $pid = $permId[$code] ?? null;
                if (!$pid) continue;

                $exists = $db->table('role_permissions')
                    ->where('role_id', $rid)
                    ->where('permission_id', $pid)
                    ->get()
                    ->getFirstRow('array');

                if ($exists) continue;

                $db->table('role_permissions')->insert([
                    'role_id'       => $rid,
                    'permission_id' => $pid,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        };

        $all = array_keys($permissions);

        $grant('super_admin', $all);

        $grant('manager', [
            'dashboard.view',
            'reports.view',
            'branches.read',
            'inventory.read',
            'inventory.manage',
            'orders.read',
            'orders.place',
        ]);

        $grant('sales', [
            'dashboard.view',
            'branches.read',
            'inventory.read',
            'orders.read',
            'orders.create',
            'orders.place',
        ]);


        // Demo bootstrap: ensure a default branch and an admin user exist.
        $now = date('Y-m-d H:i:s');

        $branch = $db->table('branches')->where('code', 'MAIN')->get()->getFirstRow('array');
        if (!$branch) {
            $db->table('branches')->insert([
                'code'       => 'MAIN',
                'name'       => 'Main Branch',
                'manager_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $branchId = (int) $db->insertID();
        } else {
            $branchId = (int) $branch['id'];
        }

        $adminEmail = 'admin@erp.com';
        $admin = $db->table('users')->where('email', $adminEmail)->get()->getFirstRow('array');
        if (!$admin) {
            $db->table('users')->insert([
                'branch_id'     => $branchId,
                'name'          => 'Admin',
                'email'         => $adminEmail,
                'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
                'is_active'     => 1,
                'last_login_at' => null,
                'created_at'    => $now,
                'updated_at'    => $now,
                'deleted_at'    => null,
            ]);
            $adminId = (int) $db->insertID();
        } else {
            $adminId = (int) $admin['id'];
        }

        $superRoleId = $roleId['super_admin'] ?? null;
        if ($superRoleId) {
            $existing = $db->table('user_roles')
                ->where('user_id', $adminId)
                ->where('role_id', $superRoleId)
                ->get()
                ->getFirstRow('array');

            if (!$existing) {
                $db->table('user_roles')->insert([
                    'user_id'    => $adminId,
                    'role_id'    => $superRoleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
