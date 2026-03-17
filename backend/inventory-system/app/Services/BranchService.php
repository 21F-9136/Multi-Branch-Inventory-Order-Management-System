<?php

namespace App\Services;

use App\Models\BranchModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

class BranchService
{
    protected BaseConnection $db;

    public function __construct(
        protected BranchModel $branches = new BranchModel(),
        protected UserModel $users = new UserModel(),
    ) {
        $this->db = Database::connect();
    }

    /**
     * @param array{name:string,address:string,manager_id?:int|null,status:string} $data
     * @return int|string
     */
    public function createBranch(array $data)
    {
        $this->branches->insert([
            'name'       => $data['name'],
            'address'    => $data['address'],
            'manager_id' => $data['manager_id'] ?? null,
            'status'     => $data['status'],
        ]);

        return $this->branches->getInsertID();
    }

    /**
     * @param array{name?:string|null,address?:string|null,manager_id?:int|null,status?:string|null} $data
     */
    public function updateBranch(int $branchId, array $data): void
    {
        $branch = $this->branches->find($branchId);
        if ($branch === null || !empty($branch['deleted_at'])) {
            throw new RuntimeException('Branch not found.');
        }

        $update = [];
        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $update['name'] = $data['name'];
        }
        if (array_key_exists('address', $data) && $data['address'] !== null) {
            $update['address'] = $data['address'];
        }
        if (array_key_exists('manager_id', $data)) {
            $update['manager_id'] = $data['manager_id'];
        }
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $update['status'] = $data['status'];
        }

        if ($update === []) {
            return;
        }

        $this->branches->update($branchId, $update);
    }

    public function deleteBranch(int $branchId): void
    {
        $branch = $this->branches->find($branchId);
        if ($branch === null || !empty($branch['deleted_at'])) {
            throw new RuntimeException('Branch not found.');
        }

        $this->branches->delete($branchId);
    }

    public function assignManager(int $branchId, ?int $managerUserId): void
    {
        if ($managerUserId !== null) {
            $manager = $this->users->find($managerUserId);
            if ($manager === null) {
                throw new RuntimeException('Manager user not found.');
            }
        }

        $this->branches->update($branchId, [
            'manager_id' => $managerUserId,
        ]);
    }

    public function moveUserToBranch(int $userId, ?int $branchId): void
    {
        if ($branchId !== null) {
            $branch = $this->branches->find($branchId);
            if ($branch === null) {
                throw new RuntimeException('Branch not found.');
            }
        }

        $this->users->update($userId, [
            'branch_id' => $branchId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBranches(?int $branchId = null): array
    {
        $q = $this->db
            ->table('branches b')
            ->select('b.id, b.name, b.address, b.manager_id, b.status, u.name as manager_name, b.created_at, b.updated_at')
            ->join('users u', 'u.id = b.manager_id', 'left')
            ->where('b.deleted_at IS NULL');

        if ($branchId !== null) {
            $q->where('b.id', $branchId);
        }

        return $q->orderBy('b.name', 'ASC')->get()->getResultArray();
    }
}
