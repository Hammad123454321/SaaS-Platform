"""Script to test Taskify connection for a tenant."""
import asyncio
import sys
from sqlmodel import Session, select

from app.db import engine
from app.models import VendorCredential, Tenant, ModuleCode
from app.services.vendor_clients.factory import create_vendor_client


async def test_taskify_connection(tenant_id: int = None):
    """
    Test Taskify connection for a tenant.
    
    Args:
        tenant_id: Tenant ID (if None, uses first tenant found)
    """
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

        print(f"📋 Testing Taskify connection for tenant: {tenant.id} ({tenant.name})")

        # Get credentials
        stmt = select(VendorCredential).where(
            VendorCredential.tenant_id == tenant.id,
            VendorCredential.vendor == ModuleCode.TASKS.value,
        )
        cred = session.exec(stmt).first()

        if not cred:
            print("❌ No Taskify credentials found for this tenant.")
            print("   Run: python scripts/setup_taskify.py --token YOUR_TOKEN")
            sys.exit(1)

        credentials = cred.credentials
        print(f"   Base URL: {credentials.get('base_url')}")
        print(f"   Workspace ID: {credentials.get('workspace_id')}")
        print(f"   API Token: {'*' * 20}...{credentials.get('api_token', '')[-4:]}")

        # Test connection
        print("\n🔍 Testing connection...")
        try:
            client = await create_vendor_client(ModuleCode.TASKS, tenant.id, session)
            if not client:
                print("❌ Failed to create Taskify client")
                sys.exit(1)

            # Health check
            health = await client.health()
            print(f"   Health Status: {health.get('status')}")
            if health.get('status') == 'ok':
                print("✅ Taskify connection successful!")
            else:
                print(f"⚠️  Taskify connection issue: {health.get('error')}")

            # Test list tasks
            print("\n📋 Testing list tasks...")
            tasks = await client.list_tasks()
            print(f"   Found {len(tasks)} tasks")
            if tasks:
                print(f"   Sample task: {tasks[0].get('title', 'N/A')}")

            # Test list projects
            print("\n📁 Testing list projects...")
            projects = await client.list_projects()
            print(f"   Found {len(projects)} projects")
            if projects:
                print(f"   Sample project: {projects[0].get('name', 'N/A')}")

            await client.close()
            print("\n✅ All tests passed!")

        except Exception as e:
            print(f"❌ Connection test failed: {e}")
            import traceback
            traceback.print_exc()
            sys.exit(1)


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Test Taskify connection")
    parser.add_argument("--tenant-id", type=int, help="Tenant ID (optional)")

    args = parser.parse_args()

    asyncio.run(test_taskify_connection(tenant_id=args.tenant_id))

