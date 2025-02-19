<?php

namespace Kanboard\Plugin\PluginManager\Helper;

use Kanboard\Core\Base;
use Kanboard\Core\Http\Client;
use Kanboard\Core\Plugin\Directory;

class PluginManagerHelper extends Base
{
    public function checkRootDomain($url)
    {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "http://" . $url;
        }
        $domain = implode('.', array_slice(explode('.', parse_url($url, PHP_URL_HOST)), -2));
        switch ($domain) {
            case 'github.com':
                return '/blob/master/README.md';
            case 'gitlab.com':
                return '/-/blob/master/README.md';
            case 'gitea.com':
                return '/src/branch/main/README.md';
            default:
                return false;
        }
    }

    public function countTypes($available_plugins)
    {
        $types = array();
        foreach ($available_plugins as $plugin) {
            if (isset($plugin['is_type']) && in_array($plugin['is_type'], ['plugin', 'action', 'theme', 'multi', 'connector'], false)) {
                array_push($types, $plugin['is_type']);
            }
        }
        $count_types = array_count_values($types);
        return $count_types;
    }

    public function getAllPlugins($url = PLUGIN_API_URL)
    {
        return array_filter($this->httpClient->getJson($url), array($this, 'isNotInstallable'));
    }

    public function isNotInstallable(array $plugin)
    {
        return $plugin['remote_install'] === false;
    }

    public function lastUpdatedDirectory()
    {
        $curlCheck = $this->httpClient->backend();

        if ($curlCheck == 'cURL') {
            // https://www.appsloveworld.com/php/12/get-the-last-modified-date-of-a-remote-file
            $curl = curl_init(PLUGIN_API_URL);

            // don't fetch the actual page, you only want headers
            curl_setopt($curl, CURLOPT_NOBODY, true);

            // stop it from outputting stuff to stdout
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            // attempt to retrieve the modification date
            curl_setopt($curl, CURLOPT_FILETIME, true);

            // set HTTP_VERIFY_SSL_CERTIFICATE from config.php
            if (HTTP_VERIFY_SSL_CERTIFICATE === false) {
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            }

            $result = curl_exec($curl);

            if ($result === false) {
                die(curl_error($curl));
            }

            $timestamp = curl_getinfo($curl, CURLINFO_FILETIME);

            if ($timestamp != -1) { // otherwise unknown
                return date("d F Y H:i", $timestamp); // etc
            }
        } else {
            return t('Not Available');
        }
    }

    /**
     * Create list of updatable plugins.
     *
     * @return array with plugin titles
     */
    public function getPluginUpdates(): array
    {
        $installed_plugins = array();
        $updatables = array();

        foreach ($this->pluginLoader->getPlugins() as $plugin) {
            $installed_plugins[$plugin->getPluginName()] = $plugin->getPluginVersion();
        }

        foreach (Directory::getInstance($this->container)->getAvailablePlugins() as $plugin) {
            $proband = &$installed_plugins[$plugin['title']];
            if (isset($proband) && $proband < $plugin['version']) {
                $updatables[$plugin['title']] = $plugin['download'];
            }
        }

        return $updatables;
    }

    /**
     * Get all plugins available
     * Duplicate function of 'Kanboard\Core\Plugin\Directory\getAvailablePlugins()'
     *
     * @access public
     * @param  string $url
     * @return array
     */
    public function getAllInstallablePlugins($url = PLUGIN_API_URL)
    {
        return Directory::getInstance($this->container)->getAvailablePlugins($url);
    }
}
