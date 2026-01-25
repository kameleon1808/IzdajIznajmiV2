# Repository Guidelines

## Project Structure & Module Organization
- `frontend/` holds the Vue 3 + Vite SPA (`src/`, `tests/*.spec.ts`, `e2e/`, configs); `backend/` contains the Laravel 12 API, migrations, policies, and PHPUnit suites.
- `docs/` stores API contracts, QA/UAT plans, and deployment instructions; `ops/` provides the deploy/rollback shell helpers plus cron/nginx/supervisor snippets.
- Legacy Laravel folders (`app/`, `database/`) support the API, and `README.md` plus `docs/dev-setup.md` are the primary onboarding guides.

## Build, Test, and Development Commands
- Backend bootstrap: `cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate:fresh --seed`; run `php artisan storage:link`, `php artisan queue:work`, and `php artisan schedule:work` during dev to keep media jobs and auto-expiry working.
- Frontend dev: `cd frontend && npm install && cp .env.example .env && npm run dev -- --host --port 5173`; use `VITE_USE_MOCK_API=true` for mock mode.
- Build/test: `npm run build` (TS and Vite), `npm run test` (Vitest units), and `npm run test:e2e` (mock build + Playwright smoke; run `npx playwright install --with-deps chromium` once).

## Coding Style & Naming Conventions
- Frontend: 2-space indentation, PascalCase Vue components, camelCase stores/services, and kebab-case filenames/routes.
- Backend: PSR-12 with 4-space indentation, descriptive class names (controllers, policies, resources), and `/api/v1` endpoints guarded by role/rate limit middleware described in `AppServiceProvider`.
- Verify TypeScript changes via `npm run build` and PHP changes via `php artisan test` (or `vendor/bin/phpunit`) before committing.

## Testing Guidelines
- Backend specs live in `backend/tests/Unit` and `backend/tests/Feature`; name files with `*Test.php` and keep domain logic in features/policies.
- Frontend unit specs sit in `frontend/tests/*.spec.ts`, Playwright smoke lives in `frontend/e2e/smoke.spec.ts`; run `npm run test` for logic and `npm run test:e2e` for browser coverage.
- Update `docs/test-plan-sr.md` or `docs/uat-test-plan-sr.md` when you change flows that QA or UAT rely on.

## Commit & Pull Request Guidelines
- Keep commits imperative (“Add health endpoints”, “Fix filter modal z-index”), mention the area touched (backend/frontend/docs/ops), and note the commands you ran; link related issues when possible.
- PRs require a clear summary, testing notes, linked issue/story, and any UI screenshots or contract changes.

## Environment & Ops Notes
- Mirror the sample env files (`frontend/.env.example`, `backend/.env.example`) and keep values for `SANCTUM_STATEFUL_DOMAINS`, queue driver, image options, and CORS aligned with `docs/dev-setup.md`.
- Use the scripts in `ops/` for deploy/rollback, and update `docs/deploy/DEPLOYMENT.md` whenever SSH, cron, or nginx helpers change; GitHub workflows live under `.github/workflows/deploy-*.yml`.
