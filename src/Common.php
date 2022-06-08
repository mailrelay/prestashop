<?php

/**
 * 2007-2022 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\Mailrelay;

use Configuration;
use Db;
use DbQuery;

class Common
{
    public function getSubscribers()
    {
        $dbquery = new DbQuery();
        $dbquery->select('c.`id_customer` AS `id`, c.`lastname`, c.`firstname`, c.`email`, c.`newsletter` AS `subscribed`');
        $dbquery->from('customer', 'c');
        $dbquery->where('c.`newsletter` = 1');

        $customers = Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->executeS($dbquery->build());

        $dbquery = new DbQuery();
        $dbquery->select('CONCAT(\'N\', e.`id`) AS `id`, NULL AS `lastname`, NULL AS `firstname`, e.`email`, e.`active` AS `subscribed`');
        $dbquery->from('emailsubscription', 'e');
        $dbquery->where('e.`active` = 1');
        $non_customers = Db::getInstance()->executeS($dbquery->build());
        $subscribers = array_merge($customers, $non_customers);

        return $subscribers;
    }

    public function mailrelayNewUser($params)
    {
        $newsletter = $params->newsletter;
        if (1 != (int) Configuraton::get('MAILRELAY_AUTO_SYNC')) {
            return;
        }

        $user = [
            'email' => $params->email,
            'firstname' => $params->firstname,
            'lastname' => $params->lastname,
        ];

        $groups = unserialize(Configuration::get('MAILRELAY_GROUPS_SYNC'));
        if (!empty($groups)) {
            try {
                $mailrelayApi = new MailrelayApi();
                $data = $mailrelayApi->mailrelay_data();
                $mailrelayApi->mailrelay_sync_user($user, $groups, $data);
            } catch (Exception $e) {
                // Ignore if something goes wrong to avoid showing errors to user
            }
        }
    }

    public function mailrelayUpdateUser($params)
    {
        $newsletter = $params->newsletter;
        if (1 != (int) Configuration::get('MAILRELAY_AUTO_SYNC') || $newsletter == false) {
            // Autosync isn't enabled
            return;
        }

        $user = [
            'email' => $params->email,
            'firstname' => $params->firstname,
            'lastname' => $params->lastname,
        ];

        $groups = unserialize(Configuration::get('MAILRELAY_GROUPS_SYNC'));
        if (!empty($groups)) {
            try {
                $mailrelayApi = new MailrelayApi();
                $data = $mailrelayApi->mailrelay_data();
                $mailrelayApi->mailrelay_sync_user($user, $groups, $data);
            } catch (Exception $e) {
                // Ignore if something goes wrong to avoid showing errors to user
            }
        }
    }
}
