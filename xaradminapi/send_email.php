<?php
/**
 * Reminders Module
 *
 * @package modules
 * @subpackage reminders
 * @category Third Party Xaraya Module
 * @version 1.0.0
 * @copyright (C) 2019 Luetolf-Carroll GmbH
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @author Marc Lutolf <marc@luetolf-carroll.com>
 */

function reminders_adminapi_send_email($data)
{
    # --------------------------------------------------------
#
    # Get some properties for use in the template
#
    $data['name'] = DataPropertyMaster::getProperty(['name' => 'name']);
    $data['checkbox'] = DataPropertyMaster::getProperty(['name' => 'checkbox']);
    $data['date'] = DataPropertyMaster::getProperty(['name' => 'date']);
    $data['number'] = DataPropertyMaster::getProperty(['name' => 'number']);
    $data['integerbox'] = DataPropertyMaster::getProperty(['name' => 'integerbox']);
    $data['floatbox'] = DataPropertyMaster::getProperty(['name' => 'floatbox']);
    $data['textbox'] = DataPropertyMaster::getProperty(['name' => 'textbox']);
    $data['textarea'] = DataPropertyMaster::getProperty(['name' => 'textarea']);

    # --------------------------------------------------------
#
    # Send the participant an email with the attachments
#

    $result = [];
    $attachments = [];
    $data['name']->value = $data['info']['name_1'];

    // Set a placeholder name if we don't have one
    if (empty($data['name']->value)) {
        $data['name']->setValue([['id' => 'last_name', 'value' => xarModVars::get('mailer', 'defaultrecipientname')]]);
    }

    // Get the name and address of the chosen participant
    $recipientname    = $data['name']->getValue();
    $recipientaddress = $data['info']['address_1'];

    // Add a CC if there is one
    if (!empty($data['info']['address_1'])) {
        $data['name']->value = $data['info']['name_1'];
        $ccname = $data['name']->getValue();
        $ccaddress = [$data['info']['address_1'] => $ccname];
    } else {
        $ccaddress = [];
    }
    // Maybe we'll add a BCC at some point
    $bccaddress = $data['copy_emails'] ? [xarUser::getVar('email')] : [];

    $data['reminder_text'] = trim($data['info']['message']);
    $data['entry_id']      = (int)$data['info']['id'];
    $data['code']          = $data['info']['code'];
    $data['due_date']      = (int)$data['info']['due_date'];

    // Get today's date
    $datetime = new XarDateTime();
    $datetime->settoday();
    $today = $datetime->getTimestamp();

    if ($data['due_date'] == $today) {
        // If today is the due date, then this is the last email
        $data['remaining'] = 0;
    } else {
        // Otherwise, get the number of remaining emails to send
        $remaining = xarMod::apiFunc('reminders', 'user', 'get_remaining_dates', ['array' => $data['info']]);
        // By default we also send an email on the due date
        $data['remaining'] = count($remaining) + 1;
    }

    unset($data['info']);

    try {
        // Set the paramenters for the send function
        $args = ['sendername'       => xarModVars::get('reminders', 'defaultsendername'),
                      'senderaddress'    => xarModVars::get('reminders', 'defaultsenderaddress'),
                      'recipientname'    => $recipientname,
                      'recipientaddress' => $recipientaddress,
                      'ccaddresses'      => $ccaddress,
                      'bccaddresses'     => $bccaddress,
                      'attachments'      => $attachments,
                      'data'             => $data,
                    ];

        // Check if we have a subject/message or a message ID
        if (empty($data['params']['subject']) && empty($data['params']['message_body'])) {
            // Bail if no message ID available
            if (empty($data['params']['message_id'])) {
                $result['code'] = 2;
            }
            return $result;
        }

        if (!empty($data['params']['message_id'])) {
            // We have a message ID
            $args['id'] = (int)$data['params']['message_id'];
            // Get the message template
            $object = DataObjectMaster::getObject(['name' => 'mailer_mails']);
            $object->getItem(['itemid' => $args['id']]);
            // The subject can be overwritten
            $args['subject'] = $object->properties['subject']->value;
            if (!empty($data['params']['subject'])) {
                $args['subject'] = $data['params']['subject'];
            }
            if (!empty($data['params']['message_body'])) {
                // We have a message ID (which indicates a template) and also a message body
                // In this case we insert the latter into the former
                $message = $object->properties['body']->value;
                $args['message'] = str_replace('#$message#', $data['params']['message_body'], $message);
                $args['mail_type'] = $object->properties['mail_type']->value;
                $sendername = $object->properties['sender_name']->value;
                if (!empty($sendername)) {
                    $args['sendername'] = $sendername;
                }
                $senderaddress = $object->properties['sender_address']->value;
                if (!empty($senderaddress)) {
                    $args['senderaddress'] = $senderaddress;
                }
                unset($args['id']);
            }
        } elseif (!empty($data['params']['message_body'])) {
            // We have only a message body
            if (isset($data['params']['subject'])) {
                $args['subject'] = $data['params']['subject'];
            }
            $args['message'] = $data['params']['message_body'];
            // In this case we set the mail type to "text to html"
            $args['mail_type'] = 2;
        }
        // Send the email
        $result['code'] = xarMod::apiFunc('mailer', 'user', 'send', $args);

        // Save to the database if called for
        if (xarModVars::get('reminders', 'save_history') && ($result['code'] == 0)) {
            $history = DataObjectMaster::getObject(['name' => 'reminders_history']);
            $history->createItem([
                                    'entry_id' => $data['entry_id'],
                                    'message'  => $data['reminder_text'],
                                    'address'  => $recipientaddress,
                                    'due_date' => $data['due_date'],
                                ]);
        }
    } catch (Exception $e) {
        $result['exception'] = $e->getMessage();
    }
    $result['name'] = $recipientname;
    $result['email'] = $recipientaddress;

    // if we are testing, then add the test name and email
    if ($data['test']) {
        // If we are testing, then send to this user
        $result['test_name']    = xarUser::getVar('name');
        $result['test_email']   = xarUser::getVar('email');
    }

    return $result;
}
