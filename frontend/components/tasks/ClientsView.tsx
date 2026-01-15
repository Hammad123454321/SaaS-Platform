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
    return <div className="text-center py-12 text-gray-400">Loading clients...</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold text-white">Clients</h2>
        {canCreateProject && (
          <Button onClick={() => { setShowForm(true); setEditingClient(null); resetForm(); }} className="glass">
            <Plus className="h-4 w-4 mr-2" />
            Create Client
          </Button>
        )}
      </div>

      {clients && clients.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {clients.map((client) => (
            <div key={client.id} className="glass rounded-lg p-4 space-y-2">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <h3 className="font-semibold text-white">
                    {client.first_name} {client.last_name}
                  </h3>
                  {client.company && (
                    <p className="text-sm text-gray-300/80">{client.company}</p>
                  )}
                  {client.email && (
                    <p className="text-xs text-gray-400">{client.email}</p>
                  )}
                  {client.phone && (
                    <p className="text-xs text-gray-400">{client.phone}</p>
                  )}
                </div>
                {canCreateProject && (
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => handleEdit(client)}
                      className="h-8 w-8"
                    >
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeletingClient(client)}
                      className="h-8 w-8 text-red-400"
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
        <div className="glass rounded-lg p-12 text-center">
          <Users className="h-12 w-12 mx-auto mb-4 text-gray-400" />
          <p className="text-gray-400">No clients yet. Create your first client to get started.</p>
        </div>
      )}

      {/* Create/Edit Form */}
      <Dialog open={showForm} onOpenChange={setShowForm}>
        <DialogContent className="glass max-w-2xl">
          <DialogHeader>
            <DialogTitle className="text-white">
              {editingClient ? "Edit Client" : "Create Client"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label className="text-sm text-gray-200/80">First Name *</label>
                <Input
                  className="mt-1 bg-white/5 border-white/10 text-white"
                  value={formData.first_name || ""}
                  onChange={(e) => setFormData({ ...formData, first_name: e.target.value })}
                  required
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Last Name *</label>
                <Input
                  className="mt-1 bg-white/5 border-white/10 text-white"
                  value={formData.last_name || ""}
                  onChange={(e) => setFormData({ ...formData, last_name: e.target.value })}
                  required
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Email *</label>
                <Input
                  type="email"
                  className="mt-1 bg-white/5 border-white/10 text-white"
                  value={formData.email || ""}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  required
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Phone</label>
                <Input
                  className="mt-1 bg-white/5 border-white/10 text-white"
                  value={formData.phone || ""}
                  onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Company</label>
                <Input
                  className="mt-1 bg-white/5 border-white/10 text-white"
                  value={formData.company || ""}
                  onChange={(e) => setFormData({ ...formData, company: e.target.value })}
                />
              </div>
              <div>
                <label className="text-sm text-gray-200/80">Address</label>
                <Input
                  className="mt-1 bg-white/5 border-white/10 text-white"
                  value={formData.address || ""}
                  onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                />
              </div>
            </div>
            <div>
              <label className="text-sm text-gray-200/80">Notes</label>
              <Textarea
                className="mt-1 bg-white/5 border-white/10 text-white"
                value={formData.notes || ""}
                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                rows={3}
              />
            </div>
            <div className="flex gap-2 justify-end">
              <Button type="button" variant="outline" onClick={() => { setShowForm(false); resetForm(); }}>
                Cancel
              </Button>
              <Button type="submit" className="glass" disabled={createClient.isPending || updateClient.isPending}>
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

