<?php

namespace App\FieldDefinitions\Service;

use App\FieldDefinitions\Entity\FieldDefinition;

interface VardefConfigMapperInterface
{
    /**
     * Get the mapper key
     * @return string
     */
    public function getKey(): string;

    /**
     * Get the module key
     * @return string
     */
    public function getModule(): string;

    /**
     * Map array
     * @param array $vardef
     * @return array
     */
    public function map(array $vardefs): array;
}
