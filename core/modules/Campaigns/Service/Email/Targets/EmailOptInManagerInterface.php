<?php

namespace App\Module\Campaigns\Service\Email\Targets;

use App\Data\Entity\Record;

interface EmailOptInManagerInterface
{
    public function isOptedIn(Record $targetRecord, Record $marketingRecord, string $campaignId, string $prospectListId): bool;
}
