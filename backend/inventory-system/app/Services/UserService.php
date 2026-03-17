<?php

namespace App\Services;

use App\Models\RoleModel;
use App\Models\UserModel;
use App\Models\UserRoleModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

class UserService
{
    protected BaseConnection $db;

    public function __construct(
        protected UserModel $users = new UserModel(),
        protected UserRoleModel $userRoles = new UserRoleModel(),
        protected RoleModel $roles = new RoleModel(),
    ) {
        $this->db = Database::connect();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUsers(?string $role = null): array
    {
        // Choose one role per user (highest priority) for display.
        $roleExpr = "MAX(CASE r.name "
            . "WHEN 'super_admin' THEN 5 "
            . "WHEN 'admin' THEN 4 "
            . "WHEN 'manager' THEN 3 "
            . "WHEN 'sales' THEN 2 "
            . "ELSE 1 END)";

        $rankFilter = null;
        if ($role !== null) {
            $role = strtolower(trim($role));
            $rankFilter = match ($role) {
                'super_admin' => 5,
                'admin'       => 4,
                'manager'     => 3,
                'sales'       => 2,
                'user'        => 1,
                default       => null,
            };
        }

        $builder = $this->db
            ->table('users u')
            ->select('u.id, u.name, u.email, u.branch_id, b.name as branch_name, u.is_active, u.last_login_at, u.created_at')
            ->select($roleExpr . ' as role_rank', false)
            ->join('branches b', 'b.id = u.branch_id', 'left')
            ->join('user_roles ur', 'ur.user_id = u.id', 'left')
            ->join('roles r', 'r.id = ur.role_id', 'left')
            ->where('u.deleted_at IS NULL')
            ->groupBy('u.id');

        if ($rankFilter !== null) {
            $builder->having($roleExpr . ' = ' . (int) $rankFilter, null, false);
        }

        $rows = $builder
            ->orderBy('u.id', 'DESC')
            ->get()
            ->getResultArray();

        // Convert role_rank into a role name by re-querying roles per user (small datasets),
        // or by a second grouped selection. Keep it simple + deterministic.
        $roleMap = [
            5 => 'super_admin',
            4 => 'admin',
            3 => 'manager',
            2 => 'sales',
            1 => 'user',
            0 => 'user',
            null => 'user',
        ];

        foreach ($rows as &$row) {
            $rank = $row['role_rank'] ?? null;
            $row['role'] = $roleMap[$rank] ?? 'user';
            unset($row['role_rank']);
        }

        return $rows;
    }

    /**
     * Returns active users that have the 'manager' role AND are not already assigned
     * as manager_id on any non-deleted branch.
     *
     * If $includeManagerId is provided, that user will be included even if assigned.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function listAvailableManagers(?int $includeManagerId = null): array
    {
        $builder = $this->db
            ->table('users u')
            ->select('u.id, u.name')
            ->join('user_roles ur', 'ur.user_id = u.id', 'inner')
            ->join('roles r', 'r.id = ur.role_id', 'inner')
            ->where('u.deleted_at IS NULL')
            ->where('u.is_active', 1)
            ->where('r.name', 'manager')
            ->groupBy('u.id')
            ->orderBy('u.name', 'ASC');

        $includeManagerId = $includeManagerId !== null ? (int) $includeManagerId : null;

        // Enforce: a manager can only manage ONE branch.
        if ($includeManagerId !== null && $includeManagerId > 0) {
            $builder->where(
                'u.id NOT IN (SELECT b.manager_id FROM branches b WHERE b.deleted_at IS NULL AND b.manager_id IS NOT NULL AND b.manager_id <> ' . $includeManagerId . ')',
                null,
                false
            );
        } else {
            $builder->where(
                'u.id NOT IN (SELECT b.manager_id FROM branches b WHERE b.deleted_at IS NULL AND b.manager_id IS NOT NULL)',
                null,
                false
            );
        }

        $rows = $builder->get()->getResultArray();
        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name']], $rows);
    }

    /**
     * Returns active users that have Branch Manager role through user_roles.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function listBranchManagers(?int $includeManagerId = null): array
    {
        $builder = $this->db
            ->table('users u')
            ->select('u.id, u.name')
            ->join('user_roles ur', 'ur.user_id = u.id', 'inner')
            ->join('roles r', 'r.id = ur.role_id', 'inner')
            ->where('u.deleted_at IS NULL')
            ->where('u.is_active', 1)
            ->groupStart()
            ->where('r.name', 'Branch Manager')
            ->orWhere('r.name', 'branch_manager')
            ->orWhere('r.name', 'manager')
            ->groupEnd()
            ->groupBy('u.id')
            ->orderBy('u.name', 'ASC');

        $includeManagerId = $includeManagerId !== null ? (int) $includeManagerId : null;
        if ($includeManagerId !== null && $includeManagerId > 0) {
            $builder->groupStart()
                ->where('u.id', $includeManagerId)
                ->orGroupStart()
                ->where(
                    'u.id NOT IN (SELECT b.manager_id FROM branches b WHERE b.deleted_at IS NULL AND b.manager_id IS NOT NULL)',
                    null,
                    false
                )
                ->groupEnd()
                ->groupEnd();
        } else {
            $builder->where(
                'u.id NOT IN (SELECT b.manager_id FROM branches b WHERE b.deleted_at IS NULL AND b.manager_id IS NOT NULL)',
                null,
                false
            );
        }

        $rows = $builder->get()->getResultArray();
        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name']], $rows);
    }

    /**
     * @param array{name?:string|null,email?:string|null,branch_id?:int|string|null,is_active?:int|null,role?:string|null} $data
     */
    public function updateUser(int $userId, array $data): void
    {
        $user = $this->users->find($userId);
        if ($user === null || !empty($user['deleted_at'])) {
            throw new RuntimeException('User not found.');
        }

        $update = [];
        if (isset($data['name']) && $data['name'] !== null) {
            $update['name'] = $data['name'];
        }
        if (isset($data['email']) && $data['email'] !== null) {
            $update['email'] = $data['email'];
        }
        if (array_key_exists('branch_id', $data) && $data['branch_id'] !== null) {
            $update['branch_id'] = $data['branch_id'] === '' ? null : (int) $data['branch_id'];
        }
        if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
            $update['is_active'] = (int) $data['is_active'];
        }

        $this->db->transException(true)->transStart();

        if ($update !== []) {
            $this->users->update($userId, $update);
        }

        if (!empty($data['role'])) {
            $this->setSingleRoleByName($userId, $data['role']);
        }

        $this->db->transComplete();
    }

    public function deleteUser(int $userId): void
    {
        $user = $this->users->find($userId);
        if ($user === null || !empty($user['deleted_at'])) {
            throw new RuntimeException('User not found.');
        }

        $this->users->delete($userId);
    }

    public function getPrimaryRoleNameForUser(int $userId): string
    {
        $roles = $this->db
            ->table('user_roles ur')
            ->select('r.name')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->get()
            ->getResultArray();

        if (empty($roles)) return 'user';

        $names = array_map(static fn ($r) => (string) ($r['name'] ?? ''), $roles);
        return $this->pickPrimaryRole($names);
    }

    private function setSingleRoleByName(int $userId, string $roleName): void
    {
        $role = $this->roles->where('name', $roleName)->first();
        if ($role === null) {
            throw new RuntimeException('Role not found.');
        }

        // Remove existing roles and set one.
        $this->userRoles->where('user_id', $userId)->delete();
        $this->userRoles->insert([
            'user_id' => $userId,
            'role_id' => (int) $role['id'],
        ]);
    }

    /**
     * @param array<int, string> $roleNames
     */
    private function pickPrimaryRole(array $roleNames): string
    {
        $priority = [
            'super_admin' => 50,
            'admin'       => 40,
            'manager'     => 30,
            'sales'       => 20,
            'user'        => 10,
        ];

        $best = 'user';
        $bestScore = -1;
        foreach ($roleNames as $name) {
            $name = strtolower(trim($name));
            $score = $priority[$name] ?? 0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $name;
            }
        }

        return $best;
    }
}
