<?php

namespace App\Commands;

use App\Models\ReservationModel;
use App\Models\StockMovementModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;
use Throwable;

class ReleaseExpiredReservations extends BaseCommand
{
    protected $group       = 'Inventory';
    protected $name        = 'inventory:release-expired-reservations';
    protected $description = 'Release expired active inventory reservations and unlock reserved stock.';

    protected $usage = 'inventory:release-expired-reservations';

    public function run(array $params)
    {
        $db = Database::connect();
        $reservations = new ReservationModel();
        $movements = new StockMovementModel();

        $now = date('Y-m-d H:i:s');

        $candidates = $reservations
            ->select('id, order_id, branch_id, sku_id, quantity, expires_at')
            ->where('status', 'active')
            ->where('deleted_at', null)
            ->where('expires_at IS NOT NULL', null, false)
            ->where('expires_at <', $now)
            ->orderBy('id', 'ASC')
            ->findAll();

        if (empty($candidates)) {
            CLI::write('No expired active reservations found.', 'yellow');
            log_message('info', 'Expired reservations released: 0');
            return;
        }

        $releasedCount = 0;

        foreach ($candidates as $candidate) {
            $reservationId = (int) ($candidate['id'] ?? 0);
            if ($reservationId <= 0) {
                continue;
            }

            $db->transBegin();

            try {
                $locked = $db
                    ->query(
                        'SELECT `id`, `order_id`, `branch_id`, `sku_id`, `quantity` '
                        . 'FROM `reservations` '
                        . 'WHERE `id` = ? AND `status` = ? AND `deleted_at` IS NULL '
                        . 'AND `expires_at` IS NOT NULL AND `expires_at` < ? '
                        . 'FOR UPDATE',
                        [$reservationId, 'active', $now]
                    )
                    ->getFirstRow('array');

                // Already processed by another run/process; skip safely.
                if ($locked === null) {
                    $db->transCommit();
                    continue;
                }

                $branchId = (int) ($locked['branch_id'] ?? 0);
                $skuId = (int) ($locked['sku_id'] ?? 0);
                $qty = (float) ($locked['quantity'] ?? 0);

                if ($branchId <= 0 || $skuId <= 0 || $qty <= 0) {
                    throw new RuntimeException('Invalid reservation payload for expiry release.');
                }

                $db->query(
                    'UPDATE `inventory_balances` '
                    . 'SET `qty_reserved` = `qty_reserved` - ? '
                    . 'WHERE `branch_id` = ? AND `sku_id` = ? '
                    . 'AND `deleted_at` IS NULL AND `qty_reserved` >= ?',
                    [$qty, $branchId, $skuId, $qty]
                );

                if ($db->affectedRows() !== 1) {
                    throw new RuntimeException('Unable to decrement reserved quantity for expired reservation #' . $reservationId);
                }

                $reservations->update($reservationId, [
                    'status' => 'expired',
                ]);

                $movements->insert([
                    'branch_id'      => $branchId,
                    'sku_id'         => $skuId,
                    'movement_type'  => 'reservation_expired',
                    'quantity'       => $qty,
                    'reference_type' => 'reservation',
                    'reference_id'   => $reservationId,
                    'notes'          => 'Reservation expired and reserved stock released',
                    'created_by'     => null,
                ]);

                $db->transCommit();
                $releasedCount++;
            } catch (Throwable $e) {
                $db->transRollback();
                log_message('error', 'Failed to release expired reservation #' . $reservationId . ': ' . $e->getMessage());
            }
        }

        CLI::write('Expired reservations released: ' . $releasedCount, 'green');
        log_message('info', 'Expired reservations released: ' . $releasedCount);
    }
}
