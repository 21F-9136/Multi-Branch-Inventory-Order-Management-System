<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $brandNames = [
            'Dell', 'HP', 'Lenovo', 'Apple', 'ASUS', 'Acer', 'MSI', 'Samsung', 'LG', 'Logitech',
            'Razer', 'TP-Link', 'Cisco', 'Ubiquiti', 'Kingston', 'Seagate', 'Western Digital', 'Anker',
            'Canon', 'Epson', 'Sony', 'JBL'
        ];

        $categoryNames = [
            'Laptops', 'Monitors', 'Accessories', 'Networking', 'Storage', 'Printers', 'Audio'
        ];

        $brandIds = $this->upsertLookups('brands', $brandNames);
        $categoryIds = $this->upsertLookups('categories', $categoryNames);

        $products = $this->getCatalog();

        $productRows = [];
        $skuRows = [];

        foreach ($products as $item) {
            $existingSku = $this->db->table('skus')
                ->select('id, product_id')
                ->where('sku_code', $item['sku'])
                ->where('deleted_at', null)
                ->get()
                ->getFirstRow('array');

            $brandId = $brandIds[$item['brand']] ?? null;
            $categoryId = $categoryIds[$item['category']] ?? null;

            if ($existingSku) {
                $this->db->table('products')
                    ->where('id', (int) $existingSku['product_id'])
                    ->update([
                        'name' => $item['name'],
                        'brand_id' => $brandId,
                        'category_id' => $categoryId,
                        'description' => $item['description'],
                        'status' => 'active',
                        'tax_percent' => 17.00,
                        'is_active' => 1,
                        'updated_at' => $now,
                    ]);

                $this->db->table('skus')
                    ->where('id', (int) $existingSku['id'])
                    ->update([
                        'name' => $item['name'],
                        'cost_price' => $item['cost_price'],
                        'sale_price' => $item['sale_price'],
                        'unit_price' => $item['sale_price'],
                        'updated_at' => $now,
                    ]);

                continue;
            }

            $existingProduct = $this->db->table('products')
                ->select('id')
                ->where('name', $item['name'])
                ->where('deleted_at', null)
                ->get()
                ->getFirstRow('array');

            if ($existingProduct) {
                $productId = (int) $existingProduct['id'];
                $this->db->table('products')
                    ->where('id', $productId)
                    ->update([
                        'brand_id' => $brandId,
                        'category_id' => $categoryId,
                        'description' => $item['description'],
                        'status' => 'active',
                        'tax_percent' => 17.00,
                        'is_active' => 1,
                        'updated_at' => $now,
                    ]);
            } else {
                $productRows[] = [
                    'name' => $item['name'],
                    'brand_id' => $brandId,
                    'category_id' => $categoryId,
                    'description' => $item['description'],
                    'status' => 'active',
                    'tax_percent' => 17.00,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($productRows !== []) {
            foreach (array_chunk($productRows, 200) as $chunk) {
                $this->db->table('products')->insertBatch($chunk);
            }
        }

        foreach ($products as $item) {
            $product = $this->db->table('products')
                ->select('id')
                ->where('name', $item['name'])
                ->where('deleted_at', null)
                ->get()
                ->getFirstRow('array');
            if (!$product) {
                continue;
            }

            $skuExists = $this->db->table('skus')
                ->select('id')
                ->where('sku_code', $item['sku'])
                ->where('deleted_at', null)
                ->get()
                ->getFirstRow('array');

            if ($skuExists) {
                continue;
            }

            $skuRows[] = [
                'product_id' => (int) $product['id'],
                'sku_code' => $item['sku'],
                'barcode' => $item['barcode'],
                'name' => $item['name'],
                'cost_price' => $item['cost_price'],
                'sale_price' => $item['sale_price'],
                'unit_price' => $item['sale_price'],
                'reorder_level' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($skuRows !== []) {
            foreach (array_chunk($skuRows, 200) as $chunk) {
                $this->db->table('skus')->insertBatch($chunk);
            }
        }
    }

    /**
     * @param array<int, string> $names
     * @return array<string, int>
     */
    private function upsertLookups(string $table, array $names): array
    {
        foreach ($names as $name) {
            $exists = $this->db->table($table)->where('name', $name)->get()->getFirstRow('array');
            if (!$exists) {
                $this->db->table($table)->insert(['name' => $name]);
            }
        }

        $rows = $this->db->table($table)->select('id, name')->get()->getResultArray();
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['name']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @return array<int, array{name:string,brand:string,category:string,sku:string,barcode:string,cost_price:float,sale_price:float,description:string}>
     */
    private function getCatalog(): array
    {
        return [
            ['name'=>'Dell XPS 13 Laptop','brand'=>'Dell','category'=>'Laptops','sku'=>'DL-XPS13-001','barcode'=>'8901000000011','cost_price'=>295000,'sale_price'=>339000,'description'=>'13-inch premium ultrabook for business users.'],
            ['name'=>'HP EliteBook 840 G9','brand'=>'HP','category'=>'Laptops','sku'=>'HP-ELT840-001','barcode'=>'8901000000012','cost_price'=>258000,'sale_price'=>298000,'description'=>'Corporate laptop with enterprise-grade security.'],
            ['name'=>'Lenovo ThinkPad X1 Carbon','brand'=>'Lenovo','category'=>'Laptops','sku'=>'LN-X1C-001','barcode'=>'8901000000013','cost_price'=>305000,'sale_price'=>349000,'description'=>'Lightweight durable laptop for executives.'],
            ['name'=>'MacBook Air M2 13-inch','brand'=>'Apple','category'=>'Laptops','sku'=>'AP-MBA-M2-001','barcode'=>'8901000000014','cost_price'=>315000,'sale_price'=>369000,'description'=>'Apple silicon productivity laptop.'],
            ['name'=>'ASUS ROG Strix G16','brand'=>'ASUS','category'=>'Laptops','sku'=>'AS-ROG-G16-001','barcode'=>'8901000000015','cost_price'=>365000,'sale_price'=>429000,'description'=>'Gaming laptop with high refresh display.'],
            ['name'=>'Acer Aspire 7','brand'=>'Acer','category'=>'Laptops','sku'=>'AC-ASP7-001','barcode'=>'8901000000016','cost_price'=>178000,'sale_price'=>214000,'description'=>'Mid-range laptop for office and home use.'],
            ['name'=>'MSI Modern 14','brand'=>'MSI','category'=>'Laptops','sku'=>'MS-MOD14-001','barcode'=>'8901000000017','cost_price'=>168000,'sale_price'=>199000,'description'=>'Portable laptop for students and professionals.'],
            ['name'=>'Dell Latitude 5440','brand'=>'Dell','category'=>'Laptops','sku'=>'DL-LAT5440-001','barcode'=>'8901000000018','cost_price'=>212000,'sale_price'=>249000,'description'=>'Reliable business laptop with long battery life.'],

            ['name'=>'Samsung 27-inch Curved Monitor','brand'=>'Samsung','category'=>'Monitors','sku'=>'SM-CV27-001','barcode'=>'8901000000021','cost_price'=>48500,'sale_price'=>59900,'description'=>'Curved monitor for immersive viewing.'],
            ['name'=>'Dell 24-inch IPS Monitor','brand'=>'Dell','category'=>'Monitors','sku'=>'DL-IPS24-001','barcode'=>'8901000000022','cost_price'=>36500,'sale_price'=>44900,'description'=>'24-inch IPS business monitor.'],
            ['name'=>'LG UltraWide 29-inch Monitor','brand'=>'LG','category'=>'Monitors','sku'=>'LG-UW29-001','barcode'=>'8901000000023','cost_price'=>64500,'sale_price'=>78900,'description'=>'Ultrawide monitor for multitasking workflows.'],
            ['name'=>'ASUS ProArt 27 Monitor','brand'=>'ASUS','category'=>'Monitors','sku'=>'AS-PA27-001','barcode'=>'8901000000024','cost_price'=>91500,'sale_price'=>109900,'description'=>'Color-accurate monitor for creators.'],
            ['name'=>'Acer Nitro 24 Gaming Monitor','brand'=>'Acer','category'=>'Monitors','sku'=>'AC-NTR24-001','barcode'=>'8901000000025','cost_price'=>45200,'sale_price'=>55900,'description'=>'High refresh monitor for gaming stations.'],
            ['name'=>'HP M24f FHD Monitor','brand'=>'HP','category'=>'Monitors','sku'=>'HP-M24F-001','barcode'=>'8901000000026','cost_price'=>29800,'sale_price'=>36900,'description'=>'Slim full HD monitor for desk setups.'],

            ['name'=>'Logitech MX Master 3S Mouse','brand'=>'Logitech','category'=>'Accessories','sku'=>'LG-MXM3S-001','barcode'=>'8901000000031','cost_price'=>18800,'sale_price'=>23900,'description'=>'Flagship productivity wireless mouse.'],
            ['name'=>'Mechanical RGB Keyboard','brand'=>'Razer','category'=>'Accessories','sku'=>'RZ-MECHKB-001','barcode'=>'8901000000032','cost_price'=>21200,'sale_price'=>26900,'description'=>'Mechanical backlit keyboard with RGB.'],
            ['name'=>'USB-C Docking Station 10-in-1','brand'=>'Anker','category'=>'Accessories','sku'=>'AK-USBC10-001','barcode'=>'8901000000033','cost_price'=>15400,'sale_price'=>19900,'description'=>'Multi-port USB-C docking station.'],
            ['name'=>'Webcam Full HD 1080p','brand'=>'Logitech','category'=>'Accessories','sku'=>'LG-WC1080-001','barcode'=>'8901000000034','cost_price'=>9800,'sale_price'=>12900,'description'=>'1080p webcam for meetings and streaming.'],
            ['name'=>'Bluetooth Headset Pro','brand'=>'JBL','category'=>'Accessories','sku'=>'JB-BTHSP-001','barcode'=>'8901000000035','cost_price'=>8400,'sale_price'=>10900,'description'=>'Wireless headset with ENC microphone.'],
            ['name'=>'Laptop Cooling Pad','brand'=>'HP','category'=>'Accessories','sku'=>'HP-CLPAD-001','barcode'=>'8901000000036','cost_price'=>3200,'sale_price'=>4500,'description'=>'Cooling pad with dual fan support.'],
            ['name'=>'Wireless Presenter','brand'=>'Logitech','category'=>'Accessories','sku'=>'LG-PRSNT-001','barcode'=>'8901000000037','cost_price'=>4100,'sale_price'=>5900,'description'=>'Presentation remote with laser pointer.'],
            ['name'=>'Surge Protector 6-Socket','brand'=>'Anker','category'=>'Accessories','sku'=>'AK-SRG6-001','barcode'=>'8901000000038','cost_price'=>2500,'sale_price'=>3900,'description'=>'Office-grade surge protector strip.'],

            ['name'=>'External SSD 1TB','brand'=>'Kingston','category'=>'Storage','sku'=>'KG-SSD1T-001','barcode'=>'8901000000041','cost_price'=>19800,'sale_price'=>24900,'description'=>'Portable USB 3.2 external SSD 1TB.'],
            ['name'=>'External HDD 2TB','brand'=>'Seagate','category'=>'Storage','sku'=>'SG-HDD2T-001','barcode'=>'8901000000042','cost_price'=>16200,'sale_price'=>20900,'description'=>'2TB external hard drive for backup.'],
            ['name'=>'NVMe SSD 1TB Gen4','brand'=>'Western Digital','category'=>'Storage','sku'=>'WD-NVME1T-001','barcode'=>'8901000000043','cost_price'=>17600,'sale_price'=>21900,'description'=>'High-speed Gen4 NVMe storage.'],
            ['name'=>'USB Flash Drive 128GB','brand'=>'Kingston','category'=>'Storage','sku'=>'KG-USB128-001','barcode'=>'8901000000044','cost_price'=>1850,'sale_price'=>2900,'description'=>'USB 3.0 flash drive 128GB.'],
            ['name'=>'NAS Hard Drive 4TB','brand'=>'Seagate','category'=>'Storage','sku'=>'SG-NAS4T-001','barcode'=>'8901000000045','cost_price'=>24800,'sale_price'=>30900,'description'=>'4TB drive optimized for NAS.'],

            ['name'=>'TP-Link WiFi Router AX3000','brand'=>'TP-Link','category'=>'Networking','sku'=>'TP-AX3000-001','barcode'=>'8901000000051','cost_price'=>12500,'sale_price'=>15900,'description'=>'Dual-band WiFi 6 router.'],
            ['name'=>'Cisco Business Switch 24-Port','brand'=>'Cisco','category'=>'Networking','sku'=>'CS-SW24-001','barcode'=>'8901000000052','cost_price'=>64200,'sale_price'=>78900,'description'=>'Managed business network switch.'],
            ['name'=>'Ubiquiti Access Point U6 Lite','brand'=>'Ubiquiti','category'=>'Networking','sku'=>'UB-U6L-001','barcode'=>'8901000000053','cost_price'=>27500,'sale_price'=>33900,'description'=>'Ceiling-mounted WiFi 6 access point.'],
            ['name'=>'TP-Link 16-Port Switch','brand'=>'TP-Link','category'=>'Networking','sku'=>'TP-SW16-001','barcode'=>'8901000000054','cost_price'=>9800,'sale_price'=>12900,'description'=>'Gigabit unmanaged switch 16-port.'],
            ['name'=>'Cisco Firewall Appliance','brand'=>'Cisco','category'=>'Networking','sku'=>'CS-FW-001','barcode'=>'8901000000055','cost_price'=>118000,'sale_price'=>145000,'description'=>'Entry-level security firewall appliance.'],
            ['name'=>'Ubiquiti PoE Switch 8-Port','brand'=>'Ubiquiti','category'=>'Networking','sku'=>'UB-POE8-001','barcode'=>'8901000000056','cost_price'=>22400,'sale_price'=>27900,'description'=>'Managed PoE switch for AP and cameras.'],

            ['name'=>'Canon Laser Printer LBP2900','brand'=>'Canon','category'=>'Printers','sku'=>'CN-LBP2900-001','barcode'=>'8901000000061','cost_price'=>39200,'sale_price'=>47900,'description'=>'Monochrome laser printer for office use.'],
            ['name'=>'Epson EcoTank L3250','brand'=>'Epson','category'=>'Printers','sku'=>'EP-L3250-001','barcode'=>'8901000000062','cost_price'=>46200,'sale_price'=>55900,'description'=>'Ink tank printer with low running cost.'],
            ['name'=>'HP LaserJet Pro M404','brand'=>'HP','category'=>'Printers','sku'=>'HP-M404-001','barcode'=>'8901000000063','cost_price'=>58500,'sale_price'=>69900,'description'=>'Fast monochrome enterprise printer.'],
            ['name'=>'Canon ImageRunner Scanner','brand'=>'Canon','category'=>'Printers','sku'=>'CN-SCN-001','barcode'=>'8901000000064','cost_price'=>49500,'sale_price'=>61900,'description'=>'Document scanner for back office.'],

            ['name'=>'Sony WH-1000XM5 Headphones','brand'=>'Sony','category'=>'Audio','sku'=>'SY-WH1000XM5-001','barcode'=>'8901000000071','cost_price'=>72500,'sale_price'=>88900,'description'=>'Noise-cancelling flagship headphones.'],
            ['name'=>'JBL Conference Speakerphone','brand'=>'JBL','category'=>'Audio','sku'=>'JB-SPKPH-001','barcode'=>'8901000000072','cost_price'=>21800,'sale_price'=>27900,'description'=>'Speakerphone for conference rooms.'],
            ['name'=>'Logitech USB Speaker Set','brand'=>'Logitech','category'=>'Audio','sku'=>'LG-SPKUSB-001','barcode'=>'8901000000073','cost_price'=>5400,'sale_price'=>7900,'description'=>'Compact USB speaker system.'],
            ['name'=>'Sony Soundbar 2.1 Channel','brand'=>'Sony','category'=>'Audio','sku'=>'SY-SDBR21-001','barcode'=>'8901000000074','cost_price'=>38800,'sale_price'=>46900,'description'=>'Soundbar with wireless subwoofer.'],
        ];
    }
}
