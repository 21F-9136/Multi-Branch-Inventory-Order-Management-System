<?php

namespace App\Services;

use App\Libraries\Jwt;
use App\Models\UserModel;
use Config\Database;
use RuntimeException;

class AuthService
{
    private $db;

    public function __construct(
        protected UserModel $users = new UserModel(),
        protected UserService $userService = new UserService(),
    ) {
        $this->db = Database::connect();
    }

    /**
     * @return array The authenticated user row.
     */
    public function authenticate(string $email, string $plainPassword): array
    {
        $user = $this->users->where('email', $email)->first();
        if ($user === null) {
            throw new RuntimeException('Invalid credentials.');
        }

        if (!empty($user['deleted_at'])) {
            throw new RuntimeException('User is deleted.');
        }

        if (isset($user['is_active']) && (int) $user['is_active'] !== 1) {
            throw new RuntimeException('User is inactive.');
        }

        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || !password_verify($plainPassword, $hash)) {
            throw new RuntimeException('Invalid credentials.');
        }

        $this->users->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        $user['role'] = $this->userService->getPrimaryRoleNameForUser((int) $user['id']);

        return $user;
    }

    /**
     * @param array{name:string,email:string,password:string,branch_id?:int|null,is_active?:int,role?:string|null} $data
     * @return int|string Insert ID.
     */
    public function register(array $data)
    {
        $payload = [
            'branch_id'      => $data['branch_id'] ?? null,
            'name'           => $data['name'],
            'email'          => $data['email'],
            'password_hash'  => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_active'      => $data['is_active'] ?? 1,
            'last_login_at'  => null,
        ];

        $this->db->transException(true)->transStart();

        $this->users->insert($payload);
        $userId = (int) $this->users->getInsertID();

        if (!empty($data['role'])) {
            $this->userService->updateUser($userId, ['role' => $data['role']]);
        }

        $this->db->transComplete();

        return $userId;
    }

    /**
     * @param array<string, mixed> $extraClaims
     */
    public function issueToken(int $userId, array $extraClaims = []): string
    {
        $secret = (string) (getenv('JWT_SECRET') ?: '');
        if ($secret === '') {
            throw new RuntimeException('JWT secret not configured.');
        }

        $ttl = (int) (getenv('JWT_TTL_SECONDS') ?: 8 * 60 * 60);
        if ($ttl <= 0) {
            $ttl = 8 * 60 * 60;
        }

        $now = time();

        $payload = array_merge([
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $ttl,
        ], $extraClaims);

        return Jwt::encode($payload, $secret);
    }
}
