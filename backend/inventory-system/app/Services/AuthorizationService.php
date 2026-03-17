<?php

namespace App\Services;

use App\Models\PermissionModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

class AuthorizationService
{
    protected BaseConnection $db;

    public function __construct(
        protected UserModel $users = new UserModel(),
        protected PermissionModel $permissions = new PermissionModel(),
        protected UserService $userService = new UserService(),
    ) {
        $this->db = Database::connect();
    }

    /**
     * @return array<string, mixed>
     */
    public function getActiveUserOrFail(int $userId): array
    {
        $user = $this->users->find($userId);
        if ($user === null || !empty($user['deleted_at'])) {
            throw new RuntimeException('User not found.');
        }

        if (isset($user['is_active']) && (int) $user['is_active'] !== 1) {
            throw new RuntimeException('User is inactive.');
        }

        $user['role'] = $this->userService->getPrimaryRoleNameForUser($userId);

        return $user;
    }

    /**
     * @return array<int, string>
     */
    public function listPermissionCodesForUser(int $userId): array
    {
        $rows = $this->db
            ->table('user_roles ur')
            ->select('p.code')
            ->join('role_permissions rp', 'rp.role_id = ur.role_id')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('ur.user_id', $userId)
            ->get()
            ->getResultArray();

        $codes = [];
        foreach ($rows as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            if ($code !== '') $codes[] = $code;
        }

        return array_values(array_unique($codes));
    }

    public function hasPermission(int $userId, string $permissionCode): bool
    {
        $permissionCode = strtolower(trim($permissionCode));
        if ($permissionCode === '') return false;

        $row = $this->db
            ->table('user_roles ur')
            ->select('p.id')
            ->join('role_permissions rp', 'rp.role_id = ur.role_id')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('ur.user_id', $userId)
            ->where('p.code', $permissionCode)
            ->get()
            ->getFirstRow('array');

        return $row !== null;
    }
}
