"""Script to setup Taskify integration for a tenant."""
import asyncio
import sys
from sqlmodel import Session, select

from app.db import engine
from app.models import VendorCredential, Tenant, ModuleCode, ModuleEntitlement


async def setup_taskify(
    tenant_id: int = None,
    taskify_url: str = "http://localhost:8001",
    api_token: str = None,
    workspace_id: int = 1,
):
    """
    Setup Taskify integration for a tenant.
    
    Args:
        tenant_id: Tenant ID (if None, uses first tenant found)
        taskify_url: Base URL of Taskify instance
        api_token: Laravel Sanctum API token (required)
        workspace_id: Workspace ID in Taskify
    """
    if not api_token:
        print("❌ Error: api_token is required")
        print("   Get token from Taskify: php artisan tinker")
        print("   Then: $user->createToken('saas-platform')->plainTextToken")
        sys.exit(1)

    with Session(engine) as session:
        # Get tenant
        if tenant_id:
            tenant = session.get(Tenant, tenant_id)
            if not tenant:
                print(f"❌ Tenant {tenant_id} not found")
                sys.exit(1)
        else:
            tenant = session.exec(select(Tenant)).first()
            if not tenant:
                print("❌ No tenant found. Please create a tenant first via signup.")
                sys.exit(1)

        print(f"📋 Setting up Taskify for tenant: {tenant.id}")

        # Check if credential already exists
        stmt = select(VendorCredential).where(
            VendorCredential.tenant_id == tenant.id,
            VendorCredential.vendor == ModuleCode.TASKS.value,
        )
        existing_cred = session.exec(stmt).first()

        if existing_cred:
            print("⚠️  Credential already exists. Updating...")
            existing_cred.credentials = {
                "base_url": taskify_url,
                "api_token": api_token,
                "workspace_id": workspace_id,
            }
            session.add(existing_cred)
            credential = existing_cred
        else:
            # Create VendorCredential
            credential = VendorCredential(
                tenant_id=tenant.id,
                vendor=ModuleCode.TASKS.value,
                credentials={
                    "base_url": taskify_url,
                    "api_token": api_token,
                    "workspace_id": workspace_id,
                },
            )
            session.add(credential)

        # Ensure ModuleEntitlement exists
        stmt = select(ModuleEntitlement).where(
            ModuleEntitlement.tenant_id == tenant.id,
            ModuleEntitlement.module_code == ModuleCode.TASKS,
        )
        entitlement = session.exec(stmt).first()

        if not entitlement:
            entitlement = ModuleEntitlement(
                tenant_id=tenant.id,
                module_code=ModuleCode.TASKS,
                enabled=True,
            )
            session.add(entitlement)
        elif not entitlement.enabled:
            entitlement.enabled = True
            session.add(entitlement)

        session.commit()
        session.refresh(credential)

        print("✅ Taskify configured successfully!")
        print(f"   Tenant ID: {tenant.id}")
        print(f"   Taskify URL: {taskify_url}")
        print(f"   Workspace ID: {workspace_id}")
        print("\n🧪 Test the connection:")
        print(f"   curl http://localhost:8000/api/modules/tasks/health \\")
        print(f"     -H 'Authorization: Bearer YOUR_TOKEN'")


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Setup Taskify integration")
    parser.add_argument("--tenant-id", type=int, help="Tenant ID (optional)")
    parser.add_argument("--url", default="http://localhost:8001", help="Taskify base URL")
    parser.add_argument("--token", required=True, help="Laravel Sanctum API token")
    parser.add_argument("--workspace-id", type=int, default=1, help="Taskify workspace ID")

    args = parser.parse_args()

    asyncio.run(
        setup_taskify(
            tenant_id=args.tenant_id,
            taskify_url=args.url,
            api_token=args.token,
            workspace_id=args.workspace_id,
        )
    )


















