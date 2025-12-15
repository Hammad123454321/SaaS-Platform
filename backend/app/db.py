from sqlmodel import SQLModel, create_engine, Session

from app.config import settings
from app.seed import seed_permissions

engine = create_engine(str(settings.database_url), echo=settings.debug)


def init_db() -> None:
    SQLModel.metadata.create_all(engine)
    with Session(engine) as session:
        seed_permissions(session)


def get_session() -> Session:
    with Session(engine) as session:
        yield session

