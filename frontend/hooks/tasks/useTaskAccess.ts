import { useMemo } from "react";
import { useSessionStore } from "@/lib/store";

export function useTaskAccess() {
  const { user } = useSessionStore();
  
  const permissions = useMemo(() => {
    if (!user) {
      return {
        canCreateTask: false,
        canUpdateTask: false,
        canDeleteTask: false,
        canChangeStatus: false,
        canCreateProject: false,
        canUpdateProject: false,
        canDeleteProject: false,
        isReadOnly: true,
      };
    }
    
    const roleNames = (user.roles || []).map(r => r.toLowerCase());
    const isOwner = user.is_super_admin || roleNames.includes("owner") || roleNames.includes("company_admin");
    const isManager = roleNames.includes("manager");
    const isStaff = roleNames.includes("staff");
    const isAccountant = roleNames.includes("accountant");
    
    return {
      canCreateTask: isOwner || isManager || isStaff, // Accountant cannot create
      canUpdateTask: isOwner || isManager || isStaff, // Accountant is read-only
      canDeleteTask: isOwner || isManager, // Staff cannot delete
      canChangeStatus: isOwner || isManager || isStaff, // Staff can only change status
      canCreateProject: isOwner || isManager, // Staff needs permission
      canUpdateProject: isOwner || isManager, // Staff needs permission
      canDeleteProject: isOwner || isManager, // Only Owner/Manager
      isReadOnly: isAccountant, // Accountant is read-only
      isStaff: isStaff,
      isManager: isManager,
      isOwner: isOwner,
    };
  }, [user]);
  
  return permissions;
}

