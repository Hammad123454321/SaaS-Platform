"use client";

import { useState } from "react";
import { usePosLocations, usePosRegisters } from "@/hooks/usePos";
import { usePosSessionStore } from "@/lib/pos-store";
import { api } from "@/lib/api";
import { parseCurrency } from "@/lib/pos-utils";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";

export default function POSRegisterPage() {
  const { data: locations } = usePosLocations();
  const [locationId, setLocationId] = useState<string | null>(null);
  const { data: registers } = usePosRegisters(locationId || undefined);
  const [registerId, setRegisterId] = useState<string | null>(null);
  const [openingCash, setOpeningCash] = useState(0);
  const [closingCash, setClosingCash] = useState(0);
  const [cashMovementAmount, setCashMovementAmount] = useState(0);
  const [cashMovementType, setCashMovementType] = useState<"paid_in" | "paid_out">("paid_in");
  const [cashMovementReason, setCashMovementReason] = useState("");
  const [newLocationName, setNewLocationName] = useState("");
  const [newLocationCode, setNewLocationCode] = useState("");
  const [newRegisterName, setNewRegisterName] = useState("");

  const posSession = usePosSessionStore();

  const openRegister = async () => {
    if (!registerId) return;
    try {
      const res = await api.post(`/modules/pos/registers/${registerId}/open`, {
        opening_cash_cents: openingCash,
      });
      posSession.setSession({
        locationId: res.data.location_id,
        registerId,
        registerSessionId: res.data.id,
      });
      alert("Register opened");
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to open register");
    }
  };

  const closeRegister = async () => {
    if (!posSession.registerId) return;
    try {
      await api.post(`/modules/pos/registers/${posSession.registerId}/close`, {
        closing_cash_cents: closingCash,
      });
      posSession.clearSession();
      alert("Register closed");
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to close register");
    }
  };

  const recordCashMovement = async () => {
    if (!posSession.registerSessionId) return;
    try {
      await api.post("/modules/pos/registers/cash-movements", {
        register_session_id: posSession.registerSessionId,
        movement_type: cashMovementType,
        amount_cents: cashMovementAmount,
        reason: cashMovementReason,
      });
      alert("Cash movement recorded");
      setCashMovementAmount(0);
      setCashMovementReason("");
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to record cash movement");
    }
  };

  const createLocation = async () => {
    if (!newLocationName || !newLocationCode) return;
    try {
      await api.post("/modules/pos/locations", {
        name: newLocationName,
        code: newLocationCode,
      });
      alert("Location created");
      setNewLocationName("");
      setNewLocationCode("");
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to create location");
    }
  };

  const createRegister = async () => {
    if (!newRegisterName || !locationId) return;
    try {
      await api.post("/modules/pos/registers", {
        location_id: locationId,
        name: newRegisterName,
      });
      alert("Register created");
      setNewRegisterName("");
    } catch (err: any) {
      alert(err?.response?.data?.detail || "Failed to create register");
    }
  };

  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <div className="lg:col-span-2 space-y-4">
        <Card className="p-4 space-y-3">
          <h2 className="text-lg font-semibold text-gray-900">Open Register</h2>
          <Select value={locationId || ""} onValueChange={(value) => setLocationId(value)}>
            <SelectTrigger>
              <SelectValue placeholder="Select location" />
            </SelectTrigger>
            <SelectContent>
              {(locations || []).map((location) => (
                <SelectItem key={location.id} value={location.id}>
                  {location.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Select value={registerId || ""} onValueChange={(value) => setRegisterId(value)}>
            <SelectTrigger>
              <SelectValue placeholder="Select register" />
            </SelectTrigger>
            <SelectContent>
              {(registers || []).map((register) => (
                <SelectItem key={register.id} value={register.id}>
                  {register.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <Input
            placeholder="Opening cash"
            value={openingCash ? (openingCash / 100).toString() : ""}
            onChange={(e) => setOpeningCash(parseCurrency(e.target.value))}
          />
          <Button onClick={openRegister}>Open Register</Button>
        </Card>

        <Card className="p-4 space-y-3">
          <h2 className="text-lg font-semibold text-gray-900">Setup</h2>
          <div className="grid gap-2 sm:grid-cols-2">
            <Input
              placeholder="Location name"
              value={newLocationName}
              onChange={(e) => setNewLocationName(e.target.value)}
            />
            <Input
              placeholder="Location code"
              value={newLocationCode}
              onChange={(e) => setNewLocationCode(e.target.value)}
            />
          </div>
          <Button variant="outline" onClick={createLocation}>
            Create Location
          </Button>

          <Input
            placeholder="Register name"
            value={newRegisterName}
            onChange={(e) => setNewRegisterName(e.target.value)}
          />
          <Button variant="outline" onClick={createRegister} disabled={!locationId}>
            Create Register
          </Button>
        </Card>

        <Card className="p-4 space-y-3">
          <h2 className="text-lg font-semibold text-gray-900">Cash Movements</h2>
          <Select value={cashMovementType} onValueChange={(value) => setCashMovementType(value as any)}>
            <SelectTrigger>
              <SelectValue placeholder="Movement type" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="paid_in">Paid In</SelectItem>
              <SelectItem value="paid_out">Paid Out</SelectItem>
            </SelectContent>
          </Select>
          <Input
            placeholder="Amount"
            value={cashMovementAmount ? (cashMovementAmount / 100).toString() : ""}
            onChange={(e) => setCashMovementAmount(parseCurrency(e.target.value))}
          />
          <Input
            placeholder="Reason"
            value={cashMovementReason}
            onChange={(e) => setCashMovementReason(e.target.value)}
          />
          <Button onClick={recordCashMovement} disabled={!posSession.registerSessionId}>
            Record Movement
          </Button>
        </Card>
      </div>

      <div className="space-y-4">
        <Card className="p-4 space-y-3">
          <h2 className="text-lg font-semibold text-gray-900">Close Register</h2>
          <p className="text-sm text-gray-500">
            Active Session: {posSession.registerSessionId || "None"}
          </p>
          <Input
            placeholder="Closing cash"
            value={closingCash ? (closingCash / 100).toString() : ""}
            onChange={(e) => setClosingCash(parseCurrency(e.target.value))}
          />
          <Button variant="outline" onClick={closeRegister} disabled={!posSession.registerSessionId}>
            Close Register
          </Button>
        </Card>
      </div>
    </div>
  );
}
