# Developer Handbook

This handbook is the operational guide for contributors working on Inventory AI System.

## 1. System Overview

Inventory AI System is a full-stack monorepo:

- Backend API: CodeIgniter 4 (PHP 8.2+)
- Frontend app: Vue 3 + TypeScript + Vite
- Data store: MySQL
- Auth model: JWT + role/permission filters

Core domains:

- Authentication and authorization
- Branches and users
- Products and SKUs
- Inventory balances and stock movements
- Orders and invoices
- Dashboard/reporting

## 2. Repository Structure

```text
inventory-ai-system/
  backend/
    inventory-system/
      app/
      public/
      tests/
      writable/
  frontend/
    src/
  docs/
```

Important backend locations:

- app/Config/Routes.php: API route map
- app/Config/Filters.php: JWT + permission filters
- app/Database/Migrations: database schema evolution
- app/Database/Seeds: demo/bootstrap data

Important frontend locations:

- src/router/index.ts: route map and role redirects
- src/services/api.ts: Axios client, base URL, auth headers
- src/store/auth.ts: auth state + local persistence
- src/pages: feature pages

## 3. Local Environment Setup

### 3.1 Prerequisites

- PHP 8.2+
- Composer
- MySQL or MariaDB
- Node.js 20+ and npm

### 3.2 Backend Setup

```bash
cd backend/inventory-system
composer install
```

Environment:

1. Copy env to .env
2. Configure at minimum:

- app.baseURL=http://localhost:8080/
- database.default.hostname
- database.default.database
- database.default.username
- database.default.password
- JWT_SECRET (must be changed outside local development)

Database bootstrap:

```bash
php spark migrate
php spark db:seed MasterDemoSeeder
```

Run backend:

```bash
php spark serve
```

### 3.3 Frontend Setup

```bash
cd frontend
npm install
```

Environment:

1. Copy .env.example to .env
2. Set:

- VITE_API_URL=http://localhost:8080

Run frontend:

```bash
npm run dev
```

## 4. Authentication, RBAC, and Access

### 4.1 Roles

Seeded roles:

- super_admin
- manager
- sales

### 4.2 Permission Enforcement

Protected backend routes use:

- jwt filter for identity
- permission filter for authorization

Representative permissions:

- dashboard.view
- branches.read / branches.manage
- users.read / users.manage
- products.read / products.manage
- skus.read / skus.manage
- inventory.read / inventory.manage
- orders.read / orders.create / orders.place

### 4.3 Frontend Route Guarding

Client-side guard behavior:

- Unauthenticated users are redirected to /login
- Authenticated users are redirected to role-specific dashboards
- Route meta roles are checked in router guard logic

Server-side checks still remain the source of truth.

## 5. Data and Seeders

Main migration groups include:

- Roles, permissions, and user-role mappings
- Branches and users
- Products and SKUs
- Inventory balances and stock movements
- Orders and order lines
- Reservations and audit/outbox records

Primary seeders:

- RbacSeeder: roles, permissions, default super admin
- BranchSeeder: branch records
- UserSeeder: manager/sales users
- ProductSeeder and InventorySeeder
- OrderSeeder
- MasterDemoSeeder: orchestrates full demo bootstrap

Seeded credentials:

- Password for seeded users: 123456
- Example super admin: admin@erp.com

## 6. API Surface (High-Level)

Auth:

- POST /auth/login
- POST /auth/register
- GET /auth/me

Catalog:

- GET /brands
- GET /categories
- GET /products
- GET /products/{id}
- GET /products/{id}/skus
- POST /products
- PUT /products/{id}
- DELETE /products/{id}
- POST /products/sku
- GET /skus

Inventory:

- GET /inventories
- GET /inventory/ledger
- GET /inventory/ledger/export
- POST /inventory/receive
- POST /inventory/adjust
- POST /inventory/transfer
- POST /inventory/reserve

Orders/Invoices:

- GET /orders
- GET /orders/{id}
- POST /orders
- POST /orders/place
- POST /orders/{id}/complete
- POST /orders/{id}/cancel
- GET /invoices
- GET /invoices/{id}
- GET /invoices/{id}/pdf

## 7. Frontend Feature Map

Main pages:

- Login
- Dashboard, Manager Dashboard, Sales Dashboard
- Products, SKUs
- Inventory, Stock Ledger
- Orders, Order Details
- Invoices, Invoice Details
- Users, Branches

State stores include auth, notifications, and theme.

## 8. Testing and Quality

Backend tests:

```bash
cd backend/inventory-system
composer test
```

PHPUnit config is in phpunit.xml.dist.

Frontend checks/build:

```bash
cd frontend
npm run build
npm run preview
```

## 9. Deployment Notes

Backend:

- Serve from backend/inventory-system/public as document root
- Set CI_ENVIRONMENT=production
- Use strong JWT_SECRET
- Ensure writable directory permissions

CORS:

- Limit allowed origins to trusted frontend domains

Frontend:

- Build with npm run build
- Serve static output from dist/

## 10. Troubleshooting

401 Unauthorized:

- Token missing/expired
- JWT secret mismatch across environments

403 Forbidden:

- User role lacks required permission

CORS errors:

- Frontend origin not present in backend CORS settings

Frontend cannot call API:

- Invalid VITE_API_URL
- Wrong backend host or port

Seeder issues:

- Ensure migration order is complete before seeding
- Run MasterDemoSeeder for full baseline data

## 11. Useful Command Reference

```bash
# Backend
cd backend/inventory-system
composer install
php spark migrate
php spark db:seed MasterDemoSeeder
php spark serve
composer test

# Frontend
cd frontend
npm install
npm run dev
npm run build
npm run preview
```
