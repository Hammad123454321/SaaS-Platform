"""
Document Management Service

Handles document uploads, versioning, categories, folders, and document library.
"""
import logging
import os
import re
import uuid
from pathlib import Path
from typing import Optional, List, Dict, Any
from datetime import datetime

from beanie import PydanticObjectId
from fastapi import HTTPException, status, UploadFile

from app.models import Task, Project
from app.models.tasks import TaskAttachment, DocumentFolder, DocumentCategory

logger = logging.getLogger(__name__)


# ========== Document Operations ==========
async def upload_document(
    tenant_id: str,
    user_id: str,
    file: UploadFile,
    task_id: Optional[str] = None,
    project_id: Optional[str] = None,
    folder_id: Optional[str] = None,
    category_id: Optional[str] = None,
    description: Optional[str] = None,
    tags: Optional[List[str]] = None
) -> TaskAttachment:
    """Upload a document/file."""
    # Verify task or project exists
    if task_id:
        task = await Task.find_one(
            Task.id == PydanticObjectId(task_id),
            Task.tenant_id == tenant_id
        )
        if not task:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Task not found"
            )
        project_id = task.project_id
    elif project_id:
        project = await Project.find_one(
            Project.id == PydanticObjectId(project_id),
            Project.tenant_id == tenant_id
        )
        if not project:
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
        folder = await DocumentFolder.find_one(
            DocumentFolder.id == PydanticObjectId(folder_id),
            DocumentFolder.tenant_id == tenant_id
        )
        if not folder:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Folder not found"
            )
    
    # Verify category if provided
    if category_id:
        category = await DocumentCategory.find_one(
            DocumentCategory.id == PydanticObjectId(category_id),
            DocumentCategory.tenant_id == tenant_id
        )
        if not category:
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
            detail="File size exceeds maximum allowed size of 50MB"
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
    await document.insert()
    return document


async def create_document_version(
    tenant_id: str,
    document_id: str,
    user_id: str,
    file: UploadFile
) -> TaskAttachment:
    """Create a new version of an existing document."""
    original = await TaskAttachment.find_one(
        TaskAttachment.id == PydanticObjectId(document_id),
        TaskAttachment.tenant_id == tenant_id
    )
    if not original:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Document not found"
        )
    
    # Mark old version as not current
    original.is_current_version = False
    await original.save()
    
    # Upload new version
    new_version = await upload_document(
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
    new_version.parent_id = str(original.id)
    new_version.version = original.version + 1
    new_version.is_current_version = True
    await new_version.save()
    
    return new_version


async def list_documents(
    tenant_id: str,
    task_id: Optional[str] = None,
    project_id: Optional[str] = None,
    folder_id: Optional[str] = None,
    category_id: Optional[str] = None,
    search: Optional[str] = None,
    include_versions: bool = False
) -> List[TaskAttachment]:
    """List documents with filters."""
    conditions = [TaskAttachment.tenant_id == tenant_id]
    
    if not include_versions:
        conditions.append(TaskAttachment.is_current_version == True)
    
    if task_id:
        conditions.append(TaskAttachment.task_id == task_id)
    
    if project_id:
        conditions.append(TaskAttachment.project_id == project_id)
    
    if folder_id:
        conditions.append(TaskAttachment.folder_id == folder_id)
    
    if category_id:
        conditions.append(TaskAttachment.category_id == category_id)
    
    if search:
        search_regex = re.compile(f".*{re.escape(search)}.*", re.IGNORECASE)
        conditions.append({
            "$or": [
                {"original_filename": {"$regex": search_regex}},
                {"description": {"$regex": search_regex}}
            ]
        })
    
    return await TaskAttachment.find(*conditions).sort(-TaskAttachment.created_at).to_list()


async def get_document_versions(tenant_id: str, document_id: str) -> List[TaskAttachment]:
    """Get all versions of a document."""
    document = await TaskAttachment.find_one(
        TaskAttachment.id == PydanticObjectId(document_id),
        TaskAttachment.tenant_id == tenant_id
    )
    if not document:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Document not found"
        )
    
    # Get root document (original)
    root_id = document.parent_id if document.parent_id else str(document.id)
    
    # Get all versions
    return await TaskAttachment.find(
        TaskAttachment.tenant_id == tenant_id,
        {"$or": [
            {"_id": PydanticObjectId(root_id)},
            {"parent_id": root_id}
        ]}
    ).sort(+TaskAttachment.version).to_list()


async def delete_document(tenant_id: str, document_id: str) -> None:
    """Delete a document (and all versions if current)."""
    document = await TaskAttachment.find_one(
        TaskAttachment.id == PydanticObjectId(document_id),
        TaskAttachment.tenant_id == tenant_id
    )
    if not document:
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
        root_id = document.parent_id if document.parent_id else str(document.id)
        versions = await get_document_versions(tenant_id, root_id)
        for version in versions:
            if os.path.exists(version.file_path):
                try:
                    os.remove(version.file_path)
                except Exception as e:
                    logger.warning(f"Failed to delete file {version.file_path}: {e}")
            await version.delete()
    else:
        await document.delete()


# ========== Folder Operations ==========
async def create_folder(
    tenant_id: str,
    project_id: Optional[str],
    name: str,
    description: Optional[str] = None,
    parent_id: Optional[str] = None
) -> DocumentFolder:
    """Create a document folder."""
    if project_id:
        project = await Project.find_one(
            Project.id == PydanticObjectId(project_id),
            Project.tenant_id == tenant_id
        )
        if not project:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Project not found"
            )
    
    if parent_id:
        parent = await DocumentFolder.find_one(
            DocumentFolder.id == PydanticObjectId(parent_id),
            DocumentFolder.tenant_id == tenant_id
        )
        if not parent:
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
    await folder.insert()
    return folder


async def list_folders(
    tenant_id: str,
    project_id: Optional[str] = None,
    parent_id: Optional[str] = None
) -> List[DocumentFolder]:
    """List folders."""
    conditions = [DocumentFolder.tenant_id == tenant_id]
    
    if project_id:
        conditions.append(DocumentFolder.project_id == project_id)
    
    if parent_id is not None:
        conditions.append(DocumentFolder.parent_id == parent_id)
    
    return await DocumentFolder.find(*conditions).sort(+DocumentFolder.name).to_list()


# ========== Category Operations ==========
async def create_category(
    tenant_id: str,
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
    await category.insert()
    return category


async def list_categories(tenant_id: str) -> List[DocumentCategory]:
    """List all categories for a tenant."""
    return await DocumentCategory.find(
        DocumentCategory.tenant_id == tenant_id
    ).sort(+DocumentCategory.name).to_list()
