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
                $result = $this->_sync($params, $this->_getCredentials());
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
            $client = $this->_getClient($credentials['hostname'], $credentials['key']);
            $params['enable'] = true;
            $result = $this->_execute($client, 'getGroups', $params);

            if ($result && $result['status']) {
                foreach($result['data'] as $item) {
                    $options[$item['id']] = $item['name'];
                }
            }
        }

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

    protected function _sync(Zend_Config $data, $credential) {
        $group = abs((int) $data->mailrelay_group);
        if ($group) {
            $db_prefix = _DB_PREFIX_;
            $summary['total'] = 0;
            $summary['new'] = 0;
            $summary['updated'] = 0;
            $summary['failed'] = 0;

            $client = $this->_getClient($credential['hostname'], $credential['key']);

            $data->mailrelay_group;
            $sql = "SELECT * FROM `{$db_prefix}customer` WHERE `newsletter` = 1";
            $rowset = Db::getInstance()->executeS($sql);
            foreach($rowset as $row) {
                $name = "{$row['firstname']} {$row['lastname']}";
                $email = $row['email'];

                $params = array();
                $params['email'] = $email;
                $result = $this->_execute($client, 'getSubscribers', $params);

                if ($result) {
                    $summary['total']++;

                    if (! count($result['data'])) {
                        $params['name'] = $name;
                        $params['groups'] = array(
                                $group
                       );
                        $result = $this->_execute($client, 'addSubscriber', $params);

                        if ($result && 1 == $result['status'])
                            $summary['new'] ++;
                        else
                        {
                            $summary['failed'] ++;
                        }
                    } else {
                        $params['id'] = $result['data'][0]['id'];
                        $params['name'] = $name;
                        $params['groups'] = array(
                                $group
                       );
                        $result = $this->_execute($client, 'updateSubscriber', $params);

                        if ($result && 1 == $result['status'])
                            $summary['updated'] ++;
                        else
                        {
                            $summary['failed'] ++;
                        }
                    }
                } else {
                    $summary['failed'] ++;
                }
            }

            // update the last selected group
            $sql = "UPDATE `{$db_prefix}mailrelay` SET last_group = $group";
            Db::getInstance()->execute($sql);

            $message = '';
            $message .= "{$this->_('total_subscribers')}:({$summary['total']})<br />";
            $message .= "{$this->_('new_subscribers')}:({$summary['new']})<br />";
            $message .= "{$this->_('updated_subscribers')}:({$summary['updated']})<br />";
            $message .= "{$this->_('failed_subscribers')}:({$summary['failed']})<br />";

            $this->_showMessage($message, 'conf');
        } else {
            $this->_showMessage($this->_('please_select_a_group'), 'error');
        }
    }

    /**
     *
     * @param Zend_Config $data
     * @return boolean array
     */
    protected function _saveCredential(Zend_Config $data) {
        global $smarty;
        $hostname = Db::getInstance()->escape($data->mailrelay_hostname);
        $apiKey = Db::getInstance()->escape($data->mailrelay_key);

        if (empty($hostname) || empty($apiKey)) {
            $this->_showMessage($this->_('please_fill_in_all_required_fields'), 'error');
            return false;
        }

        $validate = new Zend_Validate_Hostname();
        if (! $validate->isValid($hostname)) {
            $this->_showMessage($this->_('please_provide_a_valid_hostname'), 'error');
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
                    $this->_showMessage(Db::getInstance()->getMsgError(), 'error');
                    return false;
                }

                $this->_showMessage($this->_('your_credentials_have_been_saved'), 'conf');
            } else {
                $sql = "INSERT INTO `{$db_prefix}mailrelay`(`id`, `hostname`, `key`, `last_group`) VALUES(NULL, '$hostname', '$apiKey', 0)";
                $flag = Db::getInstance()->execute($sql);

                if (!$flag)
                {
                    $this->_showMessage(Db::getInstance()->getMsgError(), 'error');
                    return false;
                }

                $id = Db::getInstance()->Insert_ID();
                $this->_showMessage($this->_('your_credentials_have_been_saved'), 'conf');
            }

            return array(
                'id' => $id,
                'hostname' => $hostname,
                'key' => $apiKey
            );
        } catch(Exception $e) {
            $this->_showMessage(sprintf($this->_('unable_to_connect_to'), $hostname), 'error');
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
     * @param string $hostName
     * @param string $apiKey
     * @return Zend_Http_Client
     */
    protected function _getClient($hostName, $apiKey = null) {
        $uri = Zend_Uri_Http::fromString('http://example.com/ccm/admin/api/version/2/&type=json');
        $uri->setHost($hostName);

        $client = new Zend_Http_Client();
        $client->setUri($uri);

        if ($apiKey)
            $client->setParameterPost('apiKey', $apiKey);

        return $client;
    }

    /**
     *
     * @param Zend_Http_Client $client
     * @param string $function
     * @param array $params
     * @return array null
     */
    protected function _execute(Zend_Http_Client $client, $function, array $params = array()) {
        $result = null;
        $client->setParameterPost('function', $function);

        foreach($params as $key => $value)
            $client->setParameterPost($key, $value);

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