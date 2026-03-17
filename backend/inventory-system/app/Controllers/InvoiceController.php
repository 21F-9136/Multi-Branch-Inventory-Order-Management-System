<?php

namespace App\Controllers;

use App\Libraries\AuthContext;
use App\Services\InvoiceService;
use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

class InvoiceController extends BaseController
{
    public function __construct(protected ?InvoiceService $invoiceService = null)
    {
        $this->invoiceService ??= new InvoiceService();
    }

    public function index(): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

            $role = AuthContext::role();
            $userId = AuthContext::id();
            $branchId = AuthContext::branchId();
            $requestedBranchId = null;

            if ($role === 'admin' || $role === 'super_admin') {
                $requested = $this->request->getGet('branch_id');
                if ($requested !== null && $requested !== '' && is_numeric($requested) && (int) $requested > 0) {
                    $requestedBranchId = (int) $requested;
                }
            }

            $items = $this->invoiceService->listInvoices($role, $userId, $branchId, $requestedBranchId);

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
        }
    }

    public function show(int $orderId): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

            $item = $this->invoiceService->getInvoice(
                $orderId,
                AuthContext::role(),
                AuthContext::id(),
                AuthContext::branchId(),
            );

            return $this->response->setJSON([
                'success' => true,
                'data'    => [
                    'item' => $item,
                ],
            ]);
        } catch (RuntimeException $e) {
            return $this->failInvoiceException($e);
        }
    }

    public function pdf(int $orderId): ResponseInterface
    {
        try {
            $this->assertRoleAllowed(['super_admin', 'admin', 'manager', 'sales']);

            $invoice = $this->invoiceService->getInvoice(
                $orderId,
                AuthContext::role(),
                AuthContext::id(),
                AuthContext::branchId(),
            );

            $options = new Options();
            $options->set('isRemoteEnabled', false);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($this->buildInvoiceHtml($invoice));
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $invoiceId = (string) ($invoice['invoice_id'] ?? ('INV-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT)));
            $fileName = preg_replace('/[^A-Za-z0-9\-_]/', '_', $invoiceId) . '.pdf';

            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
                ->setBody($dompdf->output());
        } catch (RuntimeException $e) {
            return $this->failInvoiceException($e);
        }
    }

    /**
     * @param array<int, string> $roles
     */
    private function assertRoleAllowed(array $roles): void
    {
        $role = AuthContext::role();
        if (!in_array($role, $roles, true)) {
            throw new RuntimeException('Forbidden.');
        }
    }

    private function failInvoiceException(RuntimeException $e): ResponseInterface
    {
        $message = $e->getMessage();
        $status = 400;
        if ($message === 'Forbidden.' || $message === 'User is not assigned to a branch.') {
            $status = 403;
        } elseif ($message === 'Invoice source order not found.') {
            $status = 404;
        }

        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'error'   => $message,
        ]);
    }

    /**
     * @param array<string, mixed> $invoice
     */
    private function buildInvoiceHtml(array $invoice): string
    {
        $invoiceId = (string) ($invoice['invoice_id'] ?? ('INV-' . str_pad((string) ($invoice['id'] ?? ''), 4, '0', STR_PAD_LEFT)));
        $orderId = (int) ($invoice['id'] ?? 0);
        $branch = $this->escape((string) ($invoice['branch_name'] ?? '—'));
        $creator = $this->escape((string) (($invoice['creator_name'] ?? '') !== '' ? $invoice['creator_name'] : ('User #' . ((int) ($invoice['user_id'] ?? 0)))));
        $date = $this->escape((string) ($invoice['created_at'] ?? '—'));
        $status = $this->escape((string) ($invoice['status'] ?? 'draft'));

        $rows = '';
        $items = is_array($invoice['items'] ?? null) ? $invoice['items'] : [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $product = $this->escape((string) ($item['product_name'] ?? $item['sku_name'] ?? '—'));
            $sku = $this->escape((string) ($item['sku_code'] ?? '—'));
            $unitPrice = number_format((float) ($item['unit_price'] ?? 0), 2, '.', '');
            $quantity = number_format((float) ($item['quantity'] ?? 0), 3, '.', '');
            $lineTotal = number_format((float) ($item['line_total'] ?? 0), 2, '.', '');

            $rows .= '<tr>'
                . '<td>' . $product . '</td>'
                . '<td>' . $sku . '</td>'
                . '<td style="text-align:right;">' . $unitPrice . '</td>'
                . '<td style="text-align:right;">' . $quantity . '</td>'
                . '<td style="text-align:right;">' . $lineTotal . '</td>'
                . '</tr>';
        }

        $subtotal = number_format((float) ($invoice['subtotal'] ?? 0), 2, '.', '');
        $taxAmount = number_format((float) ($invoice['tax_amount'] ?? 0), 2, '.', '');
        $grandTotal = number_format((float) ($invoice['grand_total'] ?? 0), 2, '.', '');

        return '<!DOCTYPE html>'
            . '<html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#111;}'
            . '.header{margin-bottom:16px;}'
            . '.title{font-size:20px;font-weight:bold;margin-bottom:8px;}'
            . '.meta{margin:2px 0;}'
            . 'table{width:100%;border-collapse:collapse;margin-top:14px;}'
            . 'th,td{border:1px solid #d1d5db;padding:8px;}'
            . 'th{background:#f3f4f6;text-align:left;}'
            . '.totals{margin-top:16px;width:300px;float:right;}'
            . '.totals td{border:none;padding:4px 0;}'
            . '.totals .grand td{font-weight:bold;border-top:1px solid #d1d5db;padding-top:8px;}'
            . '</style></head><body>'
            . '<div class="header">'
            . '<div class="title">Invoice ' . $this->escape($invoiceId) . '</div>'
            . '<div class="meta"><strong>Order ID:</strong> #' . $orderId . '</div>'
            . '<div class="meta"><strong>Branch:</strong> ' . $branch . '</div>'
            . '<div class="meta"><strong>Created By:</strong> ' . $creator . '</div>'
            . '<div class="meta"><strong>Date:</strong> ' . $date . '</div>'
            . '<div class="meta"><strong>Status:</strong> ' . $status . '</div>'
            . '</div>'
            . '<table><thead><tr>'
            . '<th>Product</th><th>SKU</th><th>Unit Price</th><th>Quantity</th><th>Line Total</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<table class="totals">'
            . '<tr><td>Subtotal</td><td style="text-align:right;">' . $subtotal . '</td></tr>'
            . '<tr><td>Tax Amount</td><td style="text-align:right;">' . $taxAmount . '</td></tr>'
            . '<tr class="grand"><td>Grand Total</td><td style="text-align:right;">' . $grandTotal . '</td></tr>'
            . '</table>'
            . '</body></html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
