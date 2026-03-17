<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RepriceZeroOrderLinesAndTotals extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('order_lines') || !$this->db->tableExists('skus') || !$this->db->tableExists('orders')) {
            return;
        }

        // Reprice only lines that are currently zero and can be inferred from SKU pricing fields.
        $this->db->query(
            'UPDATE `order_lines` `ol` '
            . 'JOIN `skus` `s` ON `s`.`id` = `ol`.`sku_id` '
            . 'SET `ol`.`unit_price` = COALESCE(NULLIF(`s`.`sale_price`, 0), NULLIF(`s`.`unit_price`, 0), NULLIF(`s`.`cost_price`, 0), 0), '
            . '`ol`.`line_total` = (COALESCE(NULLIF(`s`.`sale_price`, 0), NULLIF(`s`.`unit_price`, 0), NULLIF(`s`.`cost_price`, 0), 0) * `ol`.`quantity`) '
            . 'WHERE `ol`.`unit_price` = 0 AND `ol`.`line_total` = 0 AND `s`.`deleted_at` IS NULL'
        );

        // Recompute order totals from order_lines and 10% tax.
        $this->db->query(
            'UPDATE `orders` `o` '
            . 'LEFT JOIN ('
            . '  SELECT `order_id`, ROUND(SUM(`line_total`), 2) AS `subtotal` '
            . '  FROM `order_lines` '
            . '  GROUP BY `order_id`'
            . ') `x` ON `x`.`order_id` = `o`.`id` '
            . 'SET '
            . '  `o`.`subtotal` = COALESCE(`x`.`subtotal`, 0), '
            . '  `o`.`tax_amount` = ROUND(COALESCE(`x`.`subtotal`, 0) * 0.10, 2), '
            . '  `o`.`grand_total` = ROUND(COALESCE(`x`.`subtotal`, 0) * 1.10, 2), '
            . '  `o`.`total_amount` = ROUND(COALESCE(`x`.`subtotal`, 0) * 1.10, 2) '
            . 'WHERE `o`.`deleted_at` IS NULL'
        );
    }

    public function down()
    {
        // No safe rollback: this migration performs data corrections.
    }
}
