<?php

namespace Kanboard\Plugin\PluginManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Plugin\Directory;
use Kanboard\Core\Plugin\PluginInstallerException;
use Kanboard\Plugin\PluginManager\Controller\Installer;
use ZipArchive;

/**
 * Plugin PluginManager
 * Class PluginManagerController
 *
 * @author aljawaid
 * @author alfredbuehler
 */

class PluginManagerController extends \Kanboard\Controller\PluginController
{
    /**
     * Display the Plugin Problems Page
     *
     * @access public
     */

    public function show()
    {
        $this->response->html($this->helper->layout->plugin('pluginManager:info/plugin-problems', array(
            'title' => t('Plugin Manager') . ' &#10562; ' . t('Plugin Problems'),
        )));
    }

    /**
     * Display the Plugin Info Page
     *
     * @access public
     */

    public function showPluginInfo()
    {
        $this->response->html($this->helper->layout->plugin('pluginManager:info/plugin-info', array(
            'title' => t('Plugin Manager') . ' &#10562; ' . t('Plugin Info'),
            'available_plugins' => Directory::getInstance($this->container)->getAvailablePlugins()
        )));
    }

    /**
     * Display the Manual Plugins Page
     *
     * @access public
     */

    public function showManualPlugins()
    {
        $this->response->html($this->helper->layout->plugin('pluginManager:plugin/manual-plugins', array(
            'title' => t('Plugin Manager') . ' &#10562; ' . t('Manual Plugins'),
        )));
    }

    /**
     * Install a plugin
     *
     * @author alfredbuehler
     */
    public function installPlugin()
    {
        $rc = false;

        if (strlen($archiveURL = urldecode($this->request->getValue('plugin_url'))) > 0) {
            $rc = $this->installByURL($archiveURL);
        }

        if (strlen($archiveFile = $this->request->getFilePath('plugin_file')) > 0) {
            $rc = $this->installByFile($archiveFile);
        }

        $this->response->redirect($rc
            ? $this->helper->url->to('PluginController', 'show')
            : $this->helper->url->to('PluginManagerController', 'showManualPlugins', array('plugin' => 'PluginManager')));
    }

    /**
     * Install a plugin from URL
     *
     * @param string $archiveUrl
     * @return bool
     * @author alfredbuehler
     */
    private function installByURL(string $archiveUrl): bool
    {
        try {
            $installer = new Installer($this->container);
            $archiveFile = $installer->downloadPluginArchive($archiveUrl);
            $this->installByFile($archiveFile);
        } catch (PluginInstallerException $e) {
            $this->flash->failure($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Install a plugin from file
     *
     * @param string $archiveFile
     * @return bool
     * @author alfredbuehler
     */
    private function installByFile(string $archiveFile): bool
    {
        if (file_exists($archiveFile)) {
            $zip = new ZipArchive();

            try {
                if ($zip->open($archiveFile) !== true) {
                    throw new PluginInstallerException(t('Unable to open the plugin archive'));
                }

                $dirname = getPluginDir($zip);
                $plugin = getPluginFile($zip, $dirname);
                $namespace = getPluginNamespace($plugin);

                if ($dirname != "$namespace/") {
                    throw new PluginInstallerException(t('The directory name (%s) does not match with the namespace (%s)', $dirname, $namespace));
                }

                $pluginName = getPluginName($plugin);

                if ($pluginName != $namespace) {
                    throw new PluginInstallerException(t('The plugin name (%s) does not match with the namespace (%s)', $pluginName, $namespace));
                }

                if (!$zip->extractTo(PLUGINS_DIR)) {
                    $zip->close();
                    throw new PluginInstallerException(t('Unable to extract the plugin archive'));
                }

                // Success
                $zip->close();
                unlink($archiveFile);
                $this->flash->success(t('Plugin installed successfully'));
            } catch (PluginInstallerException $e) {
                unlink($archiveFile);
                $this->flash->failure($e->getMessage());

                return false;
            }
        } else {
            $this->flash->failure(t('Plugin archive file not found'));

            return false;
        }

        return true;
    }
}

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
/**
 * Different class in file
 * Added by @author
 *
 * @author alfredbuehler
 */
class Installer extends \Kanboard\Core\Plugin\Installer
{
    /**
     *  Download archive
     *
     * @param string $archiveUrl
     * @return string  Downloaded $archiveFile
     * @author alfredbuehler
     */
    public function downloadPluginArchive($archiveUrl): string
    {
        $zip = parent::downloadPluginArchive($archiveUrl); // zip is open!
        $archiveFile = $zip->filename;
        $zip->close();

        return $archiveFile;
    }
}
//phpcs enable

/**
 * Get the directory name
 *
 * @param ZipArchive open zip
 * @return string directory name in archive
 * @author alfredbuehler
 */
function getPluginDir(ZipArchive $zip): string
{
    $dirname = $zip->getNameIndex(0);
    if ($dirname === false) {
        throw new PluginInstallerException(t('Plugin directory name was not found'));
    }

    return $dirname;
}

/**
 * Get the Plugin.php
 *
 * @param ZipArchive open zip
 * @param string directory name in archive
 * @return string content of Plugin.php
 * @author alfredbuehler
 */
function getPluginFile(ZipArchive $zip, string $dirname): string
{
    $plugin = $zip->getFromName($dirname . 'Plugin.php');
    if ($plugin === false) {
        throw new PluginInstallerException(t('File \'Plugin.php\' could not get extracted'));
    }

    return $plugin;
}

/**
 * Get the namespace
 *
 * @param string content of Plugin.php
 * @return string namespace of plugin
 * @author alfredbuehler
 */
function getPluginNamespace(string $plugin): string
{
    $match = [];

    $rc = preg_match("/^namespace Kanboard\\\\Plugin\\\\(\w{1,});/m", $plugin, $match);
    if ($rc == false || $rc != 1) {
        throw new PluginInstallerException('The namespace was not found');
    }
    $namespace = $match[1];

    return $namespace;
}

/**
 * Get the plugin's name
 *
 * @param string content of Plugin.php
 * @return string name of plugin
 * @author alfredbuehler
 */
function getPluginName(string $plugin): string
{
    $match = [];

    $rc = preg_match('/(public function getPluginName\(\))(.|\n)*return ["\'](.*)["\']/U', $plugin, $match);
    if ($rc == false || $rc != 1) {
        throw new PluginInstallerException(t('The plugin name was not found'));
    }
    $pluginName = $match[3];

    return $pluginName;
}
