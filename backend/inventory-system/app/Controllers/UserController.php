<?php

namespace App\Controllers;

use App\Services\UserService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class UserController extends BaseController
{
    public function __construct(protected ?UserService $userService = null)
    {
        $this->userService ??= new UserService();
    }

    public function index(): ResponseInterface
    {
        try {
            $role = $this->request->getGet('role');
            $role = is_string($role) && trim($role) !== '' ? trim($role) : null;

            if ($role === 'manager') {
                $include = $this->request->getGet('include_manager_id');
                $includeId = is_string($include) && trim($include) !== '' ? (int) $include : null;
                $items = $this->userService->listAvailableManagers($includeId);
            } else {
                $items = $this->userService->listUsers($role);
            }

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'items' => $items,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function managers(): ResponseInterface
    {
        try {
            $include = $this->request->getGet('include_manager_id');
            $includeId = is_string($include) && trim($include) !== '' ? (int) $include : null;

            $items = $this->userService->listBranchManagers($includeId);

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'items' => $items,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->getJsonBody();

        $rules = [
            'name'      => 'permit_empty|min_length[2]',
            'email'     => 'permit_empty|valid_email',
            'branch_id' => 'permit_empty|is_natural_no_zero',
            'is_active' => 'permit_empty|in_list[0,1]',
            'role'      => 'permit_empty|max_length[100]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $this->userService->updateUser($id, [
                'name'      => $data['name'] ?? null,
                'email'     => $data['email'] ?? null,
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : null,
                'is_active' => array_key_exists('is_active', $data) ? (int) $data['is_active'] : null,
                'role'      => $data['role'] ?? null,
            ]);

            return $this->response->setJSON(['success' => true]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $this->userService->deleteUser($id);
            return $this->response->setJSON(['success' => true]);
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
}
