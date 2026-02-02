from __future__ import annotations

from datetime import datetime
from typing import List

from fastapi import HTTPException, status

from app.models.pos import CustomerFeedback, FeedbackStatus


async def create_feedback(tenant_id: str, payload) -> CustomerFeedback:
    feedback = CustomerFeedback(
        tenant_id=tenant_id,
        sale_id=payload.sale_id,
        customer_id=payload.customer_id,
        rating=payload.rating,
        comment=payload.comment,
        status=FeedbackStatus.NEW,
    )
    await feedback.insert()
    return feedback


async def list_feedback(tenant_id: str) -> List[CustomerFeedback]:
    return await CustomerFeedback.find(CustomerFeedback.tenant_id == tenant_id).sort(-CustomerFeedback.created_at).to_list()


async def respond_feedback(tenant_id: str, feedback_id: str, user_id: str, payload) -> CustomerFeedback:
    feedback = await CustomerFeedback.get(feedback_id)
    if not feedback or feedback.tenant_id != tenant_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Feedback not found")
    feedback.response = payload.response
    feedback.status = payload.status
    feedback.responded_by_user_id = user_id
    feedback.responded_at = datetime.utcnow()
    await feedback.save()
    return feedback
