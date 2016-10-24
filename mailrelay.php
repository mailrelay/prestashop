<?php

if (!defined('_PS_VERSION_'))
    exit;


class MailRelay extends Module
{
    public function __construct()
    {
        $this->name = 'mailrelay';
        $this->tab = 'administration';
        $this->version = '1.3.2';
        $this->author = '';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Mailrelay');
        $this->description = $this->l('');
    }

    public function install()
    {
        if ($id_tab = Tab::getIdFromClassName('AdminMailRelay'))
        {
            $tab = new Tab((int)$id_tab);
            if (!$tab->delete())
                $this->_errors[] = sprintf($this->l('Unable to delete outdated AdminMailRelay tab %d'), (int)$id_tab);
        }

        if (!$id_tab = Tab::getIdFromClassName('AdminMailRelay'))
        {
            $tab = new Tab();
            $tab->class_name = 'AdminMailRelay';
            $tab->module = 'mailrelay';
            $tab->id_parent = (int)Tab::getIdFromClassName('AdminTools');
            foreach (Language::getLanguages(false) as $lang)
                $tab->name[(int)$lang['id_lang']] = 'Mailrelay';

            if (!$tab->save())
                return $this->_abortInstall($this->l('Unable to create the "AdminMailRelay" tab'));
        }
        else
            $tab = new Tab((int)$id_tab);


        /* Update the "AdminMailRelay" tab id in database or exit */
        if (Validate::isLoadedObject($tab))
            Configuration::updateValue('PS_AUTOUPDATE_MODULE_IDTAB', (int)$tab->id);
        else
            return $this->_abortInstall($this->l('Unable to load the "AdminMailRelay" tab'));

        /* Check working directory is existing or create it */
        $module_dir = _PS_ADMIN_DIR_.DIRECTORY_SEPARATOR.'mailrelay';
        if (!file_exists($module_dir) && !@mkdir($module_dir, 0755))
            return $this->_abortInstall(sprintf($this->l('Unable to create the directory "%s"'), $module_dir));

        /* Make sure that the 1-click upgrade working directory is writeable */
        if (!is_writable($module_dir))
            return $this->_abortInstall(sprintf($this->l('Unable to write in the directory "%s"'), $module_dir));

        Db::getInstance()->Execute('
                CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'mailrelay` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `hostname` VARCHAR(255) NOT NULL,
                    `key` VARCHAR(255) NOT NULL,
                    `last_group` CHAR(20),
                    PRIMARY KEY(`id`)
                ) ENGINE='._MYSQL_ENGINE_.' default CHARSET=utf8');

        return parent::install();
    }


    public function uninstall()
    {
        /* Delete Back-office tab */
        if ($id_tab = Tab::getIdFromClassName('AdminMailRelay'))
        {
            $tab = new Tab((int)$id_tab);
            $tab->delete();

            Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'mailrelay`');
        }

        /* Remove the working directory */
        self::_removeDirectory(_PS_ADMIN_DIR_.DIRECTORY_SEPARATOR.'mailrelay');

        return parent::uninstall();
    }


    public function getContent()
    {
        global $cookie;
        header('Location: index.php?tab=AdminMailRelay&token='.md5(pSQL(_COOKIE_KEY_.'AdminMailRelay'.(int)Tab::getIdFromClassName('AdminMailRelay').(int)$cookie->id_employee)));
        exit;
    }

    /**
     * Set installation errors and return false
     *
     * @param string $error Installation abortion reason
     * @return boolean Always false
     */
    protected function _abortInstall($error)
    {
        if (version_compare(_PS_VERSION_, '1.5.0.0 ', '>='))
            $this->_errors[] = $error;
        else
            echo '<div class="error">'.strip_tags($error).'</div>';

        return false;
    }


    private static function _removeDirectory($dir)
    {
        if ($handle = @opendir($dir))
        {
            while (false !== ($entry = @readdir($handle)))
                if ($entry != '.' && $entry != '..')
                {
                    if (is_dir($dir.DIRECTORY_SEPARATOR.$entry) === true)
                        self::_removeDirectory($dir.DIRECTORY_SEPARATOR.$entry);
                    else
                        @unlink($dir.DIRECTORY_SEPARATOR.$entry);
                }

                @closedir($handle);
                @rmdir($dir);
        }
    }
}