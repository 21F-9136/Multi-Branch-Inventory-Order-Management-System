---
# Project Execution Plan & Task List (Multi-Branch Inventory & Order Management System)

**Overall Project Goal:** Deliver a production-grade Multi-Branch Inventory & Order Management System as a modular monolith (CodeIgniter 4 backend + Vue 3 SPA) with MySQL as system of record, Redis caching for read-heavy derived views, and a transactional outbox + workers for reliable async processing. The system must remain correct under concurrency and meet the PRD performance targets (1000+ concurrent users).

**Authoritative reference docs:**
- rules/system.md (architecture, patterns, module structure)
- doing/multi-branch-inventory-system/1.prd-multi-branch-inventory-system.md (requirements and acceptance criteria)

---

## Local Dev Prerequisites & Quickstart (Engineer Handoff)

**Prerequisites:**
- Docker Desktop (Windows) with Compose v2 available as `docker compose`.
- Git.
- Optional (only if running without Docker): PHP 8.2+, Composer, Node 20+.
- Optional (only for Phase 8.3 load tests): Java + Apache JMeter CLI available as `jmeter`.

**Expected local files (not committed):**
- `.env` (copy from `.env.example`)

**Quickstart commands (Docker):**
1. Start services: `docker compose -f infra/docker-compose.yml up -d --build`
2. Verify containers: `docker compose -f infra/docker-compose.yml ps`
3. Run migrations (once backend is wired): `docker compose -f infra/docker-compose.yml exec app php spark migrate`
4. Seed baseline data (once seeds exist): `docker compose -f infra/docker-compose.yml exec app php spark db:seed DatabaseSeeder`

**Common failures & recovery:**
- Port conflicts (MySQL/Redis): stop local services using the ports or change published ports in `infra/docker-compose.yml`.
- MySQL init failing / corrupted volume: stop stack, then remove volume(s), then restart: `docker compose -f infra/docker-compose.yml down -v` then `up -d`.
- Container cannot reach MySQL: confirm service name matches `MYSQL_HOST` (usually `mysql`) and both services share the same compose network.

**Task conventions (to keep this handoff self-contained):**
- If a task introduces a new endpoint or workflow, it should also add (or update) docs in `docs/api/README.md` and include at least one verification command (curl/Postman) and one negative case.
- Correctness-critical inventory/order tasks should include at least one feature test and one concurrency-focused validation step (even if initially manual).
- Unless a task explicitly says otherwise, all list endpoints must use the standard list envelope and pagination helpers from Appendix A9 and all errors must use the standard error schema (Appendix A9 `ApiResponder::error()`).

## Target Repository Structure (Comprehensive File Tree)

This file tree is the **source of truth** for paths referenced throughout tasks.

- backend/
  - app/
    - Commands/
    - Config/
      - Routes.php
      - Filters.php
    - Database/
      - Migrations/
      - Seeds/
    - Filters/
      - RequestIdFilter.php
    - Modules/
      - Shared/
        - Controllers/
        - Dto/
        - Utils/
      - Auth/
        - Controllers/
        - Services/
      - Rbac/
        - Controllers/
        - Policies/
        - Services/
      - Branches/
        - Controllers/
        - Services/
        - Models/
      - Catalog/
        - Controllers/
        - Services/
        - Models/
      - Inventory/
        - Controllers/
        - Services/
        - Models/
      - Orders/
        - Controllers/
        - Services/
        - Models/
      - Reporting/
        - Controllers/
        - Queries/
        - Services/
    - Services/ (optional: shared infrastructure services)
  - public/
    - index.php
  - writable/
  - composer.json
  - phpunit.xml
  - tests/
    - unit/
    - feature/

- frontend/
  - src/
    - api/
      - client.ts
      - types.ts
    - app/
      - router/
      - store/
    - modules/
      - auth/
      - branches/
      - catalog/
      - inventory/
      - orders/
      - reporting/
    - components/
    - pages/
    - styles/
    - main.ts
  - package.json

- infra/
  - docker-compose.yml

- docs/
  - api/
    - README.md
  - architecture/
    - auth.md
  - runbooks/
    - local-dev.md
    - deployment.md
    - outages.md

- load-test/
  - jmeter/
    - scenarios/
    - results/

- rules/
  - system.md
  - tasks.md

## Explicit Dependencies (Blocking Relationships)

- Phase 0 blocks everything else (local runtime + environment templates).
- Phase 1.1 (backend skeleton + routing + error schema + request_id) blocks all later API work.
- Phase 1.2 (RBAC/audit migrations) should land before any business module endpoints (Phases 2–5) so auditing and access control are built-in, not bolted on.
- Phase 3 inventory schemas + invariants block Phase 4 order reservation correctness.
- Phase 6 outbox/workers can be implemented in parallel after Phases 3–4 schemas exist, but should be integrated before caching-heavy Phase 5 is considered “done”.
- Phase 8 tests/load/ops docs are ongoing, but must be completed before declaring production readiness.

## Standard Commands (Copy/Paste)

Use these commands as acceptance checks after tasks land:

- Start stack: `docker compose -f infra/docker-compose.yml up -d --build`
- Tail logs: `docker compose -f infra/docker-compose.yml logs -f app` and `docker compose -f infra/docker-compose.yml logs -f worker`
- Backend deps (if not baked into image): `docker compose -f infra/docker-compose.yml exec app composer install`
- Migrate: `docker compose -f infra/docker-compose.yml exec app php spark migrate`
- Seed: `docker compose -f infra/docker-compose.yml exec app php spark db:seed DatabaseSeeder`
- Backend tests: `docker compose -f infra/docker-compose.yml exec app vendor/bin/phpunit`

- Frontend deps: `cd frontend; npm ci`
- Frontend dev server: `cd frontend; npm run dev`
- Frontend build: `cd frontend; npm run build`

## Target VPS Layout (Deployment Filesystem Expectations)

This is the expected layout on a Linux VPS when deploying via Docker Compose.

- `/opt/inventory-ai-system/`
  - `infra/docker-compose.yml`
  - `.env` (server-specific secrets; not committed)
  - `backend/` (if using bind-mount deploys; otherwise omit and use images)
  - `frontend/` (if serving SPA separately; otherwise omit and use images)
  - `docs/runbooks/` (copied or referenced for ops)

**Access required (deployment engineer):**
- SSH access to VPS
- Permission to run Docker and manage system services
- Firewall rules open for HTTP(S) and any admin-only ports (MySQL/Redis should not be publicly exposed)

---

## Phase 0: Project Setup & Essential Configuration

### 0.1 Repository structure and baseline tooling

- **Task 0.1: Create baseline repo directories** [ ]

  - **Objective:** Establish the target project structure for backend, frontend, infra, docs, and tests.
  - **Files:** (directories)
    - backend/, frontend/, infra/, docs/, load-test/, rules/
  - **Action(s):**
    - Create the top-level directories (empty is OK initially).
  - **Commands:**
    - Windows PowerShell: `mkdir backend, frontend, infra, docs, load-test, rules`
    - Verify tree: `ls` (or `Get-ChildItem`) at repo root
  - **Verification/Deliverable(s):** The workspace contains the above directories and matches the structure described in rules/system.md.

- **Task 0.2: Add baseline environment templates** [ ]

  - **Objective:** Define reproducible local configuration without committing secrets.
  - **Files:**
    - .env.example
    - .gitignore (update)
  - **Action(s):**
     1. Create .env.example at repo root with placeholders (exact keys, so every dev uses the same names):
       - APP_ENV=development
       - APP_BASE_URL=http://localhost:8080
       - MYSQL_HOST=mysql
       - MYSQL_PORT=3306
       - MYSQL_DATABASE=inventory
       - MYSQL_USER=inventory
       - MYSQL_PASSWORD=inventory
       - REDIS_HOST=redis
       - REDIS_PORT=6379
       - AUTH_JWT_SECRET=change_me (if JWT)
       - AUTH_SESSION_SECRET=change_me (if cookies/sessions)
    2. Ensure .gitignore includes .env and other secret files.
        - Use Appendix A7 as the baseline.
  - **Commands:**
    - Sanity check ignored files: `git status` should not show `.env`
  - **Verification/Deliverable(s):** .env.example exists; no secrets are committed.

- **Task 0.3: Define code style and editor consistency** [ ]

  - **Objective:** Reduce friction across backend/frontend development.
  - **Files:**
    - .editorconfig
  - **Action(s):**
    - Add/update .editorconfig with line endings, indentation, and charset.
    - Use Appendix A6 as the baseline.
  - **Verification/Deliverable(s):** .editorconfig exists and applies to PHP/TS/MD files.

### 0.2 Local runtime via Docker Compose

- **Task 0.4: Create Docker Compose for local development** [ ]

  - **Objective:** Provide local parity for MySQL + Redis + app + workers.
  - **Files:**
    - infra/docker-compose.yml
  - **Action(s):**
    1. Create infra/docker-compose.yml with services (names are referenced by later runbooks/commands):
       - mysql (InnoDB)
       - redis
       - app (backend runtime)
       - worker (outbox/queue worker runtime)
       - optional nginx (reverse proxy)
    2. Add volume mounts for local code and persistent DB data.
    3. Ensure `app` and `worker` receive environment variables from `.env` (or `.env` is mounted) without committing secrets.
    4. Publish ports for local access (documented in the runbook):
       - MySQL (optional for local client access)
       - Redis (optional)
       - App HTTP port
    5. Use Appendix A1 as a starting point (expect to replace `app`/`worker` images with a proper PHP image + extensions as backend work begins).
  - **Commands:**
    - Start stack: `docker compose -f infra/docker-compose.yml up -d --build`
    - Check health: `docker compose -f infra/docker-compose.yml ps`
    - Logs: `docker compose -f infra/docker-compose.yml logs -f mysql` and `docker compose -f infra/docker-compose.yml logs -f redis`
  - **Troubleshooting:**
    - If MySQL never becomes healthy: reset volumes `docker compose -f infra/docker-compose.yml down -v` then `up -d`
  - **Verification/Deliverable(s):** Running docker compose up starts mysql + redis successfully; app containers boot and can reach mysql/redis.

- **Task 0.5: Document local dev runbook** [ ]

  - **Objective:** Make setup and troubleshooting repeatable for the team.
  - **Files:**
    - docs/runbooks/local-dev.md
  - **Action(s):** Create docs/runbooks/local-dev.md covering:
    - prerequisites (Docker Desktop)
    - commands to start/stop (copy/paste-ready)
    - resetting DB (including `down -v` warning)
    - verifying connectivity (health endpoint + MySQL ping)
    - where logs live (`docker compose logs -f app`, `docker compose logs -f worker`)
    - known failure modes and recovery (ports, volumes, mysql health)
  - **Verification/Deliverable(s):** A new runbook exists and can be followed end-to-end by a new engineer.

---

## Phase 1: Backend Foundation (CodeIgniter 4) + Auth/RBAC + Audit

### 1.1 Backend skeleton and API conventions

- **Task 1.1: Initialize backend application skeleton** [ ]

  - **Objective:** Create a working CodeIgniter 4 backend with the module structure described in rules/system.md.
  - **Files:**
    - backend/composer.json
    - backend/public/index.php
    - backend/app/Modules/* (directories)
  - **Action(s):**
    - Create backend/ with a standard CodeIgniter 4 app skeleton.
    - Create module directories under backend/app/Modules/: Auth, Rbac, Branches, Catalog, Inventory, Orders, Reporting, Shared.
    - Ensure backend has a single public entrypoint suitable for Docker (e.g., `backend/public/index.php`).
    - Add a health endpoint (Task 1.2) before expanding other modules.
  - **Commands:**
    - (Once Docker image supports it) `docker compose -f infra/docker-compose.yml exec app php spark`
  - **Verification/Deliverable(s):** Backend can serve a health endpoint and load module routes.

- **Task 1.2: Implement API versioning and routing entry** [ ]

  - **Objective:** Ensure all backend APIs are exposed under a versioned base path.
  - **Files:**
    - backend/app/Config/Routes.php (update; see Appendix A2)
    - backend/app/Controllers/HealthController.php (new; see Appendix A3)
  - **Action(s):**
    - Configure routes so all APIs are under `/api/v1` (suggested: route group in backend/app/Config/Routes.php).
    - Add a minimal `GET /api/v1/health` endpoint returning JSON `{ "status": "ok", "request_id": "..." }`.
  - **Commands:**
    - Verify from host: `curl http://localhost:8080/api/v1/health`
  - **Verification/Deliverable(s):** `GET /api/v1/health` returns 200 JSON including `request_id`.

- **Task 1.2.1: Standardize list response envelope + pagination contract** [ ]

  - **Objective:** Meet PRD API requirements for pagination and consistent list envelopes.
  - **Files:**
    - backend/app/Modules/Shared/Utils/ApiResponder.php (new; see Appendix A9)
    - backend/app/Modules/Shared/Utils/Pagination.php (new; see Appendix A9)
  - **Action(s):**
    1. Define a shared list response envelope used by all list endpoints:
       - data: array
       - pagination: { page, per_page, total, total_pages }
       - request_id
    2. Standardize request parameters (minimum):
       - page, per_page
       - optional sort (field, direction)
    3. Implement helpers/DTOs under:
       - backend/app/Modules/Shared/Dto/
       - backend/app/Modules/Shared/Utils/
  - **Verification/Deliverable(s):** Branch list and product list endpoints return the standard envelope with correct pagination metadata.

- **Task 1.3: Implement consistent error response schema** [ ]

  - **Objective:** Satisfy PRD requirement for consistent error formats and clear permission messaging.
  - **Files:**
    - backend/app/Modules/Shared/Utils/ApiResponder.php (update; use `ApiResponder::error()` from Appendix A9)
  - **Action(s):**
    1. Define a shared error response shape: { code, message, details, request_id }.
    2. Add centralized exception and validation handling so controllers across modules produce the same error format.
    3. Implement shared helpers under backend/app/Modules/Shared/Dto/ and backend/app/Modules/Shared/Utils/.
  - **Verification/Deliverable(s):** Validation errors and permission errors use the same schema across modules and include `request_id`.

- **Task 1.4: Add request correlation ID support** [ ]

  - **Objective:** Support structured logging and cross-service tracing (PRD observability).
  - **Files:**
    - backend/app/Filters/RequestIdFilter.php (new; see Appendix A4)
    - backend/app/Config/Filters.php (update; see Appendix A8)
  - **Action(s):**
    - Add a filter/middleware to generate or propagate a request ID.
    - Include request_id in logs and error responses.
  - **Verification/Deliverable(s):** Every API response includes `X-Request-Id` response header and a `request_id` field in JSON bodies where applicable.

- **Task 1.4.1: Configure structured logging for API requests and business events** [ ]

  - **Objective:** Meet PRD observability requirements and speed up troubleshooting.
  - **Files:**
    - backend/app/Config/Logger.php (update, if choosing JSON log format)
    - backend/app/Modules/Shared/Utils/ (optional: wrappers to ensure consistent context fields)
  - **Action(s):**
    - Implement structured (JSON) logging where practical.
    - Ensure logs include: request_id, actor_id (if authenticated), branch_id (when applicable), module, action.
    - Log key business events: order created, stock reserved, stock deducted.
  - **Verification/Deliverable(s):**
    - A single request produces logs containing request_id.
    - Key business events log entries include actor_id + branch_id when applicable.

- **Task 1.4.2: Log access-denied (authorization) events without leaking sensitive data** [ ]

  - **Objective:** Satisfy PRD requirement that denied actions are logged and produce clear messages.
  - **Files:**
    - backend/app/Modules/Rbac/ (filter/policy implementation)
    - backend/app/Modules/Shared/Utils/ (logging helper, optional)
  - **Action(s):**
    - On permission denial, log an authorization event including request_id, actor_id (if present), branch scope, and required permission.
    - Ensure the API response remains non-sensitive (no internal policy details beyond a safe code/message).
  - **Verification/Deliverable(s):** Permission denials are logged consistently across modules.

- **Task 1.4.3: Implement basic API rate limiting where applicable** [ ]

  - **Objective:** Protect against abuse while meeting PRD security guidance.
  - **Files:**
    - backend/app/Filters/ (e.g., RateLimitFilter.php)
    - backend/app/Config/Filters.php (register rate limiting on auth routes)
  - **Action(s):**
    - Apply stricter rate limits to auth endpoints (login/reset).
    - Optionally apply conservative rate limits to expensive endpoints (search, reporting) if profiling indicates risk.
  - **Verification/Deliverable(s):** Rate limiting triggers on abuse patterns (returns 429); normal usage is unaffected.

- **Task 1.4.4: Create API documentation baseline for /api/v1** [ ]

  - **Objective:** Meet PRD requirement that APIs are documented.
  - **Files:**
    - docs/api/README.md (new; use Appendix A5)
  - **Action(s):**
    - Create docs/api/README.md describing:
      - base URL and versioning
      - auth method
      - list envelope + pagination
      - error schema
    - Add module sections (Branches, Catalog, Inventory, Orders, Reporting) and keep updated as endpoints ship.
  - **Verification/Deliverable(s):** Developers can discover endpoints, auth, pagination, and error formats from docs/api/README.md.

### 1.2 Database migrations: core identity + audit

- **Task 1.5: Create migrations for users, roles, permissions, and branch scoping** [ ]

  - **Objective:** Implement the RBAC data model required by the PRD.
  - **Files:**
    - backend/app/Database/Migrations/*_create_users.php
    - backend/app/Database/Migrations/*_create_roles.php
    - backend/app/Database/Migrations/*_create_permissions.php
    - backend/app/Database/Migrations/*_create_user_roles.php
    - backend/app/Database/Migrations/*_create_role_permissions.php
    - backend/app/Database/Migrations/*_create_user_branches.php
  - **Action(s):** Create migrations/tables:
    - Create migration files under `backend/app/Database/Migrations/` (or module-local migrations if the repo standardizes that; pick one approach and document it in the local-dev runbook).
    - users
    - roles
    - permissions
    - user_roles
    - role_permissions
    - user_branches
    - Add foreign keys and indexes needed for lookup (e.g., user_roles.user_id, role_permissions.role_id, user_branches.(user_id, branch_id)).
  - **Verification/Deliverable(s):**
    - `docker compose -f infra/docker-compose.yml exec app php spark migrate` completes successfully.
    - Tables exist with correct constraints and indexes; basic seed data can be inserted.

- **Task 1.5.1: Create seeders for baseline roles/permissions and a local admin user** [ ]

  - **Objective:** Make RBAC testable immediately in local/dev environments.
  - **Files:**
    - backend/app/Database/Seeds/DatabaseSeeder.php
    - backend/app/Database/Seeds/RbacSeeder.php
    - backend/app/Database/Seeds/DevAdminSeeder.php
  - **Action(s):**
    - Create seeders that insert:
      - baseline permissions (aligned to modules/actions)
      - baseline roles (e.g., Admin, Manager, Staff, Auditor)
      - a local-only admin user with a known password set via env vars
    - Ensure seeders are safe for local/dev only (do not ship default credentials in production).
  - **Commands:**
    - `docker compose -f infra/docker-compose.yml exec app php spark db:seed DatabaseSeeder`
  - **Verification/Deliverable(s):** A local admin can authenticate and access protected endpoints.

- **Task 1.6: Create migrations for audit_logs** [ ]

  - **Objective:** Ensure sensitive actions are audited (US-004, US-035).
  - **Files:**
    - backend/app/Database/Migrations/*_create_audit_logs.php
  - **Action(s):** Create audit_logs table with:
    - Create migration under `backend/app/Database/Migrations/`.
    - actor_id (user)
    - action_type
    - branch_id (nullable)
    - payload_json (before/after/metadata)
    - created_at
    - Add indexes for audit queries (minimum: created_at; optionally (branch_id, created_at), (actor_id, created_at), (action_type, created_at)).
  - **Verification/Deliverable(s):**
    - `docker compose -f infra/docker-compose.yml exec app php spark migrate` creates the table.
    - Audit log entries can be created and queried; table is append-only by convention.

### 1.3 Authentication and login protection


- **Task 1.7: Decide SPA authentication mechanism and security posture** [ ]

  - **Objective:** Pick an auth approach compatible with a Vue SPA and PRD security requirements.
  - **Files:**
    - docs/architecture/auth.md
  - **Action(s):**
    1. Decide one of:
       - JWT bearer tokens (Authorization header)
       - session cookies (requires CSRF protection)
    2. Document the decision and required protections (CSRF where applicable, secure cookie flags if used).
  - **Verification/Deliverable(s):** A short decision note is added to docs/architecture/auth.md (or equivalent).

- **Task 1.7.1: Implement login endpoint and session/token issuance** [ ]

  - **Objective:** Deliver US-001 (login).
  - **Files:**
    - backend/app/Modules/Auth/Controllers/AuthController.php (or LoginController.php)
    - backend/app/Config/Routes.php (add POST route under /api/v1)
    - docs/api/README.md (document endpoint)
  - **Action(s):**
    - Implement login endpoint controller (suggested: backend/app/Modules/Auth/Controllers/AuthController.php or LoginController.php).
    - Verify password hashing and account status checks.
    - Return authenticated session/token on success; return generic error on failure.
    - Suggested endpoint: `POST /api/v1/auth/login`.
  - **Commands:**
    - `curl -X POST http://localhost:8080/api/v1/auth/login -H "Content-Type: application/json" -d '{"username":"admin","password":"..."}'`
  - **Verification/Deliverable(s):** Valid credentials yield auth; invalid credentials return a generic failure message.

- **Task 1.7.2: Implement logout endpoint and invalidation behavior** [ ]

  - **Objective:** Deliver US-002 (logout).
  - **Files:**
    - backend/app/Modules/Auth/Controllers/AuthController.php (or LogoutController.php)
    - backend/app/Config/Routes.php (add POST route under /api/v1)
    - docs/api/README.md (document endpoint)
  - **Action(s):**
    - Implement logout endpoint controller (suggested: backend/app/Modules/Auth/Controllers/AuthController.php or LogoutController.php).
    - Invalidate token/session according to the chosen auth mechanism.
    - Suggested endpoint: `POST /api/v1/auth/logout`.
  - **Verification/Deliverable(s):** After logout, protected endpoints require re-authentication.

- **Task 1.8: Implement login throttling / rate limiting** [ ]

  - **Objective:** Meet US-001 requirement for throttling after multiple failures.
  - **Files:**
    - backend/app/Modules/Auth/Services/LoginThrottle.php
    - backend/app/Modules/Auth/Controllers/AuthController.php (use throttle)
  - **Action(s):**
    - Track failed attempts per identity and source.
    - Enforce temporary throttling after threshold.
  - **Verification/Deliverable(s):** Repeated failed logins trigger throttling (429 or 400 with safe code); error message remains generic.

- **Task 1.9: Implement password reset and password change flows** [ ]

  - **Objective:** Deliver US-028 and US-029.
  - **Files:**
    - backend/app/Modules/Auth/Controllers/PasswordController.php (suggested)
    - backend/app/Modules/Auth/Services/PasswordResetService.php
    - backend/app/Database/Migrations/*_create_password_reset_tokens.php
  - **Action(s):**
    - Implement as separate endpoints and tests (see tasks 1.9.1–1.9.3).
  - **Verification/Deliverable(s):** Password reset does not leak user existence; old password stops working after reset.

- **Task 1.9.1: Implement password reset request endpoint (non-enumerating)** [ ]

  - **Objective:** Deliver the first half of US-028 without account enumeration.
  - **Action(s):**
    - Create Auth endpoint that accepts an identifier and always returns a generic success message.
    - Generate a reset token and deliver it via the chosen channel (email integration can be stubbed behind an interface initially).
  - **Verification/Deliverable(s):** Response is identical for existing vs non-existing accounts.

- **Task 1.9.2: Implement password reset completion endpoint** [ ]

  - **Objective:** Complete US-028.
  - **Action(s):**
    - Validate reset token, enforce expiry, set new password.
    - Ensure previously valid credentials/tokens are invalidated as required.
  - **Verification/Deliverable(s):** Old password no longer works; user can login with the new password.

- **Task 1.9.3: Implement change password endpoint (requires current password)** [ ]

  - **Objective:** Deliver US-029.
  - **Action(s):**
    - Require current password.
    - Enforce configured complexity.
  - **Verification/Deliverable(s):** Password change fails on wrong current password and succeeds otherwise.

### 1.4 Authorization (RBAC) enforcement + auditing

- **Task 1.10: Implement permission checks for API requests** [ ]

  - **Objective:** Enforce server-side RBAC per branch (US-003).
  - **Files:**
    - backend/app/Config/Filters.php (register RBAC filter alias)
    - backend/app/Modules/Rbac/Policies/*
    - backend/app/Modules/Rbac/Services/AuthorizationService.php
  - **Action(s):**
    - Add an authorization filter/policy system (e.g., a CI4 Filter configured in backend/app/Config/Filters.php).
    - Ensure branch-scoped resources validate access to the branch (do not rely on frontend enforcement).
    - Ensure 403 responses follow the standard error schema (Task 1.3) and are logged (Task 1.4.2).
  - **Verification/Deliverable(s):** Unauthorized requests return consistent permission errors; actions do not occur.

- **Task 1.11: Implement audit logging for sensitive actions** [ ]

  - **Objective:** Deliver US-004 and US-035.
  - **Action(s):**
    - Add a shared audit logger utility (suggested location: backend/app/Modules/Shared/Utils/AuditLogger.php).
    - Log stock adjustments, order cancellations, role changes, user deactivations.
    - Ensure audit writes happen in the same transaction as the business change when applicable.
  - **Verification/Deliverable(s):** Audit logs record actor, timestamp, branch scope, and before/after where applicable.

- **Task 1.11.1: Implement audit log query endpoint (filters + pagination)** [ ]

  - **Objective:** Deliver US-035.
  - **Files:**
    - backend/app/Modules/Rbac/Controllers/AuditLogController.php (suggested)
    - docs/api/README.md (document endpoint)
  - **Action(s):**
    - Implement backend/app/Modules/Rbac/Controllers/ (or a dedicated Audit module if preferred) endpoint to list audit logs.
    - Support filters: date range, actor, branch, action type.
    - Use the standard list envelope and pagination contract.
  - **Verification/Deliverable(s):** Auditor can query audit logs by filters; results are paginated and immutable.

- **Task 1.12: Implement user administration endpoints (create/deactivate)** [ ]

  - **Objective:** Deliver US-030.
  - **Files:**
    - backend/app/Modules/Rbac/Controllers/UsersController.php
    - docs/api/README.md
  - **Action(s):**
    - Create endpoints to create users and deactivate users.
    - Enforce uniqueness (email/username).
  - **Verification/Deliverable(s):** Deactivated user cannot login; historical audit attribution remains.

- **Task 1.13: Implement role/permission administration endpoints** [ ]

  - **Objective:** Deliver US-031.
  - **Files:**
    - backend/app/Modules/Rbac/Controllers/RolesController.php
    - backend/app/Modules/Rbac/Controllers/PermissionsController.php (optional)
    - docs/api/README.md
  - **Action(s):**
    - Create endpoints to create/update roles and assign permissions.
    - Audit role permission changes with before/after.
  - **Verification/Deliverable(s):** Permission changes take effect immediately; audit entries exist.

---

## Phase 2: Branch Management + Catalog (Products/SKUs)

### 2.1 Branches

- **Task 2.1: Implement Branches migrations and constraints** [ ]

  - **Objective:** Support branch creation and deactivation (US-005).
  - **Files:**
    - backend/app/Database/Migrations/*_create_branches.php
  - **Action(s):**
    - Create branches table with unique branch code/name.
    - Add active/deactivated flag and timestamps.
  - **Verification/Deliverable(s):** Branch uniqueness is enforced; deactivated branches can be represented.

- **Task 2.2: Implement Branches API (CRUD + deactivate)** [ ]

  - **Objective:** Deliver US-005.
  - **Files:**
    - backend/app/Modules/Branches/Controllers/BranchesController.php
    - backend/app/Modules/Branches/Services/BranchesService.php
    - backend/app/Config/Routes.php (add routes under /api/v1)
    - docs/api/README.md
  - **Action(s):**
    - Implement endpoints to create, update, list, and deactivate branches.
    - Enforce RBAC and audit deactivation.
    - Suggested endpoints:
      - `GET /api/v1/branches`
      - `POST /api/v1/branches`
      - `PATCH /api/v1/branches/{branchId}`
      - `POST /api/v1/branches/{branchId}/deactivate`
  - **Commands:**
    - List: `curl http://localhost:8080/api/v1/branches?page=1&per_page=25`
  - **Verification/Deliverable(s):** CRUD works with standard list envelope and error schema; deactivated branch blocks new operational actions (enforced later in Phase 3/4).

- **Task 2.3: Implement user-to-branch assignment APIs** [ ]

  - **Objective:** Deliver US-006.
  - **Files:**
    - backend/app/Modules/Rbac/Controllers/UserBranchesController.php (suggested)
    - backend/app/Modules/Rbac/Services/UserBranchService.php
    - docs/api/README.md
  - **Action(s):**
    - Create endpoints to assign users to branches.
    - Ensure branch assignments constrain data access.
  - **Verification/Deliverable(s):** A user assigned only to Branch A cannot access Branch B resources.

### 2.2 Catalog

- **Task 2.4: Implement Products/SKUs migrations with uniqueness rules** [ ]

  - **Objective:** Deliver US-007 and US-009.
  - **Files:**
    - backend/app/Database/Migrations/*_create_products.php
    - backend/app/Database/Migrations/*_create_skus.php
  - **Action(s):**
    - Create products and skus tables.
    - Enforce unique constraints for sku_code and barcode.
  - **Verification/Deliverable(s):** Duplicate SKU/barcode attempts are rejected with a validation error.

- **Task 2.5: Implement Catalog APIs (CRUD for products and SKUs)** [ ]

  - **Objective:** Provide catalog management endpoints.
  - **Files:**
    - backend/app/Modules/Catalog/Controllers/ProductsController.php
    - backend/app/Modules/Catalog/Controllers/SkusController.php
    - backend/app/Modules/Catalog/Services/CatalogService.php
    - docs/api/README.md
  - **Action(s):**
    - Implement create/update endpoints for products and SKUs.
    - Implement pagination for list endpoints.
    - Suggested endpoints:
      - `GET /api/v1/catalog/products`
      - `POST /api/v1/catalog/products`
      - `PATCH /api/v1/catalog/products/{productId}`
      - `GET /api/v1/catalog/skus`
      - `POST /api/v1/catalog/skus`
      - `PATCH /api/v1/catalog/skus/{skuId}`
  - **Verification/Deliverable(s):** CRUD works with RBAC enforcement, standard list envelope, and consistent error schema.

- **Task 2.6: Implement product search endpoint with pagination** [ ]

  - **Objective:** Deliver US-008.
  - **Files:**
    - backend/app/Modules/Catalog/Controllers/ProductSearchController.php (optional; can be ProductsController::search)
    - backend/app/Modules/Catalog/Services/ProductSearchService.php
    - docs/api/README.md
  - **Action(s):**
    - Implement search by common attributes.
    - Add pagination parameters and response envelope.
    - Suggested endpoint: `GET /api/v1/catalog/products/search?q=...&page=1&per_page=25`
  - **Commands:**
    - `curl "http://localhost:8080/api/v1/catalog/products/search?q=wire&page=1&per_page=25"`
  - **Verification/Deliverable(s):** Search responds quickly and returns paginated results.

- **Task 2.7: Add Redis caching for product search results** [ ]

  - **Objective:** Meet PRD caching guidance for read-heavy search.
  - **Files:**
    - backend/app/Modules/Catalog/Services/ProductSearchService.php (update)
    - backend/app/Modules/Shared/Utils/ (Redis cache wrapper, optional)
  - **Action(s):**
    - Implement bounded cache key strategy (avoid unbounded filter combinations).
    - Add TTL and invalidation via events (implemented in Phase 6).
  - **Verification/Deliverable(s):** Repeated common searches hit cache; updates invalidate relevant keys.

---

## Phase 3: Inventory Core (Balances + Ledger + Receiving/Adjustments/Transfers/Cycle Count)

### 3.1 Inventory schema and invariants

- **Task 3.1: Create inventory_balances schema and constraints** [ ]

  - **Objective:** Implement per-branch, per-SKU inventory state.
  - **Files:**
    - backend/app/Database/Migrations/*_create_inventory_balances.php
  - **Action(s):**
    - Create inventory_balances with unique (branch_id, sku_id).
    - Columns: on_hand, reserved.
    - Enforce non-negative invariants at the application level; use DB constraints where appropriate.
  - **Verification/Deliverable(s):** A balance row is unique per branch+SKU; available is derived (on_hand - reserved).

- **Task 3.2: Create stock_movements append-only ledger schema** [ ]

  - **Objective:** Deliver ledger requirements and audit completeness.
  - **Files:**
    - backend/app/Database/Migrations/*_create_stock_movements.php
  - **Action(s):**
    - Create stock_movements table including:
      - branch_id, sku_id, qty_delta
      - movement_type
      - reference_type, reference_id
      - actor_id, created_at, reason
    - Add required indexes (branch_id, sku_id, created_at) and (reference_type, reference_id).
  - **Verification/Deliverable(s):** Ledger entries can be written and queried; updates/deletes are blocked by policy.

- **Task 3.3: Implement “prevent negative available” rule in services** [ ]

  - **Objective:** Deliver US-014.
  - **Files:**
    - backend/app/Modules/Inventory/Services/InventoryService.php
  - **Action(s):**
    - For any operation that reduces available stock, validate (on_hand - reserved) >= required.
    - If violation, reject transaction with a clear insufficient stock error.
  - **Verification/Deliverable(s):** Attempts to reduce below zero are rejected and leave no partial changes.

### 3.2 Inventory read APIs

- **Task 3.4: Implement inventory view endpoint (branch + SKU)** [ ]

  - **Objective:** Deliver US-010.
  - **Files:**
    - backend/app/Modules/Inventory/Controllers/InventoryController.php
    - backend/app/Modules/Inventory/Services/InventoryReadService.php
    - docs/api/README.md
  - **Action(s):**
    - Implement endpoint returning on_hand, reserved, available for a branch.
    - Add pagination/filtering by SKU.
    - Suggested endpoint: `GET /api/v1/branches/{branchId}/inventory?sku_id=&page=1&per_page=25`
  - **Verification/Deliverable(s):** Inventory views show correct quantities; response matches API envelopes.

- **Task 3.5: Add Redis caching for branch inventory snapshots** [ ]

  - **Objective:** Meet PRD caching guidance for high-traffic inventory screens.
  - **Files:**
    - backend/app/Modules/Inventory/Services/InventoryReadService.php (update)
  - **Action(s):**
    - Cache derived snapshots only.
    - TTL must be short; invalidate via outbox-driven events (Phase 6).
  - **Verification/Deliverable(s):** Cache reduces DB load; correctness remains governed by MySQL for write decisions.

### 3.3 Receiving and adjustments

- **Task 3.6: Implement receiving workflow and API** [ ]

  - **Objective:** Deliver US-011.
  - **Files:**
    - backend/app/Modules/Inventory/Controllers/ReceivingController.php
    - backend/app/Modules/Inventory/Services/ReceivingService.php
    - docs/api/README.md
  - **Action(s):**
    - Implement a receiving endpoint that increases on_hand.
    - Write stock_movements entries referencing receiving.
    - Update balances transactionally.
    - Suggested endpoint: `POST /api/v1/branches/{branchId}/receiving`
  - **Verification/Deliverable(s):** Receiving updates balances and creates ledger entries in one transaction.

- **Task 3.7: Implement stock adjustment workflow (reason required) and API** [ ]

  - **Objective:** Deliver US-012.
  - **Files:**
    - backend/app/Modules/Inventory/Controllers/AdjustmentsController.php
    - backend/app/Modules/Inventory/Services/AdjustmentService.php
    - docs/api/README.md
  - **Action(s):**
    - Implement adjustment endpoint requiring reason code/text.
    - Enforce RBAC.
    - Create ledger entry and audit record.
  - **Verification/Deliverable(s):** Adjustments require reason and permission; audit + ledger entries exist.

### 3.4 Transfers

- **Task 3.8: Implement transfer lifecycle and APIs** [ ]

  - **Objective:** Deliver US-013.
  - **Files:**
    - backend/app/Database/Migrations/*_create_transfers.php
    - backend/app/Database/Migrations/*_create_transfer_lines.php
    - backend/app/Modules/Inventory/Controllers/TransfersController.php
    - backend/app/Modules/Inventory/Services/TransferService.php
    - docs/api/README.md
  - **Action(s):**
    - Implement transfer create/execute flow producing outbound movement at source and inbound at destination.
    - Ensure references link outbound/inbound records.
    - Prevent negative on_hand at source.
    - Suggested endpoints:
      - `POST /api/v1/transfers` (create draft)
      - `POST /api/v1/transfers/{transferId}/execute`
  - **Verification/Deliverable(s):** Transfers are traceable and cannot overdraft source balances.

### 3.5 Cycle counts

- **Task 3.9: Implement cycle count recording and discrepancy adjustments** [ ]

  - **Objective:** Deliver US-034.
  - **Files:**
    - backend/app/Database/Migrations/*_create_cycle_counts.php
    - backend/app/Modules/Inventory/Controllers/CycleCountsController.php
    - backend/app/Modules/Inventory/Services/CycleCountService.php
    - docs/api/README.md
  - **Action(s):**
    - Create cycle count record table (counted quantity, timestamp, actor, notes).
    - Compute discrepancy and create a linked adjustment movement.
    - Audit the action.
    - Suggested endpoint: `POST /api/v1/branches/{branchId}/cycle-counts`
  - **Verification/Deliverable(s):** Cycle count captures required metadata; discrepancy creates a linked adjustment.

### 3.6 Ledger viewing

- **Task 3.10: Implement stock movement ledger query API** [ ]

  - **Objective:** Deliver US-015.
  - **Files:**
    - backend/app/Modules/Inventory/Controllers/LedgerController.php
    - backend/app/Modules/Inventory/Services/LedgerReadService.php
    - docs/api/README.md
  - **Action(s):**
    - Add list endpoint with filters: branch, SKU, date range, movement type.
    - Ensure entries show actor and references.
    - Suggested endpoint: `GET /api/v1/stock-movements?branch_id=&sku_id=&movement_type=&from=&to=&page=1&per_page=25`
  - **Verification/Deliverable(s):** Ledger is filterable and paginated; entries are immutable.

---

## Phase 4: Orders + Reservations + Returns + Idempotency

### 4.1 Schema

- **Task 4.1: Create orders and order_lines schema** [ ]

  - **Objective:** Support draft and lifecycle progression.
  - **Files:**
    - backend/app/Database/Migrations/*_create_orders.php
    - backend/app/Database/Migrations/*_create_order_lines.php
  - **Action(s):**
    - Create orders table with status and branch_id.
    - Create order_lines with sku_id, qty, pricing fields as needed.
    - Add index (branch_id, status, created_at).
  - **Commands:**
    - Migrate: `docker compose -f infra/docker-compose.yml exec app php spark migrate`
  - **Verification/Deliverable(s):** Orders can be stored in DRAFT state with validated line items.

- **Task 4.2: Create reservations schema (per order line) with expiry** [ ]

  - **Objective:** Support reservation on confirm and expiry (US-017, US-033).
  - **Files:**
    - backend/app/Database/Migrations/*_create_reservations.php
  - **Action(s):**
    - Create reservations table with branch_id, order_line_id, status, qty, expires_at.
    - Add index (branch_id, status, expires_at).
  - **Commands:**
    - Migrate: `docker compose -f infra/docker-compose.yml exec app php spark migrate`
  - **Verification/Deliverable(s):** Reservations can be created and expired; schema supports query by expires_at.

- **Task 4.3: Implement idempotency key storage** [ ]

  - **Objective:** Deliver US-022 for retryable APIs.
  - **Files:**
    - backend/app/Database/Migrations/*_create_idempotency_keys.php
    - backend/app/Modules/Shared/Utils/Idempotency.php (optional helper)
  - **Action(s):**
    - Create idempotency_keys table storing:
      - key
      - user_id
      - endpoint
      - request hash
      - response snapshot / resulting resource id
      - created_at, expires_at
  - **Commands:**
    - Migrate: `docker compose -f infra/docker-compose.yml exec app php spark migrate`
  - **Verification/Deliverable(s):**
    - Replays with the same `Idempotency-Key` return the same outcome without duplicates.
    - Negative case: same `Idempotency-Key` with a different request body returns 409 with a safe error code (e.g., `IDEMPOTENCY_KEY_REUSE`).

### 4.2 Order endpoints and business flows

- **Task 4.4: Implement create draft order API** [ ]

  - **Objective:** Deliver US-016.
  - **Files:**
    - backend/app/Modules/Orders/Controllers/OrdersController.php
    - backend/app/Modules/Orders/Services/OrderDraftService.php
    - docs/api/README.md
  - **Action(s):**
    - Create draft order endpoint.
    - Validate SKU existence and qty > 0.
    - Suggested endpoint: `POST /api/v1/orders` (creates DRAFT)
  - **Commands:**
    - Create (example): `curl -X POST http://localhost:8080/api/v1/orders -H "Content-Type: application/json" -H "Idempotency-Key: demo-1" -d '{"branch_id":1,"lines":[{"sku_id":1,"qty":2}]}'`
  - **Verification/Deliverable(s):**
    - Draft orders can be created and return an order id + `status=DRAFT`.
    - Negative cases:
      - qty <= 0 returns a validation error using the standard error schema.
      - unknown sku_id returns a validation error using the standard error schema.

- **Task 4.5: Implement edit draft order API** [ ]

  - **Objective:** Deliver US-032.
  - **Files:**
    - backend/app/Modules/Orders/Controllers/OrdersController.php
    - backend/app/Modules/Orders/Services/OrderDraftService.php
    - docs/api/README.md
  - **Action(s):**
    - Allow add/remove/change quantities in DRAFT.
    - Prevent edits that change confirmed reservations without explicit reconfirm flow.
    - Suggested endpoint: `PATCH /api/v1/orders/{orderId}` (only when DRAFT)
  - **Commands:**
    - Edit (example): `curl -X PATCH http://localhost:8080/api/v1/orders/1 -H "Content-Type: application/json" -H "Idempotency-Key: demo-2" -d '{"lines":[{"sku_id":1,"qty":3}]}'`
  - **Verification/Deliverable(s):**
    - Draft edits do not affect inventory balances.
    - Negative case: editing a non-DRAFT order returns 409 (safe code like `ORDER_NOT_EDITABLE`).

- **Task 4.6: Implement confirm order (reserve stock) with transactions and row locks** [ ]

  - **Objective:** Deliver US-017 and US-018 (concurrency safety).
  - **Files:**
    - backend/app/Modules/Orders/Controllers/OrderConfirmController.php (or OrdersController::confirm)
    - backend/app/Modules/Orders/Services/OrderReservationService.php
    - backend/app/Modules/Inventory/Services/InventoryService.php (row lock + balance updates)
    - docs/api/README.md
  - **Action(s):**
    - In a single transaction:
      - lock inventory_balances rows (branch_id, sku_id)
      - validate available
      - increase reserved
      - create reservations
      - create stock_movements entries (reservation)
      - update order status
    - Ensure failure leaves no partial reservations.
    - Suggested endpoint: `POST /api/v1/orders/{orderId}/confirm`
  - **Commands:**
    - Confirm (example): `curl -X POST http://localhost:8080/api/v1/orders/1/confirm -H "Idempotency-Key: demo-3"`
  - **Verification/Deliverable(s):**
    - Confirm moves order to `CONFIRMED` and increments `inventory_balances.reserved`.
    - Negative case: insufficient available stock returns 409 with a safe code (e.g., `INSUFFICIENT_STOCK`) and does not create partial reservations.
    - Concurrency check (manual): attempt two confirms in parallel against the same order/SKU/branch; exactly one should succeed and balances remain consistent.

- **Task 4.7: Implement fulfill order (deduct stock) flow** [ ]

  - **Objective:** Deliver US-019.
  - **Files:**
    - backend/app/Modules/Orders/Controllers/OrderFulfillController.php (or OrdersController::fulfill)
    - backend/app/Modules/Orders/Services/OrderFulfillmentService.php
    - docs/api/README.md
  - **Action(s):**
    - Deduct on_hand and reduce reserved accordingly.
    - Create ledger entries referencing the order.
  - **Commands:**
    - Fulfill (example): `curl -X POST http://localhost:8080/api/v1/orders/1/fulfill -H "Idempotency-Key: demo-4"`
  - **Verification/Deliverable(s):**
    - Fulfillment updates balances correctly: `on_hand` decreases and `reserved` decreases by the fulfilled qty.
    - Negative case: fulfilling a non-CONFIRMED order returns 409 (safe code like `ORDER_NOT_FULFILLABLE`).

- **Task 4.8: Implement cancel order (release reservations) flow** [ ]

  - **Objective:** Deliver US-020.
  - **Files:**
    - backend/app/Modules/Orders/Controllers/OrderCancelController.php (or OrdersController::cancel)
    - backend/app/Modules/Orders/Services/OrderCancellationService.php
    - docs/api/README.md
  - **Action(s):**
    - Release reserved stock transactionally.
    - Audit cancellation.
  - **Commands:**
    - Cancel (example): `curl -X POST http://localhost:8080/api/v1/orders/1/cancel -H "Idempotency-Key: demo-5"`
  - **Verification/Deliverable(s):**
    - Reserved decreases; available increases; audit log exists.
    - Negative case: canceling a fulfilled order returns 409 (safe code like `ORDER_NOT_CANCELLABLE`).

- **Task 4.9: Implement returns processing flow** [ ]

  - **Objective:** Deliver US-021.
  - **Files:**
    - backend/app/Modules/Orders/Controllers/ReturnsController.php
    - backend/app/Modules/Orders/Services/ReturnsService.php
    - docs/api/README.md
  - **Action(s):**
    - Validate return qty <= fulfilled qty per order line.
    - Increase on_hand and write ledger entries referencing original order.
  - **Commands:**
    - Return (example): `curl -X POST http://localhost:8080/api/v1/orders/1/returns -H "Content-Type: application/json" -H "Idempotency-Key: demo-6" -d '{"lines":[{"order_line_id":1,"qty":1}]}'`
  - **Verification/Deliverable(s):**
    - Returns cannot exceed fulfilled quantities; ledger entries exist.
    - Negative case: returning more than fulfilled returns a validation error (safe code like `RETURN_EXCEEDS_FULFILLED`).

### 4.3 Reservation expiry

- **Task 4.10: Implement reservation expiry worker/job** [ ]

  - **Objective:** Deliver US-033.
  - **Files:**
    - backend/app/Commands/ReservationsExpireCommand.php
    - backend/app/Modules/Orders/Services/ReservationExpiryService.php
    - docs/runbooks/local-dev.md (how to run locally)
  - **Action(s):**
    - Implement a scheduled job to release expired reservations.
    - Record expiry for audit/troubleshooting.
  - **Commands:**
    - Manual run (dev): `docker compose -f infra/docker-compose.yml exec app php spark reservations:expire`
  - **Troubleshooting:**
    - If nothing expires when expected: verify server timezone vs `expires_at` timezone and confirm the job compares using a consistent time source.
  - **Verification/Deliverable(s):**
    - Expired reservations are released without inconsistencies.
    - Negative case: running the command when no reservations are expired makes no changes (idempotent, safe to re-run).

### 4.4 Branch deactivation enforcement

- **Task 4.11: Enforce “no new actions on deactivated branches” across modules** [ ]

  - **Objective:** Deliver US-027.
  - **Files:**
    - backend/app/Modules/Branches/Services/BranchStatusService.php (optional central check)
    - backend/app/Modules/*/Services/* (add checks on write paths)
  - **Action(s):**
    - Add branch active checks to:
      - order create/confirm/fulfill/cancel
      - receiving/adjustment/transfer/returns
    - Ensure reads remain available for historical data.
  - **Commands:**
    - Deactivate branch (example): `curl -X POST http://localhost:8080/api/v1/branches/1/deactivate`
    - Negative check (example): `curl -X POST http://localhost:8080/api/v1/orders -H "Content-Type: application/json" -H "Idempotency-Key: demo-branch-inactive" -d '{"branch_id":1,"lines":[{"sku_id":1,"qty":1}]}'`
  - **Verification/Deliverable(s):**
    - Writes are rejected for deactivated branches with a 409-style safe error (e.g., `BRANCH_INACTIVE`); reads still work.


---

## Phase 5: Reporting + Dashboards (Cache + Async Aggregation)

### 5.1 Reporting read models and endpoints

- **Task 5.1: Define reporting read model queries** [ ]

  - **Objective:** Support dashboards for stockout risk, throughput, and SLA indicators.
  - **Files:**
    - backend/app/Modules/Reporting/Queries/*
  - **Action(s):**
    - Implement reporting queries using optimized SQL and appropriate indexes.
  - **Verification/Deliverable(s):**
    - Queries return correct data on realistic datasets.
    - Negative case: queries must not scan unbounded tables for default dashboard views (add indexes or bounded filters as needed).

- **Task 5.2: Implement operational dashboard API endpoints** [ ]

  - **Objective:** Deliver US-023.
  - **Files:**
    - backend/app/Modules/Reporting/Controllers/DashboardController.php
    - docs/api/README.md
  - **Action(s):**
    - Create endpoints for required KPI dashboards.
    - Apply caching strategy (Task 5.3).
    - Suggested endpoint: `GET /api/v1/reporting/dashboard?branch_id={branchId}&from=YYYY-MM-DD&to=YYYY-MM-DD`
  - **Commands:**
    - `curl "http://localhost:8080/api/v1/reporting/dashboard?branch_id=1&from=2026-01-01&to=2026-01-31"`
  - **Verification/Deliverable(s):**
    - Dashboards respond within targets under load when cached.
    - Negative case: invalid/missing date range returns a validation error using the standard error schema.

- **Task 5.3: Cache dashboard KPIs in Redis with TTL + event-driven invalidation** [ ]

  - **Objective:** Meet PRD caching guidance for dashboards.
  - **Files:**
    - backend/app/Modules/Reporting/Services/DashboardService.php
  - **Action(s):**
    - Implement bounded cache keys and TTL.
    - Invalidate/update via outbox-driven events (Phase 6).
  - **Verification/Deliverable(s):**
    - Repeated KPI calls with the same parameters hit cache (validated via logs/metrics).
    - Negative case: cache keys are bounded (do not create a new key for every arbitrary filter combination).

- **Task 5.4: Implement branch comparisons endpoint** [ ]

  - **Objective:** Deliver US-024.
  - **Files:**
    - backend/app/Modules/Reporting/Controllers/BranchComparisonController.php
    - backend/app/Modules/Reporting/Queries/BranchComparisonQuery.php
    - docs/api/README.md
  - **Action(s):**
    - Provide date range selection.
    - Paginate results.
    - Suggested endpoint: `GET /api/v1/reporting/branch-comparisons?from=YYYY-MM-DD&to=YYYY-MM-DD&page=1&per_page=25`
  - **Commands:**
    - `curl "http://localhost:8080/api/v1/reporting/branch-comparisons?from=2026-01-01&to=2026-01-31&page=1&per_page=25"`
  - **Verification/Deliverable(s):** Branch comparisons return paginated data over date ranges.

### 5.2 Async aggregation

- **Task 5.5: Implement async report aggregation jobs** [ ]

  - **Objective:** Deliver US-025.
  - **Files:**
    - backend/app/Commands/ReportingAggregateCommand.php (or queue job handler)
    - backend/app/Modules/Reporting/Services/ReportingAggregationService.php
  - **Action(s):**
    - Create queue job(s) for heavy aggregation.
    - Implement retries and logging.
  - **Commands:**
    - Manual run (dev): `docker compose -f infra/docker-compose.yml exec app php spark reporting:aggregate`
  - **Verification/Deliverable(s):** Aggregations run via workers; failures are retried and logged.

---

## Phase 6: Events + Transactional Outbox + Workers

### 6.1 Outbox schema

- **Task 6.1: Create outbox_events table and indexes** [ ]

  - **Objective:** Support reliable async processing.
  - **Files:**
    - backend/app/Database/Migrations/*_create_outbox_events.php
  - **Action(s):**
    - Add outbox_events table with (minimum):
      - id (pk)
      - event_type
      - payload_json
      - status (e.g., pending|processing|succeeded|failed)
      - available_at (for backoff)
      - attempts
      - locked_at (nullable)
      - locked_by (nullable; worker id)
      - last_error (nullable)
      - created_at
    - Add indexes for:
      - (status, available_at)
      - (locked_at)
      - (event_type, created_at)
  - **Commands:**
    - Migrate: `docker compose -f infra/docker-compose.yml exec app php spark migrate`
  - **Verification/Deliverable(s):** Outbox events can be inserted in the same DB transaction as business writes.

### 6.2 Event publishing and worker loops

- **Task 6.2: Implement outbox write helpers in service layer** [ ]

  - **Objective:** Standardize event writing for inventory/orders/catalog updates.
  - **Files:**
    - backend/app/Modules/Shared/Utils/OutboxWriter.php
  - **Action(s):**
    - Add a shared helper for writing outbox events during transactions.
  - **Verification/Deliverable(s):**
    - Key workflows write outbox events on success.
    - Negative case: if the business transaction rolls back, no outbox event is persisted.

- **Task 6.3: Implement outbox worker process** [ ]

  - **Objective:** Reliably publish/dispatch events for async work.
  - **Files:**
    - backend/app/Commands/OutboxWorkerCommand.php
    - backend/app/Modules/Shared/Services/OutboxWorker.php
    - backend/app/Modules/Shared/Services/OutboxRepository.php
    - docs/runbooks/local-dev.md
  - **Action(s):**
    - Implement worker that polls outbox_events, claims rows, dispatches jobs, updates status, and retries with backoff.
  - **Commands:**
    - Manual run (dev, once): `docker compose -f infra/docker-compose.yml exec app php spark outbox:work --once`
    - Manual run (dev, loop): `docker compose -f infra/docker-compose.yml exec app php spark outbox:work`
  - **Troubleshooting:**
    - If the same event is processed twice: verify the claim step uses a DB-level lock/atomic update (e.g., update where status=pending and locked_at is null).
  - **Verification/Deliverable(s):**
    - Worker processes events without duplication.
    - Negative case: if a handler throws, `attempts` increments and the row is rescheduled via `available_at` backoff with `last_error` recorded.

- **Task 6.4: Implement queue worker(s) for cache invalidation, reporting, reconciliation** [ ]

  - **Objective:** Run heavy side effects off the request path.
  - **Files:**
    - backend/app/Commands/QueueWorkerCommand.php
    - backend/app/Modules/Shared/Services/Queue/* (job handlers)
  - **Action(s):**
    - Implement job handlers:
      - invalidate product search caches on product/SKU changes
      - invalidate inventory snapshot caches on stock-affecting movements
      - update/refresh reporting aggregates
  - **Commands:**
    - Manual run (dev): `docker compose -f infra/docker-compose.yml exec app php spark queue:work --once`
  - **Verification/Deliverable(s):**
    - Side effects run asynchronously; API latency is protected.
    - Negative case: handler failures do not break the originating API request; failures are visible in logs and retried.

---

## Phase 7: Frontend (Vue 3 SPA)

### 7.1 Frontend foundation

- **Task 7.1: Initialize Vue 3 SPA structure matching rules/system.md** [ ]

  - **Objective:** Establish frontend folders and module boundaries.
  - **Files:**
    - frontend/package.json
    - frontend/src/main.ts
  - **Action(s):** Create frontend/src structure:
    - api/
    - app/router/
    - app/store/
    - modules/(auth, branches, catalog, inventory, orders, reporting)
    - components/, pages/, styles/, main.ts
  - **Commands:**
    - Install: `cd frontend; npm ci`
    - Run: `cd frontend; npm run dev`
  - **Verification/Deliverable(s):** App boots locally and shows a placeholder home page.

- **Task 7.2: Implement API client with auth + error mapping** [ ]

  - **Objective:** Ensure consistent handling of auth tokens and backend error schema.
  - **Files:**
    - frontend/src/api/client.ts
    - frontend/src/api/types.ts
  - **Action(s):**
    - Build a centralized API client that:
      - attaches auth token
      - maps backend errors to a standard UI error object
      - propagates request_id for troubleshooting
  - **Verification/Deliverable(s):**
    - UI can display permission-denied and validation errors consistently (US-026).
    - Negative case: network failures show a safe retryable message (no raw stack traces).

### 7.2 Auth and access control

- **Task 7.3: Implement login/logout UI flows** [ ]

  - **Objective:** Provide UI for US-001/US-002.
  - **Files:**
    - frontend/src/modules/auth/*
    - frontend/src/app/router/* (route + guard)
  - **Action(s):**
    - Create login page.
    - Implement logout action.
  - **Commands:**
    - Run: `cd frontend; npm run dev`
  - **Verification/Deliverable(s):** Successful login navigates to authorized app; logout returns to login.

- **Task 7.4: Implement permission-denied UI handling** [ ]

  - **Objective:** Deliver US-026.
  - **Files:**
    - frontend/src/api/client.ts (403 mapping)
    - frontend/src/components/* (error banner/toast)
  - **Action(s):**
    - For 403 responses, show a non-sensitive message and guidance.
  - **Verification/Deliverable(s):**
    - Permission errors render clearly without exposing internals.
    - Negative case: backend 403 does not cause a full-page crash (renders a recoverable state).

### 7.3 Core modules UI

- **Task 7.5: Branch management UI** [ ]

  - **Objective:** Support branch CRUD and deactivation.
  - **Files:**
    - frontend/src/modules/branches/*
  - **Action(s):**
    - Create branch list and create/edit forms.
    - Indicate deactivated state.
  - **Verification/Deliverable(s):**
    - Branches can be created/edited/deactivated via UI (subject to RBAC).
    - Negative case: users lacking permission see a non-sensitive access denied message (no hidden “successful” UI state).

- **Task 7.6: Catalog UI (search + SKU management)** [ ]

  - **Objective:** Support US-007/US-008/US-009.
  - **Files:**
    - frontend/src/modules/catalog/*
  - **Action(s):**
    - Create catalog search page with pagination.
    - Create SKU create/edit forms.
  - **Verification/Deliverable(s):**
    - Duplicate SKU/barcode errors show as validation errors.
    - Negative case: server-side validation errors map to field-level messages consistently.

- **Task 7.7: Inventory UI (branch inventory view, receiving, adjustments, transfers, cycle counts)** [ ]

  - **Objective:** Support inventory workflows.
  - **Files:**
    - frontend/src/modules/inventory/*
  - **Action(s):**
    - Inventory snapshot page (on_hand, reserved, available).
    - Receiving form.
    - Adjustment form (reason required).
    - Transfer form.
    - Cycle count form.
  - **Verification/Deliverable(s):**
    - All inventory actions reflect backend authorization and show consistent errors.
    - Negative case: insufficient stock errors (409-style) display clearly and do not leave the UI in a “success” state.

- **Task 7.8: Orders UI (draft/create/confirm/fulfill/cancel/returns)** [ ]

  - **Objective:** Support order lifecycle and idempotent-safe retries.
  - **Files:**
    - frontend/src/modules/orders/*
  - **Action(s):**
    - Draft order create/edit page.
    - Confirm flow that displays insufficient stock errors.
    - Fulfill/cancel actions.
    - Returns form.
  - **Verification/Deliverable(s):**
    - UI can execute full order lifecycle.
    - Negative case: concurrency/insufficient stock errors display clearly and the user can retry safely.

- **Task 7.9: Reporting UI (dashboards + comparisons)** [ ]

  - **Objective:** Support US-023/US-024.
  - **Files:**
    - frontend/src/modules/reporting/*
  - **Action(s):**
    - Dashboard page (cached KPIs).
    - Branch comparisons page with date range selection.
  - **Verification/Deliverable(s):**
    - Dashboards load fast and show last-updated/freshness where available.
    - Negative case: if reporting endpoints error, UI shows a safe failure state with `request_id` surfaced for support.

---

## Phase 8: Testing, Load Testing, and Operational Readiness

### 8.1 PHPUnit tests

- **Task 8.1: Add unit tests for inventory invariants and calculations** [ ]

  - **Objective:** Prevent regressions in correctness-critical logic.
  - **Files:**
    - backend/tests/unit/*
  - **Action(s):**
    - Test available calculation and negative-prevention rule.
    - Test ledger immutability policy.
  - **Commands:**
    - `docker compose -f infra/docker-compose.yml exec app vendor/bin/phpunit --testsuite unit`
  - **Verification/Deliverable(s):** Unit tests pass and cover core invariants.

- **Task 8.2: Add feature tests for RBAC and critical workflows** [ ]

  - **Objective:** Validate end-to-end correctness.
  - **Files:**
    - backend/tests/feature/*
  - **Action(s):**
    - RBAC denial tests (US-003, US-026).
    - Confirm order reserve flow (US-017/US-018).
    - Fulfill, cancel, return flows (US-019/US-020/US-021).
    - Reservation expiry flow (US-033).
  - **Commands:**
    - `docker compose -f infra/docker-compose.yml exec app vendor/bin/phpunit --testsuite feature`
  - **Verification/Deliverable(s):** Feature tests reliably validate no partial reservations and correct balances.

### 8.2 JMeter load tests

- **Task 8.3: Create JMeter scenarios for concurrency and read-heavy endpoints** [ ]

  - **Objective:** Validate performance targets and concurrency correctness.
  - **Files:**
    - load-test/jmeter/scenarios/
    - load-test/jmeter/results/
  - **Action(s):**
    - Concurrent confirm order against same SKU/branch.
    - Dashboard reads (cached) and inventory reads.
    - Mixed read/write workload at 1000+ concurrent users.
  - **Commands:**
    - Run headless (example): `jmeter -n -t load-test/jmeter/scenarios/concurrency-confirm.jmx -l load-test/jmeter/results/concurrency-confirm.jtl`
  - **Verification/Deliverable(s):**
    - JMeter scripts exist and can be run consistently; results are recorded.
    - Negative case: concurrency-confirm scenario demonstrates that oversubscription fails safely (no negative available).

### 8.3 Operational docs

- **Task 8.4: Create deployment workflow documentation** [ ]

  - **Objective:** Provide a clear path to pilot rollout.
  - **Files:**
    - docs/runbooks/deployment.md
  - **Action(s):**
    - Document build images -> run migrations -> deploy API -> deploy workers -> monitor outbox backlog.
  - **Verification/Deliverable(s):**
    - A deployment runbook exists and includes rollback guidance.
    - It includes a post-deploy validation checklist (health endpoint, migrations status, worker running, outbox backlog stable).

- **Task 8.5: Create outage runbooks for MySQL/Redis/workers** [ ]

  - **Objective:** Reduce downtime risk.
  - **Files:**
    - docs/runbooks/outages.md
  - **Action(s):**
    - Document symptoms and recovery steps for:
      - Redis unavailable
      - outbox backlog growing
      - worker failures
      - MySQL connectivity issues
  - **Verification/Deliverable(s):** Runbooks exist and are actionable.

---

**Conclusion:** Completing these phases delivers the PRD scope (US-001 through US-035) with the architecture and system patterns defined in rules/system.md, including correctness under concurrency, caching for read-heavy endpoints, reliable outbox-driven async processing, and load-test validation.

---

## Appendix: Canonical Reference Snippets (Copy/Paste Ready)

These snippets are intentionally minimal but complete. Tasks may reference them as the baseline implementation.

### A1) infra/docker-compose.yml (mysql + redis + app + worker)

```yaml
services:
  mysql:
    image: mysql:8.0
    command: ["--default-authentication-plugin=mysql_native_password"]
    environment:
      MYSQL_DATABASE: ${MYSQL_DATABASE:-inventory}
      MYSQL_USER: ${MYSQL_USER:-inventory}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:-inventory}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-root}
    ports:
      - "${MYSQL_PUBLISHED_PORT:-3306}:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-p${MYSQL_ROOT_PASSWORD:-root}"]
      interval: 10s
      timeout: 5s
      retries: 10

  redis:
    image: redis:7-alpine
    ports:
      - "${REDIS_PUBLISHED_PORT:-6379}:6379"
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 10

  app:
    image: php:8.2-apache
    working_dir: /var/www/html
    volumes:
      - ../backend:/var/www/html
    environment:
      APP_ENV: ${APP_ENV:-development}
      APP_BASE_URL: ${APP_BASE_URL:-http://localhost:8080}
      MYSQL_HOST: ${MYSQL_HOST:-mysql}
      MYSQL_PORT: ${MYSQL_PORT:-3306}
      MYSQL_DATABASE: ${MYSQL_DATABASE:-inventory}
      MYSQL_USER: ${MYSQL_USER:-inventory}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:-inventory}
      REDIS_HOST: ${REDIS_HOST:-redis}
      REDIS_PORT: ${REDIS_PORT:-6379}
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    ports:
      - "${APP_PUBLISHED_PORT:-8080}:80"

  worker:
    image: php:8.2-cli
    working_dir: /var/www/html
    volumes:
      - ../backend:/var/www/html
    environment:
      APP_ENV: ${APP_ENV:-development}
      MYSQL_HOST: ${MYSQL_HOST:-mysql}
      MYSQL_PORT: ${MYSQL_PORT:-3306}
      MYSQL_DATABASE: ${MYSQL_DATABASE:-inventory}
      MYSQL_USER: ${MYSQL_USER:-inventory}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:-inventory}
      REDIS_HOST: ${REDIS_HOST:-redis}
      REDIS_PORT: ${REDIS_PORT:-6379}
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    # Placeholder until Phase 6 defines the real worker command.
    command: ["php", "-r", "while (true) { sleep(60); }"]

volumes:
  mysql_data:
```

### A2) backend/app/Config/Routes.php (/api/v1 group + health)

```php
<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->group('api/v1', ['namespace' => 'App\\Controllers'], static function ($routes) {
    $routes->get('health', 'HealthController::index');
});
```

### A3) backend/app/Controllers/HealthController.php

```php
<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

final class HealthController extends BaseController
{
    public function index(): ResponseInterface
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? $this->request->getHeaderLine('X-Request-Id');

        return $this->response->setJSON([
            'status' => 'ok',
            'request_id' => (string) $requestId,
        ]);
    }
}
```

### A4) backend/app/Filters/RequestIdFilter.php (generate/propagate request_id)

```php
<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class RequestIdFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $requestId = $request->getHeaderLine('X-Request-Id');
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(16));
        }

        // CI4 request objects are not reliably mutable here; store in process globals
        // so controllers/services can read it consistently.
        $_SERVER['HTTP_X_REQUEST_ID'] = $requestId;

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? $request->getHeaderLine('X-Request-Id');
        if ($requestId !== '') {
            $response->setHeader('X-Request-Id', $requestId);
        }

        return $response;
    }
}
```

### A5) docs/api/README.md skeleton

````md
# API v1

Base URL: `${APP_BASE_URL}/api/v1`

## Auth
- Decision: JWT or session cookies (see docs/architecture/auth.md)
- Request header (JWT): `Authorization: Bearer <token>`

## List envelope
Response:
```json
{
  "data": [],
  "pagination": { "page": 1, "per_page": 25, "total": 0, "total_pages": 0 },
  "request_id": "..."
}
```

## Error schema
```json
{ "code": "VALIDATION_ERROR", "message": "...", "details": {}, "request_id": "..." }
```
````

### A6) .editorconfig (baseline)

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true

[*.md]
trim_trailing_whitespace = false

[*.php]
indent_style = space
indent_size = 4

[*.{ts,tsx,js,vue,json,yml,yaml}]
indent_style = space
indent_size = 2
```

### A7) .gitignore (baseline secrets + vendor/node)

```gitignore
.env
.env.*

backend/vendor/
frontend/node_modules/

backend/writable/
load-test/jmeter/results/
```

### A8) backend/app/Config/Filters.php (register RequestIdFilter)

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class Filters extends BaseConfig
{
    public array $aliases = [
        'requestid' => \App\Filters\RequestIdFilter::class,
    ];

    public array $globals = [
        'before' => [
            'requestid',
        ],
        'after' => [],
    ];
}
```

### A9) backend/app/Modules/Shared/Utils/ApiResponder.php + Pagination.php

```php
<?php

namespace App\Modules\Shared\Utils;

use CodeIgniter\HTTP\ResponseInterface;

final class ApiResponder
{
  public static function ok(ResponseInterface $response, array $data, int $statusCode = 200): ResponseInterface
  {
    return $response->setStatusCode($statusCode)->setJSON($data);
  }

  public static function error(ResponseInterface $response, string $code, string $message, array $details = [], int $statusCode = 400): ResponseInterface
  {
    return $response->setStatusCode($statusCode)->setJSON([
      'code' => $code,
      'message' => $message,
      'details' => $details,
      'request_id' => (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''),
    ]);
  }

  public static function list(ResponseInterface $response, array $items, int $page, int $perPage, int $total): ResponseInterface
  {
    $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

    return self::ok($response, [
      'data' => $items,
      'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
      ],
      'request_id' => (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''),
    ]);
  }
}
```

```php
<?php

namespace App\Modules\Shared\Utils;

use CodeIgniter\HTTP\RequestInterface;

final class Pagination
{
  public static function page(RequestInterface $request): int
  {
    return max(1, (int) ($request->getGet('page') ?? 1));
  }

  public static function perPage(RequestInterface $request, int $default = 25, int $max = 100): int
  {
    $perPage = (int) ($request->getGet('per_page') ?? $default);
    return min($max, max(1, $perPage));
  }
}
```

