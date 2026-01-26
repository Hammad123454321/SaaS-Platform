"""
Script to verify and fix user permissions.
Run this to ensure all company_admin users have ACCESS_MODULES permission.
"""
import sys
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from sqlmodel import Session, select
from app.db import engine
from app.models import User, UserRole, Role, RolePermission, Permission
from app.models.role import PermissionCode

def fix_user_permissions():
    """Check and fix user permissions."""
    with Session(engine) as session:
        # Get all users
        users = session.exec(select(User)).all()
        
        print(f"Found {len(users)} users")
        print("-" * 80)
        
        for user in users:
            # Get user's roles
            user_roles = session.exec(
                select(Role)
                .join(UserRole)
                .where(UserRole.user_id == user.id)
            ).all()
            
            role_names = [r.name for r in user_roles]
            print(f"\nUser: {user.email} (ID: {user.id}, Tenant: {user.tenant_id})")
            print(f"  Roles: {', '.join(role_names) if role_names else 'None'}")
            
            # Check if user has ACCESS_MODULES permission
            has_access = session.exec(
                select(RolePermission)
                .join(UserRole, RolePermission.role_id == UserRole.role_id)
                .join(Permission, RolePermission.permission_id == Permission.id)
                .where(
                    UserRole.user_id == user.id,
                    Permission.code == PermissionCode.ACCESS_MODULES.value
                )
            ).first()
            
            if has_access:
                print(f"  ✓ Has ACCESS_MODULES permission")
            else:
                print(f"  ✗ Missing ACCESS_MODULES permission")
                
                # Try to add it by assigning company_admin role if they don't have it
                if "company_admin" not in role_names:
                    company_admin_role = session.exec(
                        select(Role).where(
                            Role.tenant_id == user.tenant_id,
                            Role.name == "company_admin"
                        )
                    ).first()
                    
                    if company_admin_role:
                        print(f"  → Adding company_admin role...")
                        session.add(UserRole(user_id=user.id, role_id=company_admin_role.id))
                        session.commit()
                        print(f"  ✓ Added company_admin role")
                    else:
                        print(f"  ✗ company_admin role not found for tenant {user.tenant_id}")
                else:
                    print(f"  → User has company_admin role but missing permission")
                    print(f"  → This should be fixed by ensure_roles_for_tenant")
        
        print("\n" + "=" * 80)
        print("Done! If permissions were missing, try logging in again.")

if __name__ == "__main__":
    fix_user_permissions()















