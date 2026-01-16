"use client";

import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import { useSessionStore } from "@/lib/store";
import { useRouter } from "next/navigation";
import { Users, Plus, Edit, Trash2, Shield } from "lucide-react";

type User = {
  id: number;
  email: string;
  is_active: boolean;
  is_super_admin: boolean;
  roles: string[];
};

export default function UsersPage() {
  const router = useRouter();
  const { user } = useSessionStore();
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [formData, setFormData] = useState({ email: "", password: "", role_names: [] as string[], is_active: true });
  const [error, setError] = useState("");

  useEffect(() => {
    if (!user) {
      router.push("/login");
      return;
    }
    loadUsers();
  }, [user, router]);

  const loadUsers = async () => {
    setLoading(true);
    try {
      const res = await api.get<User[]>("/users");
      setUsers(res.data);
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to load users");
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    try {
      await api.post("/users", formData);
      setShowCreateModal(false);
      setFormData({ email: "", password: "", role_names: [], is_active: true });
      loadUsers();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to create user");
    }
  };

  const handleUpdate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingUser) return;
    setError("");
    try {
      await api.patch(`/users/${editingUser.id}`, {
        email: formData.email,
        password: formData.password || undefined,
        is_active: formData.is_active,
        role_names: formData.role_names,
      });
      setEditingUser(null);
      setFormData({ email: "", password: "", role_names: [], is_active: true });
      loadUsers();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to update user");
    }
  };

  const handleDelete = async (userId: number) => {
    if (!confirm("Are you sure you want to deactivate this user?")) return;
    try {
      await api.delete(`/users/${userId}`);
      loadUsers();
    } catch (err: any) {
      setError(err?.response?.data?.detail || "Failed to delete user");
    }
  };

  const openEditModal = (user: User) => {
    setEditingUser(user);
    setFormData({ email: user.email, password: "", role_names: user.roles, is_active: user.is_active });
  };

  if (!user) return null;

  return (
    <div className="space-y-6">
      <header className="bg-white rounded-2xl px-6 py-5 shadow-sm border border-gray-100">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm uppercase tracking-[0.2em] text-purple-600 font-medium">User Management</p>
            <h1 className="text-3xl font-semibold text-gray-900">Team Members</h1>
            <p className="text-sm text-gray-500">Manage users and their roles in your organization.</p>
          </div>
          <button
            onClick={() => {
              setShowCreateModal(true);
              setFormData({ email: "", password: "", role_names: [], is_active: true });
            }}
            className="flex items-center gap-2 rounded-lg bg-gradient-to-r from-purple-600 to-blue-600 px-4 py-2 text-sm font-semibold text-white shadow transition hover:-translate-y-0.5 hover:shadow-lg"
          >
            <Plus className="h-4 w-4" />
            Add User
          </button>
        </div>
      </header>

      {error && (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}

      <div className="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        {loading ? (
          <div className="text-center py-8 text-gray-500">Loading users...</div>
        ) : (
          <div className="space-y-3">
            {users.map((u) => (
              <div
                key={u.id}
                className="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3"
              >
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 rounded-full bg-purple-100 flex items-center justify-center">
                    <Users className="h-5 w-5 text-purple-600" />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-gray-900">{u.email}</span>
                      {u.is_super_admin && (
                        <Shield className="h-4 w-4 text-yellow-500" />
                      )}
                      {!u.is_active && (
                        <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700 font-medium">
                          Inactive
                        </span>
                      )}
                    </div>
                    <div className="text-xs text-gray-500">
                      Roles: {u.roles.length > 0 ? u.roles.join(", ") : "None"}
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => openEditModal(u)}
                    className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-purple-400 hover:bg-purple-50"
                  >
                    <Edit className="h-3 w-3 inline mr-1" />
                    Edit
                  </button>
                  {u.id !== user?.id && (
                    <button
                      onClick={() => handleDelete(u.id)}
                      className="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:border-red-400 hover:bg-red-50"
                    >
                      <Trash2 className="h-3 w-3 inline mr-1" />
                      Deactivate
                    </button>
                  )}
                </div>
              </div>
            ))}
            {users.length === 0 && (
              <div className="text-center py-8 text-gray-500">No users found.</div>
            )}
          </div>
        )}
      </div>

      {/* Create/Edit Modal */}
      {(showCreateModal || editingUser) && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white w-full max-w-md rounded-2xl p-6 shadow-2xl">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">
              {editingUser ? "Edit User" : "Create User"}
            </h2>
            <form onSubmit={editingUser ? handleUpdate : handleCreate} className="space-y-4">
              <div>
                <label className="text-sm text-gray-600 font-medium">Email</label>
                <input
                  type="email"
                  required
                  className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                />
              </div>
              {!editingUser && (
                <div>
                  <label className="text-sm text-gray-600 font-medium">Password</label>
                  <input
                    type="password"
                    required
                    className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                    value={formData.password}
                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  />
                  <p className="mt-1 text-xs text-gray-500">
                    Min 12 characters, must include special character.
                  </p>
                </div>
              )}
              {editingUser && (
                <div>
                  <label className="text-sm text-gray-600 font-medium">New Password (leave blank to keep current)</label>
                  <input
                    type="password"
                    className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                    value={formData.password}
                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  />
                </div>
              )}
              {editingUser && (
                <div>
                  <label className="text-sm text-gray-600 font-medium">Status</label>
                  <select
                    className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-900 outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20"
                    value={editingUser.is_active ? "active" : "inactive"}
                    onChange={(e) => {
                      setFormData({ ...formData, is_active: e.target.value === "active" });
                    }}
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </select>
                </div>
              )}
              <div className="flex gap-3">
                <button
                  type="submit"
                  className="flex-1 rounded-lg bg-gradient-to-r from-purple-600 to-blue-600 px-4 py-2 font-semibold text-white shadow transition hover:-translate-y-0.5 hover:shadow-lg"
                >
                  {editingUser ? "Update" : "Create"}
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setShowCreateModal(false);
                    setEditingUser(null);
                    setFormData({ email: "", password: "", role_names: [], is_active: true });
                    setError("");
                  }}
                  className="rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 transition hover:border-purple-400 hover:bg-gray-50"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

