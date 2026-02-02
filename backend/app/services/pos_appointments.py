from __future__ import annotations

from datetime import datetime
from typing import List, Optional

from fastapi import HTTPException, status

from app.models.pos import Appointment, AppointmentStatus


async def create_appointment(tenant_id: str, user_id: str, payload) -> Appointment:
    appointment = Appointment(
        tenant_id=tenant_id,
        customer_id=payload.customer_id,
        service_product_id=payload.service_product_id,
        assigned_to_user_id=payload.assigned_to_user_id,
        location_id=payload.location_id,
        status=payload.status,
        start_at=payload.start_at,
        end_at=payload.end_at,
        notes=payload.notes,
        created_by_user_id=user_id,
    )
    await appointment.insert()
    return appointment


async def list_appointments(
    tenant_id: str,
    status_filter: Optional[AppointmentStatus] = None,
) -> List[Appointment]:
    conditions = [Appointment.tenant_id == tenant_id]
    if status_filter:
        conditions.append(Appointment.status == status_filter)
    return await Appointment.find(*conditions).sort(Appointment.start_at).to_list()


async def update_appointment(tenant_id: str, appointment_id: str, payload) -> Appointment:
    appointment = await Appointment.get(appointment_id)
    if not appointment or appointment.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Appointment not found")

    if payload.status:
        appointment.status = payload.status
    if payload.start_at:
        appointment.start_at = payload.start_at
    if payload.end_at:
        appointment.end_at = payload.end_at
    if payload.assigned_to_user_id is not None:
        appointment.assigned_to_user_id = payload.assigned_to_user_id
    if payload.notes is not None:
        appointment.notes = payload.notes
    await appointment.save()
    return appointment
