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

class MailrelayApi {

    public function __construct() {
        $this->name = 'mailrelay';
    }

    // Test the Account and API Key 
    public function mailrelay_api_request( $method, $url, $args = array(), $mailrelay_data = null) {
        if ( is_null( $mailrelay_data ) ) {
            $mailrelay_data = $this->mailrelay_data();
        }

        $url = 'https://' . $mailrelay_data['host'] . '.ipzmarketing.com/api/v1/' . $url;
            
        $request_args = array(
            CURLOPT_URL             => trim($url),
            CURLOPT_CUSTOMREQUEST   => $method,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTPHEADER      => array(
                'x-auth-token: '. $mailrelay_data['api_key'].'',
            ),
        );

        foreach ($args as $key => $value) {
            if ( is_array($value) && isset( $request_args[$key] ) ) {
                $request_args[$key] = array_merge($request_args[$key], $value);
            } else {
                $request_args[$key] = $value;
            }
        }

        $curl = curl_init();

        curl_setopt_array( $curl, $request_args );

        $response = curl_exec( $curl );

        $error = curl_error( $curl );      
                      
        $body = json_decode( $response, true );
        $httpCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

        curl_close( $curl );

        if($error) {
            return array(
                'error'         => true,
                'error_message' => $error,
                'response'      => $response,
            );
        }

        return array(
            'error'  => false,
            'code'      => $httpCode,
            'body'      => $body,
            'response'  => $response,
        );
    }    

    //Get the email groups from the Mailrelay account

    public function mailrelay_get_groups() {
        $response = $this->mailrelay_api_request( 'GET', 'groups', array(), null );

        if ( $response['error'] ) {
            return $response['error_message'];
        } elseif ( 200 === $response['code'] ) {
            return $response['body'];
        } elseif ( 401 === $response['code'] ) {
            return $this->l("The API key wasn't sent or is invalid");
        }
        else{
            return $this->l("An internal error happened. Try again later.");
        }
    }

    //Saves the subscribers users into Mailrelay account
    public function mailrelay_sync_user( $user, $groups, $mailrelay_data = null ) {
        if ( is_null( $mailrelay_data ) ) {
            $mailrelay_data = $this->mailrelay_data();
        }

        if ( null === $user['firstname'] && null === $user['lastname'] ) { 
            $full_name = null; 
        } else { 
            $full_name = $user['firstname'] . ' ' . $user['lastname'];
        }
            
        $data = array(
            'email'              => $user['email'],
            'name'               => $full_name,
            'replace_groups'     => false,
            'restore_if_deleted' => false,
            'status'             => 'active',
            'group_ids'          => $groups,
        );

        $data = json_encode($data);

        $response = $this->mailrelay_api_request(
            'POST',
            'subscribers/sync',
            array(
                CURLOPT_POSTFIELDS  => $data,  
                CURLOPT_HTTPHEADER  => array(
                    'content-type:  application/json',
                ),
            ),
            $mailrelay_data
        );

        if ( $response['error'] ) {
            return $response['error_message'];
        }
        if ( 200 === $response['code'] ) {
            return array(
                'status' => 'updated',
            );
        } elseif ( 201 === $response['code'] ) {
            return array(
                'status' => 'created',
            );
        } else {
            return array(
                'status' => 'failed',
            );
        }
    }

    public function mailrelay_ping( $mailrelay_data ) {

        $response = $this->mailrelay_api_request( 'GET', 'ping', array(), $mailrelay_data );
        if ( $response['error'] ) {
            return false;
        }
        return $response['code'];
    }

    public function mailrelay_data() {
        $api_key = Configuration::get('MAILRELAY_API_KEY');

        if ( $api_key ) {
            return array(
                'host'        => Configuration::get('MAILRELAY_ACCOUNT'),
                'api_key'     => $api_key,
                'auto_sync'   => Configuration::get('MAILRELAY_AUTO_SYNC'),
                'groups_sync' => unserialize(Configuration::get('MAILRELAY_GROUPS_SYNC')),
            );
        }

        return false;
    }   

}





