from pydantic_settings import BaseSettings, SettingsConfigDict
from pydantic import AnyUrl, Field, field_validator


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", case_sensitive=False)

    app_name: str = "AI Service"
    environment: str = "local"
    debug: bool = False
    log_level: str = "INFO"

    # Auth / JWT
    jwks_url: str | None = None
    jwt_audience: str | None = None
    jwt_issuer: str | None = None
    fallback_jwt_secret: str | None = Field(
        default=None, description="Optional HS256 secret for dev/local"
    )
    jwt_algorithms: list[str] = ["RS256", "HS256"]

    # Rate limiting
    rate_limit_per_minute: int = 30

    # Providers
    default_model: str = "mistral-small"  # placeholder
    provider_base_url: AnyUrl | None = None
    provider_api_key: str | None = None
    hf_model_id: str | None = None
    hf_token: str | None = None

    # Misc
    allowlisted_actions: str = "create_task,add_crm_note,draft_email"

    def allowlisted_actions_list(self) -> list[str]:
        if isinstance(self.allowlisted_actions, str):
            return [item.strip() for item in self.allowlisted_actions.split(",") if item.strip()]
        try:
            return list(self.allowlisted_actions)  # type: ignore[list-item]
        except Exception:
            return []

    @field_validator("provider_base_url", mode="before")
    @classmethod
    def _empty_url_to_none(cls, v):
        if v == "":
            return None
        return v


settings = Settings()

