# Frontend (Vanilla HTML/CSS/JS)

This is a lightweight, responsive frontend for the Carwash WebSys API.

## Run locally

From the `frontend` directory:

```bash
python3 -m http.server 5173
```

Then open `http://127.0.0.1:5173`.

By default the frontend uses `${window.location.origin}/api` when served over HTTP(S).

For local split development, you can still point it to `http://127.0.0.1:8000/api` in the login panel.

## Features

- Session-based admin login/logout (`/auth/login`, `/auth/logout`, `/auth/me`)
- Dashboard KPIs
- Customers and Vehicles list/create with pagination and search
- Services and quote preview
- Tablet check-in job order creation with UI-side service-rule guardrails
- Job order list and detail
- Daily report summary and table

## Deployment note

For Render submission hosting, serve this `public/` frontend from the Laravel app so the UI and API stay on one origin.
