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

use App\Engine\LegacyHandler\LegacyHandler;
use App\Engine\LegacyHandler\LegacyScopeState;
use PHPMailer\PHPMailer\Exception;
use Symfony\Component\HttpFoundation\RequestStack;

class EmailProcessProcessor extends LegacyHandler
{

    protected EmailBuilderHandler $emailBuilderHandler;
    protected SendEmailHandler $sendEmailHandler;

    public function __construct(
        string              $projectDir,
        string              $legacyDir,
        string              $legacySessionName,
        string              $defaultSessionName,
        LegacyScopeState    $legacyScopeState,
        RequestStack        $requestStack,
        EmailBuilderHandler $emailBuilderHandler,
        SendEmailHandler    $sendEmailHandler
    )
    {
        parent::__construct(
            $projectDir,
            $legacyDir,
            $legacySessionName,
            $defaultSessionName,
            $legacyScopeState,
            $requestStack
        );
        $this->emailBuilderHandler = $emailBuilderHandler;
        $this->sendEmailHandler = $sendEmailHandler;
    }

    public function getHandlerKey(): string
    {
        return 'email-process-processor';
    }

    /**
     * @param $emailTo
     * @param $subject
     * @param $body
     * @param $from
     * @param $fromName
     * @param bool $isTest
     * @return bool
     * @throws Exception
     */
    public function processEmail($emailTo, $subject, $body, $from, $fromName, bool $isTest = false): bool
    {
        $email = $this->emailBuilderHandler->buildEmail($subject, $body, $emailTo, $from, $fromName);
        $success = $this->sendEmailHandler->sendEmail($email, $isTest);

        if ($success){
            return true;
        }

        return false;
    }


}
