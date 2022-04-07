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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/src/MailrelayApi.php';
require_once dirname(__FILE__) . '/src/Common.php';
use PrestaShop\Module\Mailrelay\Common;
use PrestaShop\Module\Mailrelay\MailrelayApi;

class Mailrelay extends Module
{
    public $_html;

    public function __construct()
    {
        $this->name = 'mailrelay';
        $this->tab = 'advertising_marketing';
        $this->version = '2.0-beta';
        $this->author = 'Mailrelay';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0',
            'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Mailrelay');
        $this->description = $this->l('Syncronize your PrestaShop users with Mailrelay.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        Configuration::updateValue('MAILRELAY', false);

        if (parent::install() == false) {
            return false;
        }
        $this->registerHook('leftColumn');
        $this->registerHook('displayBackOfficeHeader');
        $this->registerHook('actionCustomerAccountAdd');
        $this->installTab('AdminParentModulesSf', 'AdminMailrelay', 'mailrelay');

        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName('mailrelay');
        // unregister hook
        if (parent::uninstall()
        && $this->unregisterHook('leftColumn')
        && $this->unregisterHook('displayBackOfficeHeader')
        && $this->unregisterHook('actionCustomerAccountAdd')) {
            return true;
        } else {
            return false;
        }
    }

    public function installTab($parent_class, $class_name, $name)
    {
        $tab = new Tab();
        $tab->name[$this->context->language->id] = 'Mailrelay';
        $tab->class_name = $class_name;
        $tab->id_parent = (int) Tab::getIdFromClassName($parent_class);
        $tab->module = $this->name;

        return $tab->add();
    }

    public function getContent()
    {
        // get the account and API Key from database
        $mailrelay_data = [
            'host' => Configuration::get('MAILRELAY_ACCOUNT'),
            'api_key' => Configuration::get('MAILRELAY_API_KEY'),
        ];
        // test to see if it's the first time the plugin is executed or if the database is empty
        if (null == $mailrelay_data['api_key']) {
            $mailrelay_data = null;
        }

        $output = '';

        // this part is executed only when the authentication is submitted
        if (Tools::isSubmit('submitAuthentication' . $this->name)) {
            $data = $this->authenticationContent();
            $output .= $data['output'];
            $mailrelay_data = $data['mailrelay_data'];
            $ping_response_code = $data['ping_response_code'];
        } // this part is executed only when the Manual Sync is submitted
        elseif (Tools::isSubmit('submitManual' . $this->name)) {
            if (empty(Tools::getValue('GROUPS'))) {
                $output .= $this->displayError($this->l('Please select at least one group to syncronize.'));
            } else {
                $output .= $this->manualContent();
            }
        } // this part is executed only when the Settings is submitted
        elseif (Tools::isSubmit('submitSettings' . $this->name)) {
            if (true == Tools::getValue('MAILRELAY_AUTO_SYNC')) {
                if (empty(Tools::getValue('MAILRELAY_GROUPS'))) {
                    $output .= $this->displayError($this->l('Please select at least one group to syncronize.'));
                } else {
                    $output .= $this->settingsContent();
                }
            } else {
                $output .= $this->settingsContent();
            }
        }

        $_html = '';
        $_html .= $output;

        if (isset($mailrelay_data)) {
            $_html .= $this->settingsForm();
            $_html .= $this->manualForm();
            $_html .= $this->displayForm();
        } elseif (empty($ping_response_code) || 204 !== $ping_response_code) {
            $_html .= $this->displayForm();
        }

        return $_html;
    }

    // Generate the Authentication Form
    public function displayForm()
    {
        // Init Fields form array for authentication
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Authentication'),
                    'icon' => 'icon-edit',
                ],
                'input' => [
                    [
                        'col' => 4,
                        'type' => 'text',
                        'label' => $this->l('Account'),
                        'name' => 'MAILRELAY_ACCOUNT',
                        'prefix' => '<i class="icon icon-tag"></i>',
                        'size' => 20,
                        'required' => true,
                        'desc' => $this->l('Login using your Mailrelay account name.'),
                    ],
                    [
                        'col' => 4,
                        'type' => 'text',
                        'label' => $this->l('API Key'),
                        'prefix' => '<i class="icon icon-puzzle-piece"></i>',
                        'name' => 'MAILRELAY_API_KEY',
                        'size' => 20,
                        'required' => true,
                        'desc' => $this->l('Your API Key can be found or generated at your Mailrelay Account -> Settings -> API Access.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = $this->generateHelperForm('submitAuthentication');

        // Load current value into the form
        $helper->fields_value['MAILRELAY_ACCOUNT'] = Tools::getValue('MAILRELAY_ACCOUNT', Configuration::get('MAILRELAY_ACCOUNT'));
        $helper->fields_value['MAILRELAY_API_KEY'] = Tools::getValue('MAILRELAY_API_KEY', Configuration::get('MAILRELAY_API_KEY'));

        return $helper->generateForm([$form]);
    }

    // Generate the Settings Form
    public function settingsForm()
    {
        $mailrelayApi = new MailrelayApi();
        $groups = $mailrelayApi->mailrelay_get_groups();
        // Init Fields form array for settings
        $value = [];
        foreach ($groups as $group) {
            $value[] = ['key' => $group['id'], 'name' => $group['name']];
        }

        $form = [
        'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                    'input' => [
                        [
                            'type' => 'switch',
                            'label' => $this->l('Automatically sync new users with Mailrelay'),
                            'name' => 'MAILRELAY_AUTO_SYNC',
                            'values' => [
                                [
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->l('Enabled'),
                                ],
                                [
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->l('Disabled'),
                                ],
                            ],
                        ],
                        [
                            'size' => 5,
                            'type' => 'select',
                            'class' => 'mailrelay_groups',
                            'label' => $this->l('Groups that you want to automatically syncronize'),
                            'name' => 'MAILRELAY_GROUPS[]',
                            'multiple' => true,
                            'required' => true,
                            'desc' => $this->l('Please select at least one group'),
                            'options' => [
                                        'query' => $value,
                                        'id' => 'key',
                                        'name' => 'name',
                            ],
                        ],
                    ],
                    'buttons' => [
                        [
                            'type' => 'button',
                            'name' => 'refresh',
                            'href' => 'javascript:window.location.href=window.location.href',
                            'id' => 'refresh_button',
                            'title' => $this->l('Refresh Groups'),
                            'icon' => 'icon-reset',
                            'class' => 'btn btn-default pull-right',
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save'),
                        'class' => 'btn btn-default pull-right',
                    ],
            ],
        ];
        $helper = $this->generateHelperForm('submitSettings');

        // Load current value into the form
        $helper->fields_value['MAILRELAY_AUTO_SYNC'] = Tools::getValue('MAILRELAY_AUTO_SYNC', Configuration::get('MAILRELAY_AUTO_SYNC'));
        $helper->fields_value['MAILRELAY_GROUPS[]'] = Tools::getValue('MAILRELAY_GROUPS', unserialize(Configuration::get('MAILRELAY_GROUPS_SYNC')));

        return $helper->generateForm([$form]);
    }

    // Generate the Manual Form
    public function manualForm()
    {
        $mailrelayApi = new MailrelayApi();
        $groups = $mailrelayApi->mailrelay_get_groups();

        $value = [];
        foreach ($groups as $group) {
            $value[] = ['key' => $group['id'], 'name' => $group['name']];
        }

        $form = [
        'form' => [
                'legend' => [
                    'title' => $this->l('Manual Sync'),
                    'icon' => 'icon-save',
                ],
                    'input' => [
                        [
                            'size' => 5,
                            'type' => 'select',
                            'label' => $this->l('Please select Groups to synchronize.'),
                            'name' => 'GROUPS[]',
                            'class' => 'groups',
                            'multiple' => true,
                            'required' => true,
                            'desc' => $this->l('Please select at least one group'),
                            'options' => [
                                        'query' => $value,
                                        'id' => 'key',
                                        'name' => 'name',
                            ],
                        ],
                    ],
                    'buttons' => [
                        [
                            'type' => 'button',
                            'name' => 'refresh',
                            'href' => 'javascript:window.location.href=window.location.href',
                            'title' => $this->l('Refresh Groups'),
                            'icon' => 'icon-reset',
                            'class' => 'btn btn-default pull-right',
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Synchronize'),
                        'class' => 'btn btn-default pull-right',
                    ],
            ],
        ];

        $helper = $this->generateHelperForm('submitManual');
        $helper->fields_value['GROUPS[]'] = '';

        return $helper->generateForm([$form]);
    }

    public function generateHelperForm($submit_action)
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        // Module, token and currentIndex
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->submit_action = $submit_action . $this->name;

        // Default language
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        return $helper;
    }

    // Test the credentials and update Account and API Key values
    public function authenticationContent()
    {
        $mailrelay_data = [
                'host' => Tools::getValue('MAILRELAY_ACCOUNT'),
                'api_key' => Tools::getValue('MAILRELAY_API_KEY'),
        ];
        if (false !== strpos($mailrelay_data['host'], 'http://') || false !== strpos($mailrelay_data['host'], 'https://')) {
            $removeChar = ['https://', 'http://', '/'];
            $mailrelay_data['host'] = str_replace($removeChar, '', $mailrelay_data['host']);
        }
        if (false !== strpos($mailrelay_data['host'], '.ipzmarketing.com')) {
            $mailrelay_data['host'] = str_replace('.ipzmarketing.com', '', $mailrelay_data['host']);
        }
        $mailrelayApi = new MailrelayApi();
        $ping_response_code = $mailrelayApi->mailrelay_ping($mailrelay_data);
        // check the code response from the API
        if (empty($ping_response_code)) {
            $mailrelay_data = null;
            // invalid value, show an error
            $output = $this->displayError($this->l('Invalid Configuration value'));
        } elseif (204 !== $ping_response_code) {
            $mailrelay_data = null;
            // invalid value of accout or api key, show an error
            $output = $this->displayError($this->l("The API key or Account wasn't sent or is invalid"));
        } else {
            // value is ok, update it and display a confirmation message
            Configuration::updateValue('MAILRELAY_ACCOUNT', $mailrelay_data['host']);
            Configuration::updateValue('MAILRELAY_API_KEY', $mailrelay_data['api_key']);
            $output = $this->displayConfirmation($this->l('Account and API Key updated'));
        }

        $authentication = [];
        $authentication = [
            'mailrelay_data' => $mailrelay_data,
            'ping_response_code' => $ping_response_code,
            'output' => $output,
        ];

        return $authentication;
    }

    // Brings the result of the Manual Sinc.
    public function manualContent()
    {
        $common = new Common();
        $users = $common->getSubscribers();
        $groups = Tools::getValue('GROUPS');
        $added = 0;
        $updated = 0;
        $failed = 0;
        $mailrelayApi = new MailrelayApi();
        $data = $mailrelayApi->mailrelay_data();

        foreach ($users as $user) {
            $return = $mailrelayApi->mailrelay_sync_user($user, $groups, $data);

            if ('created' === $return['status']) {
                ++$added;
            } elseif ('updated' === $return['status']) {
                ++$updated;
            } elseif ('failed' === $return['status']) {
                ++$failed;
            } else {
                throw new Exception('Invalid return status.');
            }
        }

        $confirmation_output = '<p>' . sprintf($this->l('Users Created: %d;'), $added) . '</p>';
        $confirmation_output .= '<p>' . sprintf($this->l('Users Updated: %d;'), $updated) . '</p>';
        $confirmation_output .= '<p>' . sprintf($this->l('Users Failed: %d;'), $failed) . '</p>';
        $output = $this->displayConfirmation($confirmation_output);

        return $output;
    }

    // Update Settings Values
    public function settingsContent()
    {
        Configuration::updateValue('MAILRELAY_GROUPS_SYNC', serialize(Tools::getValue('MAILRELAY_GROUPS')));
        Configuration::updateValue('MAILRELAY_AUTO_SYNC', Tools::getValue('MAILRELAY_AUTO_SYNC'));
        $output = $this->displayConfirmation($this->l('Settings updated'));

        return $output;
    }

    // Hook for the js file
    public function hookBackOfficeHeader()
    {
        $this->context->controller->addJS(dirname(__FILE__) . '/js/main.js');
    }

    // Hook for every new user that register in the site
    public function hookActionCustomerAccountAdd($params)
    {
        if (empty($params['newCustomer'])) {
            return false;
        }
        $common = new Common();

        return $common->mailrelayNewUser($params);
    }
}
