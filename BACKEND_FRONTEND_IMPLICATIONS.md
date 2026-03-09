# Backend Analysis and Front-End Implications

## 1) What backend stack and API style you are getting

- Backend is Laravel 11 with JSON REST endpoints under `/api` and a custom `admin.auth` middleware for protected routes.
- Route groups are organized by module: auth, customers, vehicles, services/pricing, job orders/items, and daily reports.
- Most successful responses use a consistent envelope: `success`, `message` (optional), `data`.

Front-end implication:
- Build a shared API client that always expects JSON and standardizes handling of `success`, `message`, and `errors`.

## 2) Authentication model and SPA implications

- Login/logout/me endpoints exist (`/api/auth/login`, `/api/auth/logout`, `/api/auth/me`), and auth is session-based (`Auth::attempt` + session regenerate).
- Protected modules are behind `admin.auth` middleware.

Front-end implication:
- Your frontend should send credentials and keep cookies (`withCredentials: true` in axios/fetch equivalent) if your frontend and backend are different origins.
- Because these routes are declared in API routing (not web routing), validate early whether session persistence works end-to-end in your environment before building all pages.

## 3) Core entity design and UI modules to mirror

- Entities match your proposal: customers, vehicles, services, service pricing, job orders, and job order items.
- Vehicles enforce category/size behavior: `CAR` requires size; `MOTOR` size becomes `null`.
- Job orders store operational fields needed by UI: payment mode, washboy name, leave-vehicle + waiver metadata, and workflow status.

Front-end implication:
- Your sitemap can cleanly map to backend modules:
  1. Login
  2. Customer management
  3. Vehicle management
  4. Services & pricing
  5. Tablet check-in / create job order
  6. Job order detail + quote updates
  7. Daily report

## 4) Business rules already enforced server-side (good for front-end guardrails)

The backend enforces selection rules in `JobOrderService`:
- MOTOR: max one `MOTOR_MAIN`; car groups (`PACKAGE`, `ADDON`, `BUNDLE`) are forbidden.
- CAR: max one `PACKAGE`; `BUNDLE` (Complete) is exclusive versus package/add-on; motor-main forbidden.
- Service-category compatibility (`CAR`, `MOTOR`, `BOTH`) is validated.

Front-end implication:
- Implement the same rules in UI for better UX (disable/hide illegal combinations), but still rely on backend as source of truth.
- Always render validation errors from server (422), because backend can still reject payloads.

## 5) Pricing, totals, and TBA flow (important for order screens)

- Catalog service items auto-resolve price from `service_pricing` using vehicle size/category.
- Custom items support `TBA`, `FIXED`, or `QUOTED` states.
- Totals are computed dynamically from non-null `unit_price` values (TBA rows excluded until quoted).
- Daily report and job order detail expose `has_tba` and totals expected for dashboard/reporting UIs.

Front-end implication:
- Show “partial total” while TBA exists, with an explicit TBA badge/count.
- Keep custom-item editor capable of two modes: immediate fixed amount or pending quote.

## 6) Data/validation details your forms should match exactly

- Enumerations you should centralize in frontend constants:
  - `vehicle_category`: `CAR`, `MOTOR`
  - `vehicle_size`: `SMALL`, `MEDIUM`, `LARGE`, `XL`
  - `service_group`: `PACKAGE`, `ADDON`, `BUNDLE`, `MOTOR_MAIN`, `OTHER`
  - `payment_mode`: `CASH`, `GCASH`, `CARD`, `UNPAID`
  - `job status`: `OPEN`, `IN_PROGRESS`, `DONE`, `CANCELLED`
- Many list endpoints are paginated (`paginate(20)`), while service listing is not paginated.

Front-end implication:
- Use reusable table/list pagination components for customers, vehicles, and job orders.
- Service list screens can load all rows at once (current backend behavior).

## 7) Gaps and quirks you should account for before final front-end integration

1. **Potential auth/session integration risk on API routes**
   - Auth uses sessions in API endpoints. Confirm your deployment/CORS/cookie settings early.

2. **`add item` rule-check is per new item, not whole order context**
   - `POST /job-orders/{id}/items` validates only the incoming item against rules, not against existing selected service items.
   - Possible result: invalid combinations can slip in through repeated add-item calls.

3. **Waiver timestamp strictness differs between create vs update**
   - Create requires `waiver_accepted_at` when `leave_vehicle` + `waiver_accepted` is true.
   - Update enforces waiver acceptance, but does not strictly require timestamp in the same way.

4. **Daily report includes all non-cancelled statuses**
   - Report query excludes only `CANCELLED`, so `OPEN` and `IN_PROGRESS` orders can be included in totals.

Front-end implication:
- Decide with your team if this behavior is intended; if not, backend should be adjusted before you lock report UI assumptions.

## 8) Recommended front-end implementation order (low rework path)

1. Build API client layer + global error handler (401/403/422/409 patterns).
2. Implement login/session verification page flow first.
3. Build customer/vehicle CRUD with search + pagination.
4. Build services/pricing fetch and quote preview integration.
5. Build tablet check-in with UI-side service rule engine mirroring backend.
6. Build job order detail with add item + quote TBA flow.
7. Build daily report view with payment breakdown cards and table.

## 9) Practical contract checklist for your team handoff

- Freeze enum constants in both backend and frontend.
- Confirm cookie/CORS/session behavior in target environment.
- Decide if add-item rule loophole and report-status scope should be fixed backend-side.
- Decide whether quotation updates are admin-only by role policy (currently middleware checks authenticated active user, but no per-feature role checks beyond admin account model).
- Add endpoint docs/examples to frontend storybook or API collection used by your group.

