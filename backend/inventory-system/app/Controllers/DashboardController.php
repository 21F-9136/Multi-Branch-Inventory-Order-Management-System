<?php

namespace App\Controllers;

use App\Libraries\AuthContext;
use App\Services\DashboardService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

class DashboardController extends BaseController
{
    public function __construct(protected ?DashboardService $dashboard = null)
    {
        $this->dashboard ??= new DashboardService();
    }

    public function summary(): ResponseInterface
    {
        try {
            $role = AuthContext::role();
            $branchId = null;

            if ($role === 'manager' || $role === 'sales') {
                $branchId = AuthContext::branchId();
                if ($branchId === null) {
                    throw new RuntimeException('User is not assigned to a branch.');
                }
            } else {
                $requested = $this->request->getGet('branch_id');
                if ($requested !== null && $requested !== '') {
                    $branchId = (int) $requested;
                }
            }

            $threshold = (int) ($this->request->getGet('low_stock_threshold') ?? 10);
            if ($threshold < 0) $threshold = 0;

            $trendDays = (int) ($this->request->getGet('trend_days') ?? 30);
            if ($trendDays < 7) $trendDays = 7;
            if ($trendDays > 30) $trendDays = 30;

            $summary = $this->dashboard->getSummary($branchId, $threshold, $trendDays);

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
