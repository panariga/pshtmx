<?php

class AdminPsHtmxAjaxController extends ModuleAdminController
{
    /**
     * This controller handles AJAX requests for the pshtmx module.
     * It's not displayed in the menu.
     */
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /**
     * This is the entry point for our AJAX actions.
     * PrestaShop looks for methods named ajaxProcess[ActionName]
     */
    public function ajaxProcessFetchLatestHtmx()
    {
        $latestUrl = 'https://unpkg.com/htmx.org@latest/dist/htmx.min.js';

        // We must use cURL to handle redirects and get the final URL.
        // Tools::file_get_contents does not provide this functionality.
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $latestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the transfer as a string
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);         // Limit redirects
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);          // Timeout in seconds
        curl_setopt($ch, CURLOPT_USERAGENT, 'PrestaShop-PsHtmx-Module/2.1'); // Be a good internet citizen

        $scriptContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);

        curl_close($ch);

        // --- Error Handling ---
        if ($error) {
            $this->jsonError($this->l('cURL Error: ') . $error);
        }

        if ($httpCode !== 200) {
            $this->jsonError(sprintf($this->l('The CDN returned a non-200 status code: %d'), $httpCode));
        }

        if (empty($scriptContent) || empty($finalUrl)) {
            $this->jsonError($this->l('Received an empty response from the CDN.'));
        }

        // --- Success: Calculate Hash ---
        // 1. Calculate the raw binary hash of the content
        $rawHash = hash('sha384', $scriptContent, true);
        // 2. Base64-encode the raw hash
        $base64Hash = base64_encode($rawHash);
        // 3. Prepend the algorithm prefix for the final SRI string
        $sriHash = 'sha384-' . $base64Hash;

        // --- Send the successful response ---
        header('Content-Type: application/json');
        die(json_encode([
            'resolved_url' => $finalUrl,
            'sri_hash' => $sriHash,
        ]));
    }
}
