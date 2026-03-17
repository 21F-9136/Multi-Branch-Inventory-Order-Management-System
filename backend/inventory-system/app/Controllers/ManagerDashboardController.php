<?php

namespace App\Controllers;

use App\Libraries\AuthContext;
use App\Services\ManagerDashboardService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class ManagerDashboardController extends BaseController
{
    public function __construct(protected ?ManagerDashboardService $dashboard = null)
    {
        $this->dashboard ??= new ManagerDashboardService();
    }

    public function summary(): ResponseInterface
    {
        try {
            if (AuthContext::role() !== 'manager') {
                throw new RuntimeException('Forbidden.');
            }

            $branchId = AuthContext::branchId();
            if ($branchId === null) {
                throw new RuntimeException('User is not assigned to a branch.');
            }

            $trendDays = (int) ($this->request->getGet('trend_days') ?? 30);
            if ($trendDays < 7) $trendDays = 7;
            if ($trendDays > 30) $trendDays = 30;

            $summary = $this->dashboard->getSummary($branchId, $trendDays);

            return $this->response->setJSON([
                'success' => true,
                'data'    => $summary,
            ]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Forbidden.', 'User is not assigned to a branch.'], true) ? 403 : 400;
            return $this->response->setStatusCode($status)->setJSON([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
