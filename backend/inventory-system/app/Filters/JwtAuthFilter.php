<?php

namespace App\Filters;

use App\Libraries\AuthContext;
use App\Libraries\Jwt;
use App\Services\AuthorizationService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use RuntimeException;

class JwtAuthFilter implements FilterInterface
{
    public function __construct(protected ?AuthorizationService $authz = null)
    {
        $this->authz ??= new AuthorizationService();
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        // Allow CORS preflight.
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return null;
        }

        AuthContext::clear();

        $header = $request->getHeaderLine('Authorization');
        if ($header === '') {
            return $this->unauthorized('Missing Authorization header.');
        }

        if (!preg_match('/^Bearer\s+(?<token>.+)$/i', trim($header), $matches)) {
            return $this->unauthorized('Invalid Authorization header.');
        }

        $token = trim((string) ($matches['token'] ?? ''));
        if ($token === '') {
            return $this->unauthorized('Missing bearer token.');
        }

        $secret = (string) (getenv('JWT_SECRET') ?: '');
        if ($secret === '') {
            return $this->unauthorized('Server JWT secret not configured.');
        }

        try {
            $payload = Jwt::decode($token, $secret);

            $sub = $payload['sub'] ?? null;
            if (!is_numeric($sub)) {
                return $this->unauthorized('Invalid token subject.');
            }

            $userId = (int) $sub;
            $user = $this->authz->getActiveUserOrFail($userId);
            $perms = $this->authz->listPermissionCodesForUser($userId);

            AuthContext::set($user, $perms);

            return null;
        } catch (RuntimeException $e) {
            return $this->unauthorized($e->getMessage());
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function unauthorized(string $message)
    {
        $response = Services::response();
        $response->setStatusCode(401);
        $response->setJSON([
            'success' => false,
            'error' => $message,
        ]);

        return $response;
    }
}
