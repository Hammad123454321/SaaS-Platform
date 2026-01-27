"""
Script to set up Taskify credentials for a tenant.

This script:
1. Creates TenantTaskifyConfig if it doesn't exist
2. Optionally creates a user in Taskify and gets their token
3. Stores the configuration

Usage:
    python scripts/setup_taskify_credentials.py --tenant-id 1 --base-url http://taskify:8001 --api-token YOUR_TOKEN --workspace-id 1
"""
import sys
import argparse
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent.parent))

from sqlmodel import Session, select
from app.db import engine
from app.models import Tenant
from app.models.taskify_config import TenantTaskifyConfig

def setup_taskify_credentials(tenant_id: int, base_url: str, api_token: str, workspace_id: int):
    """Set up Taskify credentials for a tenant."""
    with Session(engine) as session:
        # Check if tenant exists
        tenant = session.get(Tenant, tenant_id)
        if not tenant:
            print(f"❌ Tenant {tenant_id} not found")
            return False
        
        # Check if config already exists
        existing = session.exec(
            select(TenantTaskifyConfig).where(
                TenantTaskifyConfig.tenant_id == tenant_id
            )
        ).first()
        
        if existing:
            print(f"⚠️  Taskify config already exists for tenant {tenant_id}")
            print(f"   Updating with new credentials...")
            existing.base_url = base_url
            existing.api_token = api_token
            existing.workspace_id = workspace_id
            existing.is_active = True
            session.add(existing)
        else:
            config = TenantTaskifyConfig(
                tenant_id=tenant_id,
                workspace_id=workspace_id,
                workspace_name=f"{tenant.name} Workspace",
                api_token=api_token,
                base_url=base_url,
                is_active=True,
            )
            session.add(config)
            print(f"✅ Created Taskify config for tenant {tenant_id}")
        
        session.commit()
        print(f"✅ Taskify credentials saved successfully!")
        print(f"   Base URL: {base_url}")
        print(f"   Workspace ID: {workspace_id}")
        print(f"   API Token: {'*' * 20}...{api_token[-4:] if len(api_token) > 4 else '****'}")
        return True

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Set up Taskify credentials for a tenant")
    parser.add_argument("--tenant-id", type=int, required=True, help="Tenant ID")
    parser.add_argument("--base-url", type=str, default="http://taskify:8001", help="Taskify base URL")
    parser.add_argument("--api-token", type=str, required=True, help="Taskify API token (Sanctum token)")
    parser.add_argument("--workspace-id", type=int, required=True, help="Taskify workspace ID")
    
    args = parser.parse_args()
    
    try:
        setup_taskify_credentials(
            tenant_id=args.tenant_id,
            base_url=args.base_url,
            api_token=args.api_token,
            workspace_id=args.workspace_id,
        )
    except Exception as e:
        print(f"❌ Error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)























