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
use App\Data\LegacyHandler\PreparedStatementHandler;
use App\Data\Service\RecordProviderInterface;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use PHPMailer\PHPMailer\Exception;
use SugarEmailAddress;
use Symfony\Component\HttpFoundation\RequestStack;

class EmailProcessProcessor extends LegacyHandler
{

    protected SendEmailHandler $sendEmailHandler;
    protected RecordProviderInterface $recordProvider;
    protected PreparedStatementHandler $preparedStatementHandler;

    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $requestStack,
        SendEmailHandler $sendEmailHandler,
        RecordProviderInterface $recordProvider,
        PreparedStatementHandler $preparedStatementHandler
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
        $this->preparedStatementHandler = $preparedStatementHandler;
    }

    public function getHandlerKey(): string
    {
        return 'email-process-processor';
    }

    /**
     * @param Record $emailRecord
     * @param bool $isTest
     * @return array
     * @throws \Doctrine\DBAL\Exception
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

        $addresses = [];

        [$addresses['to'], $addresses['cc'], $addresses['bcc']] = $this->mapAttributes($emailAttributes);

        $emailRecord = $this->recordProvider->saveRecord($emailRecord);

        $this->saveEmailAddresses($outboundRecord->getAttributes(), $emailRecord->getAttributes(), $addresses);

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

    /**
     * @param $emailAttributes
     * @return array[]
     */
    protected function mapAttributes($emailAttributes): array
    {
        $to = [];
        $bcc = [];
        $cc = [];

        $toAddresses = !empty($recordAttributes['to_addrs_names']) ? $recordAttributes['to_addrs_names'] : [];
        $ccAddresses = !empty($recordAttributes['cc_addrs_names']) ? $recordAttributes['cc_addrs_names'] : [];
        $bccAddresses = !empty($recordAttributes['bcc_addrs_names']) ? $recordAttributes['bcc_addrs_names'] : [];

        foreach ($toAddresses as $key => $value) {
            $to[] = $value['email1'] ?? $value['email'];
        }
        foreach ($ccAddresses as $key => $value) {
            $cc[] = $value['email1'] ?? $value['email'];
        }
        foreach ($bccAddresses as $key => $value) {
            $bcc[] = $value['email1'] ?? $value['email'];
        }

        return [$to, $cc, $bcc];
    }

    /**
     * @param array|null $outboundAttributes
     * @param array $emailAttributes
     * @param $addresses
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    protected function saveEmailAddresses(?array $outboundAttributes, array $emailAttributes, $addresses): void
    {

        $id = $emailAttributes['id'] ?? null;
        $emailId = $this->getEmailId($outboundAttributes['smtp_from_addr']);

        if (!empty($emailId)) {
            $this->linkEmailToAddress($id, $emailId, 'from');
        }

        $this->mapAddresses($id, $addresses['to'], 'to');
        $this->mapAddresses($id, $addresses['cc'], 'cc');
        $this->mapAddresses($id, $addresses['bcc'], 'bcc');
    }


    /**
     * @param $emailId
     * @param $email
     * @param $type
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    protected function linkEmailToAddress($emailId, $email, $type): void
    {
        $records = $this->preparedStatementHandler->fetch(
            "SELECT * FROM emails_email_addr_rel
         WHERE email_id = :email_id AND email_address_id = :email_address_id AND address_type = :type AND deleted = 0",
        ['email_id' => $emailId, 'email_address_id' => $email, 'type' => $type],
        [
            ['param' => 'email_id', 'type' => 'string'],
            ['param' => 'email_address_id', 'type' => 'string'],
            ['param' => 'type', 'type' => 'string']
        ]);

        if (empty($records)) {
            $id = create_guid();

            $this->preparedStatementHandler->update(
                'INSERT INTO emails_email_addr_rel VALUES(:id, :email_id, :type, :email, 0)',
                ['id' => $id, 'email_id' => $emailId, 'email' => $email, 'type' => $type],
                [
                    ['param' => 'email_id', 'type' => 'string'],
                    ['param' => 'id', 'type' => 'string'],
                    ['param' => 'email', 'type' => 'string'],
                    ['param' => 'type', 'type' => 'string']
                ]
            );
        }
    }

    /**
     * @param string $value
     * @return string
     */
    protected function getEmailId(string $value): string
    {
        $sugarEmailAddresses = new SugarEmailAddress();
        return $sugarEmailAddresses->getEmailGUID($value) ?? '';
    }

    /**
     * @param $id
     * @param $address
     * @param $type
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    protected function mapAddresses($id, $address, $type): void
    {
        foreach ($address as $key => $value) {
            $emailId = $this->getEmailId($value);
            if (!empty($emailId)) {
                $this->linkEmailToAddress($id, $emailId, $type);
            }
        }
    }

}
