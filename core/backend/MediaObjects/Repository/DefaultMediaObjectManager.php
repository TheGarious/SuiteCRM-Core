<?php
/**
 * SuiteCRM is a customer relationship management program developed by SuiteCRM Ltd.
 * Copyright (C) 2025 SuiteCRM Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUITECRM, SUITECRM DISCLAIMS THE
 * WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License
 * version 3, these Appropriate Legal Notices must retain the display of the
 * "Supercharged by SuiteCRM" logo. If the display of the logos is not reasonably
 * feasible for technical reasons, the Appropriate Legal Notices must display
 * the words "Supercharged by SuiteCRM".
 */

namespace App\MediaObjects\Repository;

use App\Data\Entity\Record;
use App\Data\Service\Record\Repository\RecordEntityRepository;
use App\MediaObjects\Entity\MediaObjectInterface;

class DefaultMediaObjectManager implements MediaObjectManagerInterface
{
    protected array $typeMap = [];

    public function __construct(
        protected ArchivedDocumentMediaObjectRepository $archivedDocumentRepository,
        protected PrivateDocumentMediaObjectRepository $privateDocumentRepository,
        protected PrivateImageMediaObjectRepository $privateImageRepository,
        protected PublicDocumentMediaObjectRepository $publicDocumentRepository,
        protected PublicImageMediaObjectRepository $publicImageRepository
    ) {

        $this->typeMap = [
            'archived-document' => $archivedDocumentRepository,
            'private-document' => $privateDocumentRepository,
            'private-image' => $privateImageRepository,
            'public-document' => $publicDocumentRepository,
            'public-image' => $publicImageRepository,
        ];
    }

    /**
     * Returns the repository for the given type.
     *
     * @param string $type The type of media object (e.g., 'archived-document', 'private-document', etc.)
     * @return RecordEntityRepository|null The repository instance or null if not found
     */
    public function getRepository(string $type): ?RecordEntityRepository
    {
        return $this->typeMap[$type] ?? null;
    }

    /**
     * Returns a media object by its type and ID.
     *
     * @param string $type The type of media object (e.g., 'archived-document', 'private-document', etc.)
     * @param string $id The ID of the media object
     * @return MediaObjectInterface|null The media object instance or null if not found
     */
    public function getMediaObject(string $type, string $id): ?MediaObjectInterface
    {
        $repository = $this->getRepository($type);
        if ($repository) {
            return $repository->find($id);
        }
        return null;
    }

    /**
     * Saves a media object to the appropriate repository based on its type.
     *
     * @param string $type The type of media object (e.g., 'archived-document', 'private-document', etc.)
     * @param MediaObjectInterface $mediaObject The media object to save
     */
    public function saveMediaObject(string $type, MediaObjectInterface $mediaObject): void
    {
        $repository = $this->getRepository($type);
        if ($repository) {
            $repository->save($mediaObject);
        }
    }

    /**
     * Deletes a media object from the appropriate repository based on its type.
     *
     * @param string $type The type of media object (e.g., 'archived-document', 'private-document', etc.)
     * @param MediaObjectInterface $mediaObject The media object to delete
     */
    public function deleteMediaObject(string $type, MediaObjectInterface $mediaObject): void
    {
        $repository = $this->getRepository($type);
        if ($repository) {
            $repository->remove($mediaObject);
        }
    }


    /**
     * Sets the parent type and ID for a media object.
     *
     * @param string $type The type of media object (e.g., 'archived-document', 'private-document', etc.)
     * @param MediaObjectInterface $mediaObject The media object to link
     * @param string $parentType The type of the parent object
     * @param string $parentId The ID of the parent object
     */
    public function linkParent(string $type, MediaObjectInterface $mediaObject, string $parentType, string $parentId): void
    {
        $repository = $this->getRepository($type);

        if (!$repository || !$mediaObject instanceof MediaObjectInterface) {
            return;
        }

        $mediaObject->setParentType($parentType);
        $mediaObject->setParentId($parentId);

        $repository->save($mediaObject);
    }

    /**
     * Returns all media objects linked to a parent object.
     *
     * @param string $type The type of media object (e.g., 'archived-document', 'private-document', etc.)
     * @param string $parentType The type of the parent object
     * @param string $parentId The ID of the parent object
     * @return MediaObjectInterface[] An array of linked media objects
     */
    public function getLinkedMediaObjects(string $type, string $parentType, string $parentId): array
    {
        $repository = $this->getRepository($type);
        if ($repository) {
            return $repository->findBy(['parentType' => $parentType, 'parentId' => $parentId, 'deleted' => 0]);
        }
        return [];
    }

    /**
     * Sets the parent type and ID for a media object.
     *
     * @param string $type The type of media object (e.g., 'archived-document', 'private-document', etc.)
     * @param string $id The ID of the media object
     * @param string $parentType The type of the parent object
     * @param string $parentId The ID of the parent object
     */
    public function linkParentById(string $type, string $id, string $parentType, string $parentId): void
    {
        $repository = $this->getRepository($type);

        if (!$repository) {
            return;
        }

        $mediaObject = $this->getMediaObject($type, $id);
        if (!$mediaObject) {
            return;
        }

        $mediaObject->setParentType($parentType);
        $mediaObject->setParentId($parentId);

        $repository->save($mediaObject);
    }

    /**
     * Maps a media object to a record.
     *
     * @param MediaObjectInterface|null $mediaObject The record to map
     * @return Record|null
     */
    public function mapToRecord(?MediaObjectInterface $mediaObject): ?Record
    {
        if (!$mediaObject instanceof MediaObjectInterface) {
            return null;
        }

        $record = new Record();
        $record->setId($mediaObject->getId());
        $record->setModule('media-objects');
        $record->setType('media-objects');
        $record->setAttributes(
            [
                'id' => $mediaObject->getId(),
                'name' => $mediaObject->getName(),
                'file_path' => $mediaObject->getFilePath(),
                'size' => $mediaObject->getSize(),
                'mime_type' => $mediaObject->getMimeType(),
                'original_name' => $mediaObject->getOriginalName(),
                'dimensions' => $mediaObject->getDimensions(),
                'parent_type' => $mediaObject->getParentType(),
                'parent_id' => $mediaObject->getParentId(),
                'temporary' => $mediaObject->getTemporary(),
                'content_url' => $mediaObject->getContentUrl(),
                'date_entered' => $mediaObject->getDateEntered(),
                'date_modified' => $mediaObject->getDateModified(),
                'created_by' => $mediaObject->getCreatedBy(),
                'modified_user_id' => $mediaObject->getModifiedUserId(),
                'deleted' => $mediaObject->isDeleted(),
                'module' => 'MediaObjects'
            ]
        );

        return $record;
    }

    /**
     * Synchronizes related records for a parent object.
     *
     * @param string $type The type of media object (e.g., 'archived-document', 'private-document', etc.)
     * @param Record $parent The parent record to which the media objects are linked
     * @param Record[] $records An array of records to sync with the parent
     */
    public function syncLinkedMediaObjects(string $type, Record $parent, array $records): void
    {
        $repository = $this->getRepository($type);

        if (!$repository) {
            return;
        }

        $parentType = $parent->getAttributes()['module_name'] ?? '';
        $parentId = $parent->getId();

        $records = $records ?? [];
        $relatedRecordIds = [];
        $relatedMediaObjects = $this->getLinkedMediaObjects($type, $parentType, $parentId) ?? [];

        foreach ($relatedMediaObjects as $mediaObject) {
            $id = $mediaObject->getId();
            if (!empty($id)) {
                $relatedRecordIds[$id] = true;
            }
        }

        $submittedRecordIds = [];
        foreach ($records as $record) {
            $id = $record->getId();

            $mediaObject = $this->getMediaObject($type, $id);

            if (!$mediaObject) {
                continue;
            }

            $submittedRecordIds[$record->getId()] = true;

            if (empty($relatedRecordIds[$id])) {
                $this->linkParent($type, $mediaObject, $parentType, $parentId);
            }
        }

        foreach ($relatedMediaObjects as $mediaObject) {
            $id = $mediaObject->getId();
            if (empty($submittedRecordIds[$id])) {
                $this->deleteMediaObject($type, $mediaObject);
            }
        }
    }
}
