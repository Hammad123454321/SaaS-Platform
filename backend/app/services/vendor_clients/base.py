"""Base interface for vendor clients."""
from abc import ABC, abstractmethod
from typing import Any, Dict, List


class BaseVendorClient(ABC):
    """Abstract base class for all vendor module clients."""

    @abstractmethod
    async def health(self) -> Dict[str, Any]:
        """Check module service health."""
        pass

    @abstractmethod
    async def list_records(self, resource: str, **filters: Any) -> List[Dict[str, Any]]:
        """List records for a given resource."""
        pass

    @abstractmethod
    async def create_record(self, resource: str, payload: Dict[str, Any]) -> Dict[str, Any]:
        """Create a new record."""
        pass

    @abstractmethod
    async def close(self):
        """Cleanup resources (close HTTP clients, etc.)."""
        pass























