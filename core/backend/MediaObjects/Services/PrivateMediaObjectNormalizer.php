<?php
/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2025 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SALESAGILITY, SALESAGILITY DISCLAIMS THE
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

namespace App\MediaObjects\Services;

use App\MediaObjects\Entity\ArchivedDocumentMediaObject;
use App\MediaObjects\Entity\PrivateDocumentMediaObject;
use App\MediaObjects\Entity\PrivateImageMediaObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PrivateMediaObjectNormalizer implements NormalizerInterface
{

    private const ALREADY_CALLED = 'MEDIA_OBJECT_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        #[Autowire(service: 'api_platform.jsonld.normalizer.item')]
        private readonly NormalizerInterface $normalizer
    ) {
    }

    public function normalize($object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;

        if (!empty($object?->id)) {
            $prefix = $this->getPath($object);

            $object->contentUrl = $prefix . $object->id;
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {

        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        foreach ($this->getSupportedTypes($format) as $type => $supported) {
            if ($data instanceof $type) {
                return $supported;
            }
        }

        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ArchivedDocumentMediaObject::class => true,
            PrivateDocumentMediaObject::class => true,
            PrivateImageMediaObject::class => true,
        ];
    }

    /**
     * @param mixed $object
     * @return string
     */
    protected function getPath(mixed $object): string
    {
        $prefixMap = [
            ArchivedDocumentMediaObject::class => '/media/archived/',
            PrivateDocumentMediaObject::class => '/media/documents/',
            PrivateImageMediaObject::class => '/media/images/',
        ];

        foreach ($prefixMap as $type => $prefix) {
            if ($object instanceof $type) {
                return $prefix;
            }
        }

        return 'media';
    }
}
