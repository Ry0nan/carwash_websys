# Render Deployment Guide

This repo is ready to deploy on Render as a single Docker web service plus one Render Postgres database.

## What gets deployed

- Active backend: `carwash-api/carwash-api`
- Active browser UI: `carwash-api/carwash-api/public`
- Ignored legacy folder: `carwash-api-old`

This setup keeps the frontend and backend on the same Render origin, so the Laravel session cookie works without cross-site cookie workarounds.

## Why Docker on Render

Render's current native runtimes do not include PHP, so this project uses a Docker-based web service instead.

Official docs:
- https://render.com/docs/docker
- https://render.com/docs/blueprint-spec
- https://render.com/docs/monorepo-support

## Before first deploy

Generate an application key locally and keep it ready for Render:

```bash
cd carwash-api/carwash-api
php artisan key:generate --show
```

Copy the full output, including the `base64:` prefix.

## Deploy with Blueprint

1. Push this repo to GitHub, GitLab, or Bitbucket.
2. In Render, create a new Blueprint from the repo.
3. Render will detect `render.yaml` and propose:
   - Web service: `carwash-websys`
   - Postgres database: `carwash-websys-db`
4. When prompted for `APP_KEY`, paste the generated key.
5. Complete the Blueprint deploy.

## After deploy

The web service will:

- build from `Dockerfile`
- run `php artisan migrate --force --seed` when the container starts
- serve the UI and API from the same domain

Open the service URL in Render. The frontend will call `/api` on the same origin automatically.

## Important notes

- If you want to keep using the separate `frontend/` folder for local experiments, it now defaults to same-origin `/api` and still allows manual override in the UI.
- For submission, the Render deployment should use the Laravel `public/` frontend because that is the frontend served by the Docker web service.
