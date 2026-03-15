# Render Deployment Guide

This repository is set up to deploy on Render as:

- 1 Docker web service
- 1 Render Postgres database

The deployment uses the Laravel app in `carwash-api/carwash-api` and serves the browser UI from Laravel `public/`, so the frontend and backend stay on one origin and session login works cleanly.

## What Render will deploy

- Active backend: `carwash-api/carwash-api`
- Active frontend used in production: `carwash-api/carwash-api/public`
- Legacy folder to ignore: `carwash-api-old`
- Local-only static copy: `frontend/`

## Files already prepared in this repo

- [render.yaml](/d:/Web_Projects/carwash_websys/render.yaml)
- [Dockerfile](/d:/Web_Projects/carwash_websys/Dockerfile)
- [.dockerignore](/d:/Web_Projects/carwash_websys/.dockerignore)

## Before you start

You need:

1. A GitHub, GitLab, or Bitbucket repository containing this project.
2. A Render account.
3. An `APP_KEY` for Laravel.

## How to generate `APP_KEY`

If PHP is installed on your machine, run:

```bash
cd carwash-api/carwash-api
php artisan key:generate --show
```

Copy the full output, including `base64:`.

If PHP is not installed locally, you can generate a 32-byte base64 key and store it as:

```text
base64:YOUR_GENERATED_VALUE
```

## Recommended deployment method: Blueprint

Use the repository directly through Render Blueprint because this repo already has `render.yaml`.

### Step 1: Push the repo

Commit your latest changes and push the project to your Git provider.

### Step 2: Create a new Blueprint in Render

In Render:

1. Click `New +`
2. Choose `Blueprint`
3. Connect your repository
4. Select this project repository

Render will detect [render.yaml](/d:/Web_Projects/carwash_websys/render.yaml).

### Step 3: Confirm the services

Render should create:

- Web service: `carwash-websys`
- Postgres database: `carwash-websys-db`

### Step 4: Set the missing secret

When Render asks for environment values, set:

- `APP_KEY` = your generated Laravel key

The other database and runtime values are already declared in `render.yaml`.

### Step 5: Deploy

Start the Blueprint deploy.

Render will:

- build the Docker image from [Dockerfile](/d:/Web_Projects/carwash_websys/Dockerfile)
- provision Postgres
- start the container
- run `php artisan migrate --force --seed` during container startup

## What happens during startup

The container command is defined in [Dockerfile](/d:/Web_Projects/carwash_websys/Dockerfile#L49):

```sh
php artisan migrate --force --seed && apache2-foreground
```

That means on startup it will:

1. run migrations
2. run seeders
3. start Apache

## Default seeded login

The seeders create this admin account:

- Email: `admin@carwash.local`
- Password: `Admin@1234`

You can verify that in:

- [AdminSeeder.php](/d:/Web_Projects/carwash_websys/carwash-api/carwash-api/database/seeders/AdminSeeder.php)

## After deployment

Open your Render web service URL.

Expected behavior:

- the homepage loads from Laravel `public/`
- frontend requests go to `/api`
- login uses Laravel session cookies
- authenticated actions should work without setting a separate frontend URL

## Basic post-deploy checks

After Render finishes, test these in order:

1. Open the app URL in the browser.
2. Confirm the page loads normally.
3. Log in with:
   - `admin@carwash.local`
   - `Admin@1234`
4. Open the browser dev tools network tab.
5. Confirm requests go to the same domain under `/api/...`.
6. Confirm `/api/health` returns success.
7. Try creating:
   - one customer
   - one vehicle
   - one job order
8. Open dashboard, orders, and reports pages to confirm data loads.

## If Render build fails

Check these first:

- `APP_KEY` is set
- Render created the Postgres database
- the repo includes the latest migration fixes
- the service is using the root [Dockerfile](/d:/Web_Projects/carwash_websys/Dockerfile)

## If the app opens but login or data fails

Check:

- network requests are going to `/api`
- the database migration completed
- the admin seeder ran
- the browser is not using an old `apiBase` value saved in localStorage

If needed, clear localStorage for the site and reload.

## Important submission note

For submission, use the deployed Render URL from the Laravel app, not the separate `frontend/` folder.
The Render deployment serves the production frontend from `carwash-api/carwash-api/public`.

## Quick summary

1. Push repo.
2. Create Render Blueprint from repo.
3. Set `APP_KEY`.
4. Deploy.
5. Open the Render URL.
6. Log in with the seeded admin account.
