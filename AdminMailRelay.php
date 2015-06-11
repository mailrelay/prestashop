<?php
$dirname = dirname(__FILE__);
$path[] = $dirname . DIRECTORY_SEPARATOR . 'library';
$path[] = get_include_path();
set_include_path(implode(PATH_SEPARATOR, $path));

require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance()->registerNamespace('Zend');

class AdminMailRelay extends ModuleAdminController {

    /**
     * @var string
     */
    protected $_lang = false;

    /**
     * @var string
     */
    protected $_defaultLang = 'en';

    /**
     * @var array
     */
    protected $_arrLang = false;

    /**
     * @var Zend_Translate
     */
    protected $_translate = false;

    public function display() {

        global $smarty;
        $db_prefix = _DB_PREFIX_;
        $is_post = 'POST' == $_SERVER['REQUEST_METHOD'];
        $params = $this->_getParams();

        if ($is_post) {
            if ('save_credential' == $params->mailrelay_option) {
                $result = $this->_saveCredential($params);
                if ($result) {
                    $params->mailrelay_hostname = $result['hostname'];
                    $params->mailrelay_key = $result['key'];
                }
            } elseif ('sync' == $params->mailrelay_option) {
                die(json_encode($this->_sync($params, $this->_getCredentials(), (int)$_REQUEST['start'])));
            }
        }

        $credentials = $this->_getCredentials();
        $hasCredential =($credentials) ? true : false;
        if ($hasCredential) {
            $params->mailrelay_hostname = $credentials['hostname'];
            $params->mailrelay_key = $credentials['key'];
        }

        $smarty->assign($params->toArray());
        $this->_assignGroups();

        $assign['has_credential'] =(bool) $hasCredential;
        $smarty->assign($assign);

        $smarty->assign('lang', $this->_arrLang);
        $assign['content'] = $this->_fetchTemplate('mailrelay');
        $smarty->assign($assign);

        parent::display();
    }

    protected function _assignGroups() {
        global $smarty;

        $credentials = $this->_getCredentials();

        $options = array(
                0 => $this->_('select_a_group')
       );

        if ($credentials) {
            $params['enable'] = true;
            $result = $this->_execute($credentials['hostname'], $credentials['key'], 'getGroups', $params);

            if ($result && $result['status']) {
                foreach($result['data'] as $item) {
                    $options[$item['id']] = $item['name'];
                }
            }
        }

        $smarty->assign('please_select_a_group', $this->_('please_select_a_group'));
        $smarty->assign('mailrelay_groups_options', $options);
        $smarty->assign('mailrelay_groups_option_selected', $credentials['last_group']);
    }

    /**
     *
     * @return Ambigous <NULL, array>
     */
    protected function _getCredentials() {
        $row = null;
        $db_prefix = _DB_PREFIX_;

        $sql = "SELECT * FROM `{$db_prefix}mailrelay` LIMIT 1";
        $rowset = Db::getInstance()->executeS($sql);
        if ($rowset) {
            $row = $rowset[0];
        }

        return $row;
    }

    protected function _sync(Zend_Config $data, $credential, $start = 0) {
        $response = new stdclass();
        $response->status = 'OK';
        $response->message = '';
        $response->completed = false;
        $response->customersCount = 0;
        $group = abs((int) $data->mailrelay_group);
        if ($group) {
            $db_prefix = _DB_PREFIX_;
            @session_start();
            if ($start == 0) {
                $_SESSION['summary'] = array();
                $_SESSION['summary']['total'] = 0;
                $_SESSION['summary']['new'] = 0;
                $_SESSION['summary']['updated'] = 0;
                $_SESSION['summary']['failed'] = 0;
                $sql = "SELECT COUNT(*) AS `customersCount` FROM `{$db_prefix}customer` WHERE `newsletter` = 1";
                $rowset = Db::getInstance()->executeS($sql);
                $_SESSION['customersCount'] = (int)$rowset[0]['customersCount'];
            }

            $response->customersCount = $_SESSION['customersCount'];

            $data->mailrelay_group;
            
            $sql = "SELECT * FROM `{$db_prefix}customer` WHERE `newsletter` = 1 LIMIT {$start}, 10";
            $rowset = Db::getInstance()->executeS($sql);
            if (!empty($rowset)) {
                foreach($rowset as $row) {$response->message=$row['email'];
                    $name = "{$row['firstname']} {$row['lastname']}";
                    $email = $row['email'];

                    $params = array();
                    $params['email'] = $email;
                    $result = $this->_execute($credential['hostname'], $credential['key'], 'getSubscribers', $params);

                    if ($result) {
                        $_SESSION['summary']['total']++;

                        if (! count($result['data'])) {
                            $params['name'] = $name;
                            $params['groups'] = array(
                                    $group
                           );
                            $result = $this->_execute($credential['hostname'], $credential['key'], 'addSubscriber', $params);

                            if ($result && 1 == $result['status'])
                                $_SESSION['summary']['new'] ++;
                            else
                            {
                                $_SESSION['summary']['failed'] ++;
                            }
                        } else {
                            $params['id'] = $result['data'][0]['id'];
                            $params['name'] = $name;
                            $params['groups'] = array_merge(array($group), $result['data'][0]['groups']);

                            $result = $this->_execute($credential['hostname'], $credential['key'], 'updateSubscriber', $params);

                            if ($result && 1 == $result['status'])
                                $_SESSION['summary']['updated'] ++;
                            else
                            {
                                $_SESSION['summary']['failed'] ++;
                            }
                        }
                    } else {
                        $_SESSION['summary']['failed'] ++;
                    }
                }
            } else {
                $response->message .= "{$this->_('total_subscribers')}:({$_SESSION['summary']['total']})<br />";
                $response->message .= "{$this->_('new_subscribers')}:({$_SESSION['summary']['new']})<br />";
                $response->message .= "{$this->_('updated_subscribers')}:({$_SESSION['summary']['updated']})<br />";
                $response->message .= "{$this->_('failed_subscribers')}:({$_SESSION['summary']['failed']})<br />";
                $response->completed = true;

                // update the last selected group
                $sql = "UPDATE `{$db_prefix}mailrelay` SET last_group = $group";
                Db::getInstance()->execute($sql);
            }
        } else {
            $response->status = 'danger';
            $response->message = $this->_('please_select_a_group');
        }
        return $response;
    }

    /**
     *
     * @param Zend_Config $data
     * @return boolean array
     */
    protected function _saveCredential(Zend_Config $data) {
        global $smarty;
        $hostname = trim(Db::getInstance()->escape($data->mailrelay_hostname));
        $apiKey = trim(Db::getInstance()->escape($data->mailrelay_key));

        if (empty($hostname) || empty($apiKey)) {
            $this->_showMessage($this->_('please_fill_in_all_required_fields'), 'danger');
            return false;
        }

        $validate = new Zend_Validate_Hostname();
        if (! $validate->isValid($hostname)) {
            $this->_showMessage($this->_('please_provide_a_valid_hostname'), 'danger');
            return false;
        }

        try {
            $row = $this->_getCredentials();
            $db_prefix = _DB_PREFIX_;

            if ($row) {
                $id = $row['id'];
                $sql = "UPDATE `{$db_prefix}mailrelay` SET `hostname` = '$hostname', `key` = '$apiKey' WHERE `id` = $id";
                $flag = Db::getInstance()->execute($sql);
                if (!$flag)
                {
                    $this->_showMessage(Db::getInstance()->getMsgError(), 'danger');
                    return false;
                }

                $this->_showMessage($this->_('your_credentials_have_been_saved'), 'success');
            } else {
                $sql = "INSERT INTO `{$db_prefix}mailrelay`(`id`, `hostname`, `key`, `last_group`) VALUES(NULL, '$hostname', '$apiKey', 0)";
                $flag = Db::getInstance()->execute($sql);

                if (!$flag)
                {
                    $this->_showMessage(Db::getInstance()->getMsgError(), 'danger');
                    return false;
                }

                $id = Db::getInstance()->Insert_ID();
                $this->_showMessage($this->_('your_credentials_have_been_saved'), 'success');
            }

            return array(
                'id' => $id,
                'hostname' => $hostname,
                'key' => $apiKey
            );
        } catch(Exception $e) {
            $this->_showMessage(sprintf($this->_('unable_to_connect_to'), $hostname), 'danger');
            return false;
        }
    }

    protected function _showMessage($message, $type) {
        global $smarty;

        $smarty->assign('mailrelay_message', $message);
        $smarty->assign('mailrelay_message_type', $type);
    }

    /**
     *
     * @return Zend_Config
     */
    protected function _getParams() {
        return new Zend_Config($_POST, true);
    }

    /**
     *
     * @param string $templateName
     * @return string
     */
    protected function _fetchTemplate($templateName) {
        $pathinfo = pathinfo(__FILE__);
        $fileName = "{$pathinfo['dirname']}/{$templateName}.tpl";
        return $this->context->smarty->fetch($fileName);
    }

    /**
     *
     * @param string $hostname
     * @param string $apiKey
     * @param string $function
     * @param array $params
     * @return array null
     */
    protected function _execute($hostname, $apiKey, $function, array $params = array()) {
        $result = null;

        $uri = Zend_Uri_Http::fromString('https://example.com/ccm/admin/api/version/2/&type=json');
        $uri->setHost($hostname);

        $config = array('adapter' => 'Zend_Http_Client_Adapter_Curl', 'curloptions' => array(CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSLVERSION => 3));

        $client = new Zend_Http_Client($uri, $config);
        $client->setHeaders('X-Request-Origin: Prestashop|1.11|'. _PS_VERSION_);

        $params['function'] = $function;
        $params['apiKey'] = $apiKey;
        $client->setParameterPost($params);

        $response = $client->request(Zend_Http_Client::POST);

        if (200 == $response->getStatus()) {
            $responseBody = $response->getBody();
            $result = Zend_Json::decode($responseBody);
        }

        return $result;
    }


    protected function _getLang()
    {
        //return array('iso_code' => 'es');

        global $cookie;
        $id_lang =(int)$cookie->id_lang;
        $db_prefix = _DB_PREFIX_;

        $sql = "SELECT iso_code FROM `{$db_prefix}lang` WHERE `id_lang` = $id_lang";
        $row = Db::getInstance()->getRow($sql);
        return $row;
    }

    protected function _($text)
    {
        if (!$this->_lang)
        {
            $rowLang = $this->_getLang();
            if ($rowLang)
            {
                $this->_lang = $rowLang['iso_code'];
            }
        }

        if (false === $this->_arrLang)
        {
            $dirname = dirname(__FILE__) . '/lang';
            $fileName = "$dirname/{$this->_lang}.php";
            $exist = true;

            if (!file_exists($fileName))
            {
                if ($this->_lang != $this->_defaultLang)
                {
                    $fileName = "$dirname/{$this->_defaultLang}.php";
                    if (!file_exists($fileName))
                    {
                        $exist = false;
                    }
                }
                else
                {
                    $exist = false;
                }

            }

            if ($exist)
            {
                $this->_arrLang = include_once $fileName;
            }
            else
            {
                $this->_arrLang = array();
            }
        }

        if (!$this->_translate)
        {
            $config = array(
                'adapter' => 'array',
                'content' => $this->_arrLang,
                'locale' => $this->_lang
           );

            $this->_translate = new Zend_Translate($config);
        }

        return $this->_translate->_($text);
    }
}