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


namespace App\Process\LegacyHandler;


use ApiPlatform\Exception\InvalidArgumentException;
use App\Data\Entity\Record;
use App\Emails\LegacyHandler\EmailBuilderHandler;
use App\Emails\LegacyHandler\EmailProcessProcessor;
use App\Emails\LegacyHandler\FilterEmailListHandler;
use App\Emails\LegacyHandler\SendEmailHandler;
use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use App\Module\Service\ModuleNameMapperInterface;
use App\Process\Entity\Process;
use App\Process\Service\ProcessHandlerInterface;
use App\SystemConfig\LegacyHandler\SystemConfigHandler;
use BeanFactory;
use PHPMailer\PHPMailer\Exception;
use Symfony\Component\HttpFoundation\RequestStack;

class SendTestEmailHandler extends LegacyHandler implements ProcessHandlerInterface
{
    protected const MSG_OPTIONS_NOT_FOUND = 'Process options is not defined';
    protected const PROCESS_TYPE = 'record-send-test-email';

    protected FilterEmailListHandler $filterEmailListHandler;
    protected ModuleNameMapperInterface $moduleNameMapper;
    protected SendEmailHandler $sendEmailHandler;
    protected SystemConfigHandler $systemConfigHandler;
    protected EmailProcessProcessor $emailProcessProcessor;

    public function __construct(
        string $projectDir,
        string $legacyDir,
        string $legacySessionName,
        string $defaultSessionName,
        LegacyScopeState $legacyScopeState,
        RequestStack $requestStack,
        FilterEmailListHandler $filterEmailListHandler,
        ModuleNameMapperInterface $moduleNameMapper,
        SendEmailHandler $sendEmailHandler,
        SystemConfigHandler $systemConfigHandler,
        EmailProcessProcessor $emailProcessProcessor
    ) {
        parent::__construct(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScopeState,
            $requestStack
        );
        $this->filterEmailListHandler = $filterEmailListHandler;
        $this->moduleNameMapper = $moduleNameMapper;
        $this->sendEmailHandler = $sendEmailHandler;
        $this->systemConfigHandler = $systemConfigHandler;
        $this->emailProcessProcessor = $emailProcessProcessor;
    }

    /**
     * @inheritDoc
     */
    public function getHandlerKey(): string
    {
        return self::PROCESS_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function getProcessType(): string
    {
        return self::PROCESS_TYPE;
    }

    /**
     * @inheritDoc
     */
    public function requiredAuthRole(): string
    {
        return 'ROLE_USER';
    }

    /**
     * @inheritDoc
     */
    public function getRequiredACLs(Process $process): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function configure(Process $process): void
    {
        $process->setId(self::PROCESS_TYPE);
        $process->setAsync(false);
    }

    /**
     * @inheritDoc
     */
    public function validate(Process $process): void
    {
        if (empty($process->getOptions())) {
            throw new InvalidArgumentException(self::MSG_OPTIONS_NOT_FOUND);
        }
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function run(Process $process): void
    {
        $options = $process->getOptions();

        $fields = $options['params']['fields'];
        $module = $options['module'];
        $id = $options['id'];

        $this->init();
        $this->startLegacyApp();

        $outboundEmail = null;

        $module = $this->moduleNameMapper->toLegacy($module);
        $bean = BeanFactory::getBean($module, $id);
        $max = $this->systemConfigHandler->getSystemConfig('test_email_limit')?->getValue();
        $emails = $this->filterEmailListHandler->getEmails($fields, $max, true);

        if ($emails === null) {
            $process->setStatus('error');
            $process->setMessages(['LBL_TOO_MANY_ADDRESSES']);
            $process->setData([]);
            return;
        }

        if ($bean?->outbound_email_id ?? false) {
            $outboundEmail = BeanFactory::getBean('OutboundEmailAccounts', $bean->outbound_email_id);
        }

        $subject = $bean->subject;
        $body = $bean->body;

        $allSent = true;

        foreach ($emails as $email) {
            $record = new Record();
            $record->setId($recordOption['id'] ?? '');
            $record->setModule($recordOption['module'] ?? '');
            $record->setType($recordOption['type'] ?? '');
            $record->setAttributes(
                [
                    'to_addrs_names' => [
                        [
                            'email1' => $email,
                        ]
                    ],
                    'cc_addrs_names' => [],
                    'bcc_addrs_names' => [],
                    'name' => $subject,
                    'description_html' => $body,
                    'outbound_email_id' => $outboundEmail->id,
                ]
            );

            $success = $this->emailProcessProcessor->processEmail($record, true);
            if ($success) {
                continue;
            }
            $allSent = false;
        }

        if (!$allSent) {
            $process->setStatus('error');
            $process->setMessages(['LBL_NOT_ALL_SENT']);
            $process->setData([]);
            return;
        }

        $process->setStatus('success');
        $process->setMessages(['LBL_ALL_EMAILS_SENT']);
        $process->setData([]);
    }
}
