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


namespace App\Emails\LegacyHandler;

use App\Data\Entity\Record;
use App\Data\Service\RecordProviderInterface;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use PHPMailer\PHPMailer\Exception;
use Symfony\Component\HttpFoundation\RequestStack;

class EmailProcessProcessor extends LegacyHandler
{

    protected SendEmailHandler $sendEmailHandler;
    protected RecordProviderInterface $recordProvider;

    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $requestStack,
        SendEmailHandler $sendEmailHandler,
        RecordProviderInterface $recordProvider
    ) {
        parent::__construct(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScopeState,
            $requestStack
        );
        $this->sendEmailHandler = $sendEmailHandler;
        $this->recordProvider = $recordProvider;
    }

    public function getHandlerKey(): string
    {
        return 'email-process-processor';
    }

    /**
     * @param Record $emailRecord
     * @param bool $isTest
     * @return array
     * @throws Exception
     */
    public function processEmail(Record $emailRecord, bool $isTest = false): array
    {
        $this->init();
        $this->startLegacyApp();

        $emailAttributes = $emailRecord->getAttributes() ?? [];
        $validationErrors = $this->validateInput($emailAttributes);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        /** @var \OutboundEmailAccounts $outboundEmail */
        $outboundEmail = \BeanFactory::getBean('OutboundEmailAccounts', $emailAttributes['outbound_email_id']);

        if (empty($outboundEmail)) {
            $this->close();
            return [
                'success' => false,
                'message' => 'Outbound email not found'
            ];
        }

        $outboundRecord = $this->recordProvider->mapToRecord($outboundEmail);

        $success = false;
        try {
            $success = $this->sendEmailHandler->sendEmail($emailRecord, $outboundRecord, $isTest);
        } catch (\Exception $e) {

        }

        if (!$success) {
            $this->close();
            return [
                'success' => false,
                'message' => 'Unable to send email'
            ];
        }

        $this->recordProvider->saveRecord($emailRecord);
        $this->close();
        return [
            'success' => true,
            'message' => ''
        ];
    }

    protected function validateInput(array $emailAttributes): array
    {
        if (empty($emailAttributes['to_addrs_names']) && empty($emailAttributes['cc_addrs_names']) && empty($emailAttributes['bcc_addrs_names'])) {
            $this->close();
            return [
                'success' => false,
                'message' => 'No email addresses provided'
            ];
        }

        if (empty($emailAttributes['outbound_email_id'] ?? '')) {
            $this->close();
            return [
                'success' => false,
                'message' => 'No outbound email provided'
            ];
        }

        if (empty($emailAttributes['outbound_email_id'] ?? '')) {
            $this->close();
            return [
                'success' => false,
                'message' => 'No outbound email provided'
            ];
        }

        return [];
    }


}
