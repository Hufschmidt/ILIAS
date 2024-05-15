<?php

namespace srag\RemovePluginDataConfirm\OnlyOffice;

/**
 * Trait PluginUninstallTrait
 *
 * @package srag\RemovePluginDataConfirm\OnlyOffice
 */
trait PluginUninstallTrait
{

    use BasePluginUninstallTrait;

    /**
     * @internal
     */
    protected final function afterUninstall()/* : void*/
    {

    }


    /**
     * @return bool
     *
     * @internal
     */
    protected final function beforeUninstall() : bool
    {
        return $this->pluginUninstall();
    }
}
