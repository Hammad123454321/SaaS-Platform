import pytest
from types import SimpleNamespace

from fastapi import HTTPException

from app.api.routes import pos


def test_pos_owner_required():
    with pytest.raises(HTTPException):
        pos._ensure_owner(SimpleNamespace(is_owner=False))

    pos._ensure_owner(SimpleNamespace(is_owner=True))
