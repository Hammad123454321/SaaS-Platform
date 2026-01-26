from pydantic_settings import BaseSettings, SettingsConfigDict
from pydantic import AnyUrl, Field
from typing import List


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env", 
        env_file_encoding="utf-8", 
        case_sensitive=False,
        extra="ignore"  # Ignore extra fields like old database_url
    )

    app_name: str = "SaaS Core API"
    environment: str = Field(default="local", description="Deployment environment label")
    debug: bool = False

    # MongoDB
    mongodb_uri: str = Field(default="mongodb://localhost:27017", description="MongoDB connection URI")
    mongodb_db_name: str = Field(default="saas", description="MongoDB database name")

    # Auth/JWT
    jwt_secret_key: str = Field(default="change-me", description="HS256 secret for access tokens")
    jwt_refresh_secret_key: str = Field(default="change-me-refresh", description="HS256 secret for refresh tokens")
    jwt_algorithm: str = "HS256"
    access_token_exp_minutes: int = 1440  # 24 hours for debugging - change back to 30 later
    refresh_token_exp_minutes: int = 60 * 24 * 7

    # Security
    password_min_length: int = 12
    password_require_special: bool = True
    password_max_length: int = 72  # bcrypt practical limit

    # Email (SendGrid)
    sendgrid_api_key: str = "SENDGRID_KEY_PLACEHOLDER"
    email_from: str = "no-reply@saas.local"

    # Stripe (placeholders for now)
    stripe_secret_key: str = "STRIPE_SECRET_PLACEHOLDER"
    stripe_webhook_secret: str = "STRIPE_WEBHOOK_SECRET_PLACEHOLDER"

    # Observability
    log_level: str = "DEBUG"  # Set to DEBUG temporarily to debug token expiration

    # Frontend / CORS
    cors_origins: List[str] = Field(
        default_factory=lambda: ["http://localhost:3000"],
        description="Comma-separated list of allowed origins",
    )
    frontend_base_url: str = Field(default="http://localhost:3000")
    cookie_secure: bool | None = None  # if None, derive from debug flag
    cookie_samesite: str = "none"  # "none" needed for cross-site cookies (frontend on :3000)

    # OpenAI / LangChain
    openai_api_key: str = Field(default="", description="OpenAI API key for GPT models")
    openai_model: str = Field(default="gpt-4-turbo-preview", description="OpenAI model to use (gpt-4, gpt-4-turbo-preview, gpt-3.5-turbo)")
    openai_temperature: float = Field(default=0.7, description="Temperature for AI responses")


settings = Settings()  # Singleton-style settings instance


def is_development() -> bool:
    """Check if the application is running in development mode."""
    return settings.environment.lower() == "development"
