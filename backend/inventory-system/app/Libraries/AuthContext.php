<?php

namespace App\Libraries;

final class AuthContext
{
    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    /** @var array<int, string> */
    private static array $permissions = [];

    /**
     * @param array<string, mixed> $user
     * @param array<int, string> $permissions
     */
    public static function set(array $user, array $permissions): void
    {
        self::$user = $user;
        self::$permissions = array_values(array_unique(array_map('strval', $permissions)));
    }

    public static function clear(): void
    {
        self::$user = null;
        self::$permissions = [];
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        $id = self::$user['id'] ?? null;
        return is_numeric($id) ? (int) $id : null;
    }

    public static function branchId(): ?int
    {
        $branchId = self::$user['branch_id'] ?? null;
        if ($branchId === null || $branchId === '') return null;
        return is_numeric($branchId) ? (int) $branchId : null;
    }

    public static function role(): string
    {
        return (string) (self::$user['role'] ?? '');
    }

    /** @return array<int, string> */
    public static function permissions(): array
    {
        return self::$permissions;
    }

    public static function hasPermission(string $code): bool
    {
        $code = strtolower(trim($code));
        if ($code === '') return false;
        return in_array($code, self::$permissions, true);
    }
}
