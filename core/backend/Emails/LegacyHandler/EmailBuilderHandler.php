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
use BeanFactory;
use PHPMailer\PHPMailer\Exception;
use SugarBean;
use SugarPHPMailer;

class EmailBuilderHandler extends LegacyHandler {

    protected const HANDLER_KEY = 'email-builder';

    public function getHandlerKey(): string
    {
        return self::HANDLER_KEY;
    }

    /**
     * @throws Exception
     */
    public function buildEmail(
        $subject,
        $body,
        $emailTo,
        $from = '',
        $fromName = '',
        $outboundEmail = null,
        $altEmailBody = '',
        $emailCc = [],
        $emailBcc = [],
        $attachments = []
    ) {
        require_once('include/SugarPHPMailer.php');

        $mail = new SugarPHPMailer();

        $emailObj = BeanFactory::newBean('Emails');
        $defaults = $emailObj->getSystemDefaultEmail();

        $mail->Subject = from_html($subject);

        $this->handleBodyInHTMLformat($mail, $body);

        if (!$from){
            $from = $defaults['email'];
            $fromName = $defaults['name'];
        }

        if ($outboundEmail !== null) {
            $this->setMailer($mail, $outboundEmail);
        } else {
            $mail->setMailerForSystem();
        }

        isValidEmailAddress($from);
        $mail->From = $from;
        $mail->FromName = $fromName;

        if (!empty($altEmailBody)) {
            $mail->AltBody = $altEmailBody;
        }

        if (is_array($emailTo)){
            foreach($emailTo as $email) {
                $mail->addAddress($email);
            }
        } else {
            $mail->addAddress($emailTo);
        }

        $this->addCC($mail, $emailCc, $emailBcc);

        $mail->handleAttachments($attachments);
        $mail->prepForOutbound();

        return $mail;
    }

    /**
     * @throws Exception
     */
    protected function addCC(&$mail, mixed $emailCc, mixed $emailBcc): void
    {
        foreach ($emailBcc as $bcc) {
            $mail->AddBCC($bcc);
        }

        foreach ($emailCc as $cc) {
            $mail->AddCC($cc);
        }
    }

    protected function setMailer($mail, SugarBean $outboundEmail)
    {
        $mail->protocol = $outboundEmail->mail_smtpssl ? 'ssl://' : 'tcp://';

        if (isSmtp($outboundEmail->mail_sendtype ?? '')) {
            $mail->Mailer = 'smtp';
            $mail->Host = $outboundEmail->mail_smtpserver;
            $mail->Port = $outboundEmail->mail_smtpport;
            if ($outboundEmail->mail_smtpssl === 1) {
                $mail->SMTPSecure = 'ssl';
            }

            if ($outboundEmail->mail_smtpssl === 2) {
                $mail->SMTPSecure = 'tls';
            }

            if ($outboundEmail->mail_smtpauth_req) {
                $mail->SMTPAuth = true;
                $mail->Username = $outboundEmail->mail_smtpuser;
                $mail->Password = $outboundEmail->mail_smtppass;
            }
        } else {
            $mail->Mailer = 'sendmail';
        }
    }

    protected function handleBodyInHTMLformat(SugarPHPMailer $mail, $body)
    {
        $mail->IsHTML(true);
        $body = from_html(wordwrap($body, 996));
        $mail->Body = $body;

        $plainText = from_html($body);
        $plainText = strip_tags(br2nl($plainText));
        $mail->AltBody = $plainText;
        $mail->description = $plainText;
    }
}
