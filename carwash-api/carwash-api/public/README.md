# Frontend (Vanilla HTML/CSS/JS)

This is a lightweight, responsive frontend for the Carwash WebSys API.

## Run locally

From the `frontend` directory:

```bash
python3 -m http.server 5173
```

Then open `http://127.0.0.1:5173`.

By default API base is `http://127.0.0.1:8000/api`, but you can change it in the login panel.

## Features

- Session-based admin login/logout (`/auth/login`, `/auth/logout`, `/auth/me`)
- Dashboard KPIs
- Customers and Vehicles list/create with pagination and search
- Services and quote preview
- Tablet check-in job order creation with UI-side service-rule guardrails
- Job order list and detail
- Daily report summary and table

## Deployment note

For a single machine/XAMPP setup, serve `frontend/` as static files and run Laravel API locally.
For multi-computer use, host frontend and backend on Railway or similar, then point API Base URL to deployed backend.
