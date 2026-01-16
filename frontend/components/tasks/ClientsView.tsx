"use client";

import { useState } from "react";
import { Plus, Edit, Trash2, Users } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useClients, useCreateClient, useUpdateClient, useDeleteClient } from "@/hooks/tasks/useClients";
import { ClientFormData } from "@/types/client";
import { useTaskAccess } from "@/hooks/tasks/useTaskAccess";
import { ConfirmDialog } from "@/components/shared/ConfirmDialog";

export function ClientsView() {
  const { data: clients, isLoading } = useClients();
  const createClient = useCreateClient();
  const updateClient = useUpdateClient();
  const deleteClient = useDeleteClient();
  const { canCreateProject } = useTaskAccess(); // Using same permission for clients

  const [showForm, setShowForm] = useState(false);
  const [editingClient, setEditingClient] = useState<any>(null);
  const [deletingClient, setDeletingClient] = useState<any>(null);
  const [formData, setFormData] = useState<Partial<ClientFormData>>({
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    company: "",
    address: "",
    notes: "",
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (editingClient) {
      await updateClient.mutateAsync({ id: editingClient.id, data: formData });
    } else {
      await createClient.mutateAsync(formData as ClientFormData);
    }
    setShowForm(false);
    setEditingClient(null);
    resetForm();
  };

  const resetForm = () => {
    setFormData({
      first_name: "",
      last_name: "",
      email: "",
      phone: "",
      company: "",
      address: "",
      notes: "",
    });
  };

  const handleEdit = (client: any) => {
    setEditingClient(client);
    setFormData({
      first_name: client.first_name,
      last_name: client.last_name,
      email: client.email,
      phone: client.phone,
      company: client.company,
      address: client.address,
      notes: client.notes,
    });
    setShowForm(true);
  };

  const handleDelete = async () => {
    if (deletingClient) {
      await deleteClient.mutateAsync(deletingClient.id);
      setDeletingClient(null);
    }
  };

  if (isLoading) {
    return <div className="text-center py-12 text-gray-500">Loading clients...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-gray-900">Clients</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingClient(null); resetForm(); }} className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700">
            <Plus className="h-4 w-4 mr-2" />
            Create Client
          </Button>
        )}
      </div>

      {clients && clients.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {clients.map((client) => (
            <div key={client.id} className="bg-white rounded-lg p-4 space-y-2 border border-gray-200 shadow-sm">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <h3 className="font-semibold text-gray-900">
                    {client.first_name} {client.last_name}
                  </h3>
                  {client.company && (
                    <p className="text-sm text-gray-600">{client.company}</p>
                  )}
                  {client.email && (
                    <p className="text-xs text-gray-500">{client.email}</p>
                  )}
                  {client.phone && (
                    <p className="text-xs text-gray-500">{client.phone}</p>
                  )}
                </div>
                {canCreateProject && (
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleEdit(client)}
                      className="h-8 w-8 text-gray-600 hover:text-purple-600 hover:bg-purple-50"
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeletingClient(client)}
                      className="h-8 w-8 text-gray-600 hover:text-red-600 hover:bg-red-50"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="bg-white rounded-lg p-12 text-center border border-gray-200">
          <div className="h-16 w-16 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-4">
            <Users className="h-8 w-8 text-purple-600" />
          </div>
          <p className="text-gray-500">No clients yet. Create your first client to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="bg-white max-w-2xl">
          <DialogHeader>
            <DialogTitle className="text-gray-900">
              {editingClient ? "Edit Client" : "Create Client"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-sm text-gray-600 font-medium">First Name *</label>
                <Input
                  className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                  value={formData.first_name || ""}
                  onChange={(e) => setFormData({ ...formData, first_name: e.target.value })}
                  required
                />
              </div>
              <div>
                <label className="text-sm text-gray-600 font-medium">Last Name *</label>
                <Input
                  className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                  value={formData.last_name || ""}
                  onChange={(e) => setFormData({ ...formData, last_name: e.target.value })}
                  required
                />
              </div>
              <div>
                <label className="text-sm text-gray-600 font-medium">Email *</label>
                <Input
                  type="email"
                  className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                  value={formData.email || ""}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  required
                />
              </div>
              <div>
                <label className="text-sm text-gray-600 font-medium">Phone</label>
                <Input
                  className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                  value={formData.phone || ""}
                  onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                />
              </div>
              <div>
                <label className="text-sm text-gray-600 font-medium">Company</label>
                <Input
                  className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                  value={formData.company || ""}
                  onChange={(e) => setFormData({ ...formData, company: e.target.value })}
                />
              </div>
              <div>
                <label className="text-sm text-gray-600 font-medium">Address</label>
                <Input
                  className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                  value={formData.address || ""}
                  onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                />
              </div>
            </div>
            <div>
              <label className="text-sm text-gray-600 font-medium">Notes</label>
              <Textarea
                className="mt-1 bg-white border-gray-300 text-gray-900 focus:border-purple-500 focus:ring-purple-500/20"
                value={formData.notes || ""}
                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                rows={3}
              />
            </div>
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="bg-gradient-to-r from-purple-600 to-blue-600 text-white hover:from-purple-700 hover:to-blue-700" disabled={createClient.isPending || updateClient.isPending}>
                {editingClient ? "Update" : "Create"}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <ConfirmDialog
        open={!!deletingClient}
        onOpenChange={(open) => !open && setDeletingClient(null)}
        onConfirm={handleDelete}
        title="Delete Client"
        description={`Are you sure you want to delete "${deletingClient?.first_name} ${deletingClient?.last_name}"?`}
      />
    </div>
  );
}

