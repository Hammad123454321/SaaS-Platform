from typing import Any, Dict, List, Optional


class VendorStubClient:
    """Stub client that mimics vendor API calls for CRM/HRM/POS/Tasks/Booking/Landing."""

    def __init__(self, vendor: str, credentials: dict):
        self.vendor = vendor
        self.credentials = credentials

    def health(self) -> dict:
        return {"vendor": self.vendor, "status": "ok"}

    def list_records(self, resource: str) -> List[Dict[str, Any]]:
        return [{"id": "stub-1", "resource": resource, "vendor": self.vendor}]

    def create_record(self, resource: str, payload: dict) -> dict:
        return {"id": "stub-created", "resource": resource, "payload": payload, "vendor": self.vendor}

    def add_note(self, record_id: str, note: str) -> dict:
        return {"record_id": record_id, "note": note, "vendor": self.vendor}

    def draft_email(self, to: str, subject: str, body: str) -> dict:
        return {"to": to, "subject": subject, "body": body, "vendor": self.vendor}

