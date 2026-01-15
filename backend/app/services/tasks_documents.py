"""
Document Management Service

Handles document uploads, versioning, categories, folders, and document library.
"""
import logging
import os
import uuid
from pathlib import Path
from typing import Optional, List, Dict, Any
from datetime import datetime

from sqlmodel import Session, select, and_, or_
from fastapi import HTTPException, status, UploadFile

from app.models import Task, Project
from app.models.tasks import TaskAttachment, DocumentFolder, DocumentCategory

logger = logging.getLogger(__name__)


# ========== Document Operations ==========
async def upload_document(
    session: Session,
    tenant_id: int,
    user_id: int,
    file: UploadFile,
    task_id: Optional[int] = None,
    project_id: Optional[int] = None,
    folder_id: Optional[int] = None,
    category_id: Optional[int] = None,
    description: Optional[str] = None,
    tags: Optional[List[str]] = None
) -> TaskAttachment:
    """Upload a document/file."""
    # Verify task or project exists
    if task_id:
        task = session.get(Task, task_id)
        if not task or task.tenant_id != tenant_id:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Task not found"
            )
        project_id = task.project_id
    elif project_id:
        project = session.get(Project, project_id)
        if not project or project.tenant_id != tenant_id:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Project not found"
            )
    else:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Either task_id or project_id is required"
        )
    
    # Verify folder if provided
    if folder_id:
        folder = session.get(DocumentFolder, folder_id)
        if not folder or folder.tenant_id != tenant_id:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Folder not found"
            )
    
    # Verify category if provided
    if category_id:
        category = session.get(DocumentCategory, category_id)
        if not category or category.tenant_id != tenant_id:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Category not found"
            )
    
    # Read file content
    file_content = await file.read()
    file_size = len(file_content)
    
    # Validate file size (50MB limit)
    max_size = 50 * 1024 * 1024
    if file_size > max_size:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"File size exceeds maximum allowed size of 50MB"
        )
    
    # Generate unique filename
    file_ext = Path(file.filename).suffix if file.filename else ""
    unique_filename = f"{uuid.uuid4()}{file_ext}"
    
    # Create uploads directory
    upload_dir = Path(f"uploads/tenants/{tenant_id}/documents")
    upload_dir.mkdir(parents=True, exist_ok=True)
    
    # Save file
    file_path = upload_dir / unique_filename
    with open(file_path, "wb") as f:
        f.write(file_content)
    
    # Get MIME type
    import mimetypes
    mime_type, _ = mimetypes.guess_type(file.filename or "")
    mime_type = mime_type or "application/octet-stream"
    
    # Create document record
    document = TaskAttachment(
        tenant_id=tenant_id,
        task_id=task_id,
        project_id=project_id,
        user_id=user_id,
        folder_id=folder_id,
        category_id=category_id,
        filename=unique_filename,
        original_filename=file.filename or "upload",
        file_path=str(file_path),
        file_size=file_size,
        mime_type=mime_type,
        description=description,
        tags=tags or [],
        version=1,
        is_current_version=True
    )
    session.add(document)
    session.commit()
    session.refresh(document)
    return document


async def create_document_version(
    session: Session,
    tenant_id: int,
    document_id: int,
    user_id: int,
    file: UploadFile
) -> TaskAttachment:
    """Create a new version of an existing document."""
    original = session.get(TaskAttachment, document_id)
    if not original or original.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Document not found"
        )
    
    # Mark old version as not current
    original.is_current_version = False
    session.add(original)
    
    # Upload new version
    new_version = await upload_document(
        session,
        tenant_id,
        user_id,
        file,
        task_id=original.task_id,
        project_id=original.project_id,
        folder_id=original.folder_id,
        category_id=original.category_id,
        description=original.description,
        tags=original.tags
    )
    
    # Set version info
    new_version.parent_id = original.id
    new_version.version = original.version + 1
    new_version.is_current_version = True
    
    session.add(new_version)
    session.commit()
    session.refresh(new_version)
    return new_version


def list_documents(
    session: Session,
    tenant_id: int,
    task_id: Optional[int] = None,
    project_id: Optional[int] = None,
    folder_id: Optional[int] = None,
    category_id: Optional[int] = None,
    search: Optional[str] = None,
    include_versions: bool = False
) -> List[TaskAttachment]:
    """List documents with filters."""
    query = select(TaskAttachment).where(
        and_(
            TaskAttachment.tenant_id == tenant_id,
            TaskAttachment.is_current_version == True if not include_versions else True
        )
    )
    
    if task_id:
        query = query.where(TaskAttachment.task_id == task_id)
    
    if project_id:
        query = query.where(TaskAttachment.project_id == project_id)
    
    if folder_id:
        query = query.where(TaskAttachment.folder_id == folder_id)
    
    if category_id:
        query = query.where(TaskAttachment.category_id == category_id)
    
    if search:
        query = query.where(
            or_(
                TaskAttachment.original_filename.ilike(f"%{search}%"),
                TaskAttachment.description.ilike(f"%{search}%")
            )
        )
    
    return list(session.exec(query.order_by(TaskAttachment.created_at.desc())).all())


def get_document_versions(session: Session, tenant_id: int, document_id: int) -> List[TaskAttachment]:
    """Get all versions of a document."""
    document = session.get(TaskAttachment, document_id)
    if not document or document.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Document not found"
        )
    
    # Get root document (original)
    root_id = document.parent_id if document.parent_id else document.id
    
    # Get all versions
    return list(
        session.exec(
            select(TaskAttachment).where(
                and_(
                    TaskAttachment.tenant_id == tenant_id,
                    or_(
                        TaskAttachment.id == root_id,
                        TaskAttachment.parent_id == root_id
                    )
                )
            ).order_by(TaskAttachment.version.asc())
        ).all()
    )


def delete_document(session: Session, tenant_id: int, document_id: int) -> None:
    """Delete a document (and all versions if current)."""
    document = session.get(TaskAttachment, document_id)
    if not document or document.tenant_id != tenant_id:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Document not found"
        )
    
    # Delete file from disk
    if os.path.exists(document.file_path):
        try:
            os.remove(document.file_path)
        except Exception as e:
            logger.warning(f"Failed to delete file {document.file_path}: {e}")
    
    # If current version, delete all versions
    if document.is_current_version:
        root_id = document.parent_id if document.parent_id else document.id
        versions = get_document_versions(session, tenant_id, root_id)
        for version in versions:
            if os.path.exists(version.file_path):
                try:
                    os.remove(version.file_path)
                except Exception as e:
                    logger.warning(f"Failed to delete file {version.file_path}: {e}")
            session.delete(version)
    else:
        session.delete(document)
    
    session.commit()


# ========== Folder Operations ==========
def create_folder(
    session: Session,
    tenant_id: int,
    project_id: Optional[int],
    name: str,
    description: Optional[str] = None,
    parent_id: Optional[int] = None
) -> DocumentFolder:
    """Create a document folder."""
    if project_id:
        project = session.get(Project, project_id)
        if not project or project.tenant_id != tenant_id:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Project not found"
            )
    
    if parent_id:
        parent = session.get(DocumentFolder, parent_id)
        if not parent or parent.tenant_id != tenant_id:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Parent folder not found"
            )
    
    folder = DocumentFolder(
        tenant_id=tenant_id,
        project_id=project_id,
        parent_id=parent_id,
        name=name,
        description=description
    )
    session.add(folder)
    session.commit()
    session.refresh(folder)
    return folder


def list_folders(
    session: Session,
    tenant_id: int,
    project_id: Optional[int] = None,
    parent_id: Optional[int] = None
) -> List[DocumentFolder]:
    """List folders."""
    query = select(DocumentFolder).where(DocumentFolder.tenant_id == tenant_id)
    
    if project_id:
        query = query.where(DocumentFolder.project_id == project_id)
    
    if parent_id is not None:
        query = query.where(DocumentFolder.parent_id == parent_id)
    
    return list(session.exec(query.order_by(DocumentFolder.name)).all())


# ========== Category Operations ==========
def create_category(
    session: Session,
    tenant_id: int,
    name: str,
    color: Optional[str] = None,
    description: Optional[str] = None
) -> DocumentCategory:
    """Create a document category."""
    category = DocumentCategory(
        tenant_id=tenant_id,
        name=name,
        color=color or "#6b7280",
        description=description
    )
    session.add(category)
    session.commit()
    session.refresh(category)
    return category


def list_categories(session: Session, tenant_id: int) -> List[DocumentCategory]:
    """List all categories for a tenant."""
    return list(
        session.exec(
            select(DocumentCategory).where(
                DocumentCategory.tenant_id == tenant_id
            ).order_by(DocumentCategory.name)
        ).all()
    )

