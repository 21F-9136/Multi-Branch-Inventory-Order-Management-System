<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// REST API routes (thin controllers delegating to Services)
$routes->group('', ['namespace' => 'App\Controllers'], static function (RouteCollection $routes) {
	// Auth (public)
	$routes->post('auth/login', 'AuthController::login');
	$routes->post('auth/register', 'AuthController::register');
});

$routes->group('', ['namespace' => 'App\Controllers', 'filter' => 'jwt'], static function (RouteCollection $routes) {
	// Auth (protected)
	$routes->get('auth/me', 'AuthController::me');

	// Dashboard / Reporting
	$routes->get('dashboard/summary', 'DashboardController::summary', ['filter' => 'permission:dashboard.view']);
	$routes->get('manager/dashboard/summary', 'ManagerDashboardController::summary', ['filter' => 'permission:dashboard.view']);

	// Users
	$routes->get('users', 'UserController::index', ['filter' => 'permission:users.read']);
	$routes->get('api/managers', 'UserController::managers', ['filter' => 'permission:branches.read']);
	$routes->put('users/(:num)', 'UserController::update/$1', ['filter' => 'permission:users.manage']);
	$routes->delete('users/(:num)', 'UserController::delete/$1', ['filter' => 'permission:users.manage']);

	// Branches
	$routes->get('branches', 'BranchController::index', ['filter' => 'permission:branches.read']);
	$routes->post('branches', 'BranchController::create', ['filter' => 'permission:branches.manage']);
	$routes->put('branches/(:num)', 'BranchController::update/$1', ['filter' => 'permission:branches.manage']);
	$routes->delete('branches/(:num)', 'BranchController::delete/$1', ['filter' => 'permission:branches.manage']);
	$routes->post('branches/assign-manager', 'BranchController::assignManager', ['filter' => 'permission:branches.manage']);
	$routes->post('branches/move-user', 'BranchController::moveUser', ['filter' => 'permission:branches.manage']);

	// Products
	$routes->get('brands', 'BrandController::index', ['filter' => 'permission:products.read']);
	$routes->get('categories', 'CategoryController::index', ['filter' => 'permission:products.read']);
	$routes->get('products', 'ProductController::index');
	$routes->get('products/(:num)', 'ProductController::show/$1');
	$routes->get('products/(:num)/skus', 'ProductController::listSkus/$1');
	$routes->post('products', 'ProductController::createProduct', ['filter' => 'permission:products.manage']);
	$routes->put('products/(:num)', 'ProductController::updateProduct/$1', ['filter' => 'permission:products.manage']);
	$routes->delete('products/(:num)', 'ProductController::deleteProduct/$1', ['filter' => 'permission:products.manage']);
	$routes->post('products/sku', 'ProductController::createSku', ['filter' => 'permission:skus.manage']);

	// SKUs
	$routes->get('skus', 'SkuController::index', ['filter' => 'permission:skus.read']);
	$routes->put('skus/(:num)', 'SkuController::update/$1', ['filter' => 'permission:skus.manage']);
	$routes->delete('skus/(:num)', 'SkuController::delete/$1', ['filter' => 'permission:skus.manage']);

	// Inventory
	$routes->get('inventories', 'InventoryController::index');
	$routes->get('inventory/ledger', 'InventoryController::ledger');
	$routes->get('inventory/ledger/export', 'InventoryController::ledgerCsv');
	$routes->post('inventory/receive', 'InventoryController::receive');
	$routes->post('inventory/adjust', 'InventoryController::adjust');
	$routes->post('inventory/transfer', 'InventoryController::transfer', ['filter' => 'permission:inventory.manage']);
	$routes->post('inventory/reserve', 'InventoryController::reserve', ['filter' => 'permission:orders.create']);

	// Orders
	$routes->get('orders', 'OrderController::index');
	$routes->get('orders/items', 'OrderController::items', ['filter' => 'permission:orders.read']);
	$routes->get('orders/(:num)', 'OrderController::show/$1');
	$routes->post('orders', 'OrderController::create');
	$routes->post('orders/place', 'OrderController::place');
	$routes->post('orders/(:num)/complete', 'OrderController::complete/$1');
	$routes->post('orders/(:num)/cancel', 'OrderController::cancel/$1');

	// Invoices (order-based)
	$routes->get('invoices', 'InvoiceController::index');
	$routes->get('invoices/(:num)', 'InvoiceController::show/$1');
	$routes->get('invoices/(:num)/pdf', 'InvoiceController::pdf/$1');
});
