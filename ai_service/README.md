# AI Service (Milestone 5)

Generic FastAPI service for chat, action proposals, and embeddings with JWT/JWKS auth and rate limits.

## Endpoints
- `POST /chat` — LLM reply with `trace_id`
- `POST /actions/call` — returns allowlisted proposed actions only
- `POST /embeddings/index` — returns embeddings for provided texts
- `GET /health`

## Auth
- Expects Bearer JWT. Verifies via JWKS if `JWKS_URL` set; falls back to `FALLBACK_JWT_SECRET` HS256 for local/dev.
- Per-tenant rate limiting (`RATE_LIMIT_PER_MINUTE`).

## Provider
- Stub/Mistral placeholder today; swap `provider_factory` to a real client. Uses `DEFAULT_MODEL`, `PROVIDER_API_KEY`, `PROVIDER_BASE_URL` when you wire a real provider.

## Env
- Copy `env.example` to `.env` and set JWKS or fallback secret.

## Run
```bash
cd ai_service
poetry install
cp env.example .env
poetry run uvicorn app.main:app --reload --port 8100
```

