<?php

namespace App\Filters;

use App\Libraries\AuthContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Allow CORS preflight.
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return null;
        }

        $userId = AuthContext::id();
        if ($userId === null) {
            return $this->unauthorized('Unauthenticated.');
        }

        $required = is_array($arguments) ? $arguments : [];
        $required = array_values(array_filter(array_map('strval', $required), static fn ($v) => trim($v) !== ''));

        if ($required === []) {
            return null;
        }

        foreach ($required as $code) {
            if (AuthContext::hasPermission($code)) {
                return null;
            }
        }

        return $this->forbidden('Forbidden.');
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

    private function forbidden(string $message)
    {
        $response = Services::response();
        $response->setStatusCode(403);
        $response->setJSON([
            'success' => false,
            'error' => $message,
        ]);

        return $response;
    }
}
