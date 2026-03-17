<?php

namespace App\Controllers;

use App\Libraries\AuthContext;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class AuthController extends BaseController
{
    public function __construct(
        protected ?AuthService $authService = null,
        protected ?AuthorizationService $authorizationService = null,
    )
    {
        $this->authService ??= new AuthService();
        $this->authorizationService ??= new AuthorizationService();
    }

    public function login(): ResponseInterface
    {
        $data = $this->getJsonBody();

        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $user = $this->authService->authenticate($data['email'], $data['password']);
            $token = $this->authService->issueToken((int) $user['id'], [
                'branch_id' => $user['branch_id'] ?? null,
                'role'      => $user['role'] ?? null,
            ]);

            $permissions = $this->authorizationService->listPermissionCodesForUser((int) $user['id']);

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'user' => $this->sanitizeUser($user),
                    'token' => $token,
                    'permissions' => $permissions,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function me(): ResponseInterface
    {
        $user = AuthContext::user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'error'   => 'Unauthenticated.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data'    => [
                'user'        => $this->sanitizeUser($user),
                'permissions' => AuthContext::permissions(),
            ],
        ]);
    }

    public function register(): ResponseInterface
    {
        $data = $this->getJsonBody();

        $rules = [
            'name'      => 'required|min_length[2]',
            'email'     => 'required|valid_email',
            'password'  => 'required|min_length[6]',
            'branch_id' => 'permit_empty|is_natural_no_zero',
            'is_active' => 'permit_empty|in_list[0,1]',
            'role'      => 'permit_empty|max_length[100]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $userId = $this->authService->register([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => $data['password'],
                'branch_id' => $data['branch_id'] ?? null,
                'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
                'role'      => $data['role'] ?? null,
            ]);

            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'data'    => [
                    'user_id' => $userId,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function getJsonBody(): array
    {
        $body = $this->request->getJSON(true);
        if (is_array($body)) {
            return $body;
        }

        return (array) $this->request->getPost();
    }

    private function failValidation(): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON([
            'success' => false,
            'error'   => 'Validation failed',
            'details' => $this->validator?->getErrors() ?? [],
        ]);
    }

    private function sanitizeUser(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }
}
