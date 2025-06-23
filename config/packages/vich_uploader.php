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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;


return static function (ContainerConfigurator $containerConfig) {
    $env = $_ENV ?? [];

    $awsS3Version = $env['AWS_S3_VERSION'] ?? '2006-03-01';
    if (empty($awsS3Version)) {
        $awsS3Version = '2006-03-01';
    }

    $awsS3Region = $env['AWS_S3_ACCESS_REGION'] ?? '';
    if (empty($awsS3Region)) {
        $awsS3Region = '';
    }

    if (!empty($env['AWS_S3_ACCESS_KEY'])) {
        $awsS3AccessKey = $env['AWS_S3_ACCESS_KEY'];
    } else {
        $awsS3AccessKey = '%env(AWS_S3_ACCESS_KEY)%';
    }

    if (!empty($env['AWS_S3_ACCESS_SECRET'])) {
        $awsS3AccessSecret = $env['AWS_S3_ACCESS_SECRET'];
    } else {
        $awsS3AccessSecret = '%env(AWS_S3_ACCESS_SECRET)%';
    }

    $s3ClientArgs = [];

    if (!empty($awsS3Version)) {
        $s3ClientArgs['version'] = $awsS3Version;
    }

    if (!empty($awsS3Region)) {
        $s3ClientArgs['region'] = $awsS3Region;
    }

    $s3ClientArgs['credentials'] = [
        'key' => $awsS3AccessKey,
        'secret' => $awsS3AccessSecret,
    ];

    if (!empty($s3ClientArgs)) {
        $containerConfig->services()->set('app_aws_s3_client', 'Aws\S3\S3Client')->public()->args([$s3ClientArgs]);
    }

    $dbDriver = $env['MEDIA_UPLOADER_DB_DRIVER'] ?? 'orm';

    if (empty($dbDriver) || !in_array($dbDriver, ['orm', 'mongodb', 'phpcr'])) {
        $dbDriver = 'orm';
    }

    $storage = $env['MEDIA_UPLOADER_STORAGE'] ?? 'flysystem';

    if (empty($storage) || !in_array($storage, ['file_system', 'flysystem', 'gaufrette'])) {
        $storage = 'flysystem';
    }

    $metadata = $env['MEDIA_UPLOADER_METADATA'] ?? '';

    $defaultMetadata = [
        'auto_detection' => true,
        'cache' => 'file',
        'type' => 'attribute',
    ];

    $decodedMetadata = $metadata ? json_decode($metadata, true, 512, JSON_THROW_ON_ERROR) : [];
    if (!is_array($decodedMetadata)) {
        $decodedMetadata = [];
    }
    $metadata = array_merge($defaultMetadata, $decodedMetadata);


    $mappings = $env['MEDIA_UPLOADER_MAPPINGS'] ?? '';
    $defaultMappings = [
        'archived_document_media_object' => [
            'uri_prefix' => '/media/archived',
            'upload_destination' => 'archived.documents.storage',
            'namer' => 'App\MediaObjects\Services\UuidMediaObjectFileNamer',
            'directory_namer' => [
                'service' => 'Vich\UploaderBundle\Naming\CurrentDateTimeDirectoryNamer',
                'options' => [
                    'date_time_format' => 'Y/m',
                    'date_time_property' => 'dateEntered'
                ]
            ]
        ],
        'private_document_media_object' => [
            'uri_prefix' => '/media/documents',
            'upload_destination' => 'private.documents.storage',
            'namer' => 'App\MediaObjects\Services\UuidMediaObjectFileNamer',
            'directory_namer' => [
                'service' => 'Vich\UploaderBundle\Naming\CurrentDateTimeDirectoryNamer',
                'options' => [
                    'date_time_format' => 'Y/m',
                    'date_time_property' => 'dateEntered'
                ]
            ]
        ],
        'private_image_media_object' => [
            'uri_prefix' => '/media/images',
            'upload_destination' => 'private.images.storage',
            'namer' => 'App\MediaObjects\Services\UuidMediaObjectFileNamer',
            'directory_namer' => [
                'service' => 'Vich\UploaderBundle\Naming\CurrentDateTimeDirectoryNamer',
                'options' => [
                    'date_time_format' => 'Y/m',
                    'date_time_property' => 'dateEntered'
                ]
            ]
        ],
        'public_image_media_object' => [
            'uri_prefix' => '/media-upload/images',
            'upload_destination' => 'public.images.storage',
            'namer' => 'Vich\UploaderBundle\Naming\SmartUniqueNamer',
            'directory_namer' => [
                'service' => 'Vich\UploaderBundle\Naming\CurrentDateTimeDirectoryNamer',
                'options' => [
                    'date_time_format' => 'Y/m',
                    'date_time_property' => 'dateEntered'
                ]
            ]
        ],
        'public_document_media_object' => [
            'uri_prefix' => '/media-upload/documents',
            'upload_destination' => 'public.documents.storage',
            'namer' => 'Vich\UploaderBundle\Naming\SmartUniqueNamer',
            'directory_namer' => [
                'service' => 'Vich\UploaderBundle\Naming\CurrentDateTimeDirectoryNamer',
                'options' => [
                    'date_time_format' => 'Y/m',
                    'date_time_property' => 'dateEntered'
                ]
            ]
        ],
    ];

    $decodedMappings = $mappings ? json_decode($mappings, true, 512, JSON_THROW_ON_ERROR) : [];
    if (!is_array($decodedMappings)) {
        $decodedMappings = [];
    }

    $mappings = array_merge($defaultMappings, $decodedMappings);


    $containerConfig->extension(
        'vich_uploader',
        [
            'db_driver' => $dbDriver,
            'storage' => $storage,
            'metadata' => $metadata,
            'mappings' => $mappings
        ]
    );

};
