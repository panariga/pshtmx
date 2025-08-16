<?php

/**
 * 2007-2023 PrestaShop
 * ... (Your license headers) ...
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PsHtmx extends Module
{
    // Define constants for configuration keys to avoid typos
    const CONFIG_URL = 'PSHTMX_SCRIPT_URL';
    const CONFIG_HASH = 'PSHTMX_SRI_HASH';

    public function __construct()
    {
        $this->name = 'pshtmx';
        $this->tab = 'front_office_features';
        $this->version = '2.0.0';
        $this->author = 'panariga';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('HTMX Integration (Configurable)');
        $this->description = $this->l('Provides a specific version of htmx.js to the shop front and admin. Requires configuration.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall? All settings will be lost.');
    }

    public function install()
    {
        Configuration::updateValue(self::CONFIG_URL, '');
        Configuration::updateValue(self::CONFIG_HASH, '');

        return parent::install() &&
            $this->installTab() && // Add this line
            $this->registerHook('actionFrontControllerSetMedia') &&
            $this->registerHook('actionAdminControllerSetMedia');
    }
    /**
     * Install an admin controller tab.
     * This is necessary for PrestaShop to recognize our AJAX controller.
     */
    public function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminPsHtmxAjax'; // This must match the controller class name without 'Controller'
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'HTMX AJAX';
        }
        $tab->id_parent = -1; // Hide from menu
        $tab->module = $this->name;
        return $tab->add();
    }
    public function uninstall()
    {
        Configuration::deleteByName(self::CONFIG_URL);
        Configuration::deleteByName(self::CONFIG_HASH);

        return $this->uninstallTab() && parent::uninstall(); // Add this line
    }
    /**
     * Uninstall the admin controller tab.
     */
    public function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminPsHtmxAjax');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }
    /**
     * Module configuration page
     */
    public function getContent()
    {
        $output = '';

        // Process form submission
        if (Tools::isSubmit('submit' . $this->name)) {
            $scriptUrl = (string) Tools::getValue(self::CONFIG_URL);
            $sriHash = (string) Tools::getValue(self::CONFIG_HASH);

            // Basic validation
            if (empty($scriptUrl) || !Validate::isAbsoluteUrl($scriptUrl)) {
                $output .= $this->displayError($this->l('The script URL is invalid.'));
            } elseif (empty($sriHash)) {
                $output .= $this->displayError($this->l('The SRI hash is required for security.'));
            } else {
                Configuration::updateValue(self::CONFIG_URL, $scriptUrl);
                Configuration::updateValue(self::CONFIG_HASH, $sriHash);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        // Create the secure URL to our new AJAX controller
        $ajax_url = $this->context->link->getAdminLink('AdminPsHtmxAjax', true, [], [
            'ajax' => 1,
            'action' => 'fetchLatestHtmx' // This tells our controller which method to run
        ]);

        // Pass this URL to our JavaScript file
        Media::addJsDef(['pshtmx_ajax_url' => $ajax_url]);

        // Add our custom JavaScript to the configuration page
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');

        return $output . $this->renderHelperForm();
    }

    /**
     * Renders the configuration form using HelperForm
     */
    protected function renderHelperForm()
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Form definition
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('HTMX Settings'),
                'icon' => 'icon-cogs',
            ],
            'input' => [
                [
                    'type' => 'html',
                    'name' => 'fetch_helper',
                    'html_content' => '<div class="alert alert-info">' .
                        $this->l('Use the button below to automatically fetch the latest stable version and its security hash from the unpkg CDN. You can also manually enter values from another source.') .
                        '<br><br><button type="button" id="fetch-latest-htmx" class="btn btn-default"><i class="icon-download"></i> ' . $this->l('Fetch Latest HTMX Version Info') . '</button>' .
                        '</div>',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('HTMX Script URL'),
                    'name' => self::CONFIG_URL,
                    'required' => true,
                    'desc' => $this->l('The full URL to the htmx.min.js file.'),
                    'class' => 'lg',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('SRI Hash'),
                    'name' => self::CONFIG_HASH,
                    'required' => true,
                    'desc' => $this->l('The Subresource Integrity hash for the script (e.g., sha384-...).'),
                    'class' => 'lg',
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right',
                'name' => 'submit' . $this->name,
            ],
        ];

        // Load current values
        $helper->fields_value[self::CONFIG_URL] = Configuration::get(self::CONFIG_URL);
        $helper->fields_value[self::CONFIG_HASH] = Configuration::get(self::CONFIG_HASH);

        return $helper->generateForm($fields_form);
    }

    public function hookActionFrontControllerSetMedia()
    {
        $htmx_url = Configuration::get(self::CONFIG_URL);
        $sri_hash = Configuration::get(self::CONFIG_HASH);

        // Only load the script if it has been configured
        if (!empty($htmx_url) && !empty($sri_hash)) {
            $this->context->controller->registerJavascript(
                'module-pshtmx-htmx',
                $htmx_url,
                [
                    'server' => 'remote',
                    'position' => 'bottom',
                    'priority' => 200,
                    'attributes' => 'integrity="' . $sri_hash . '" crossorigin="anonymous"',
                ]
            );
        }
    }

    public function hookActionAdminControllerSetMedia()
    {
        $htmx_url = Configuration::get(self::CONFIG_URL);

        // Only load the script if it has been configured
        if (!empty($htmx_url)) {
            $this->context->controller->addJS($htmx_url);
        }
    }
}
