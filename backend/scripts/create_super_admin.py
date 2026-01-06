#!/usr/bin/env python3
"""
Script to create a platform super admin user.
Usage: python scripts/create_super_admin.py <email> <password> [tenant_name]
"""
import sys
from sqlmodel import Session, select

from app.db import engine
from app.models import User, Tenant
from app.core.security import hash_password
from app.seed import ensure_roles_for_tenant
from app.models.role import UserRole


def create_super_admin(email: str, password: str, tenant_name: str = "Platform Admin") -> None:
    """Create a super admin user."""
    with Session(engine) as session:
        # Check if user already exists
        existing = session.exec(select(User).where(User.email == email.lower())).first()
        if existing:
            print(f"❌ User with email {email} already exists!")
            sys.exit(1)
        
        # Create tenant if it doesn't exist
        tenant = session.exec(select(Tenant).where(Tenant.name == tenant_name)).first()
        if not tenant:
            tenant = Tenant(name=tenant_name)
            session.add(tenant)
            session.flush()
            print(f"✅ Created tenant: {tenant_name}")
        else:
            print(f"✅ Using existing tenant: {tenant_name}")
        
        # Create super admin user
        user = User(
            tenant_id=tenant.id,  # type: ignore[arg-type]
            email=email.lower(),
            hashed_password=hash_password(password),
            is_super_admin=True,
            is_active=True,
        )
        session.add(user)
        session.flush()
        
        # Assign super_admin role
        roles_by_name = ensure_roles_for_tenant(session, tenant.id)  # type: ignore[arg-type]
        super_role = roles_by_name.get("super_admin")
        if super_role:
            session.add(UserRole(user_id=user.id, role_id=super_role.id))  # type: ignore[arg-type]
        
        session.commit()
        session.refresh(user)
        
        print(f"✅ Super admin created successfully!")
        print(f"   Email: {email}")
        print(f"   Tenant: {tenant_name}")
        print(f"   User ID: {user.id}")
        print(f"\n📝 You can now login with these credentials at /login")


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python scripts/create_super_admin.py <email> <password> [tenant_name]")
        print("\nExample:")
        print("  python scripts/create_super_admin.py admin@example.com 'SecurePass123!' 'Platform Admin'")
        sys.exit(1)
    
    email = sys.argv[1]
    password = sys.argv[2]
    tenant_name = sys.argv[3] if len(sys.argv) > 3 else "Platform Admin"
    
    create_super_admin(email, password, tenant_name)

