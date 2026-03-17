<?php

namespace App\Controllers;

use App\Libraries\AuthContext;
use App\Services\BranchService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class BranchController extends BaseController
{
    public function __construct(protected ?BranchService $branchService = null)
    {
        $this->branchService ??= new BranchService();
    }

    public function create(): ResponseInterface
    {
        $data = $this->getJsonBody();

        $rules = [
            'name'       => 'required|min_length[2]',
            'address'    => 'required|max_length[255]',
            'manager_id' => 'permit_empty|is_natural_no_zero',
            'status'     => 'required|in_list[active,inactive]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $branchId = $this->branchService->createBranch([
                'name'       => $data['name'],
                'address'    => $data['address'],
                'manager_id' => isset($data['manager_id']) && $data['manager_id'] !== '' ? (int) $data['manager_id'] : null,
                'status'     => $data['status'],
            ]);

            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'data'    => [
                    'branch_id' => $branchId,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => 'Server error',
            ]);
        }
    }

    public function index(): ResponseInterface
    {
        try {
            $role = AuthContext::role();
            $branchId = null;
            if ($role === 'manager' || $role === 'sales') {
                $branchId = AuthContext::branchId();
                if ($branchId === null) {
                    throw new RuntimeException('User is not assigned to a branch.');
                }
            }

            $items = $this->branchService->listBranches($branchId);

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'items' => $items,
                ],
            ]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true) ? 403 : 400;
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => 'Server error',
            ]);
        }
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->getJsonBody();

        $rules = [
            'name'       => 'required|min_length[2]',
            'address'    => 'required|max_length[255]',
            'manager_id' => 'permit_empty|is_natural_no_zero',
            'status'     => 'required|in_list[active,inactive]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $this->branchService->updateBranch($id, [
                'name'       => $data['name'],
                'address'    => $data['address'],
                'manager_id' => array_key_exists('manager_id', $data)
                    ? (isset($data['manager_id']) && $data['manager_id'] !== '' ? (int) $data['manager_id'] : null)
                    : null,
                'status'     => $data['status'],
            ]);

            return $this->response->setJSON(['success' => true]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => 'Server error',
            ]);
        }
    }

    public function delete(int $id): ResponseInterface
    {
        try {
            $this->branchService->deleteBranch($id);
            return $this->response->setJSON(['success' => true]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => 'Server error',
            ]);
        }
    }

    public function assignManager(): ResponseInterface
    {
        $data = $this->getJsonBody();

        $rules = [
            'branch_id'  => 'required|is_natural_no_zero',
            'manager_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $this->branchService->assignManager((int) $data['branch_id'], isset($data['manager_id']) ? (int) $data['manager_id'] : null);

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'branch_id'  => (int) $data['branch_id'],
                    'manager_id' => $data['manager_id'] ?? null,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function moveUser(): ResponseInterface
    {
        $data = $this->getJsonBody();

        $rules = [
            'user_id'   => 'required|is_natural_no_zero',
            'branch_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->failValidation();
        }

        try {
            $this->branchService->moveUserToBranch((int) $data['user_id'], isset($data['branch_id']) ? (int) $data['branch_id'] : null);

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'user_id'   => (int) $data['user_id'],
                    'branch_id' => $data['branch_id'] ?? null,
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
}
