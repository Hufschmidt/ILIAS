<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 ********************************************************************
 */


/**
 * Class ilCronStatusMonitorPlugin
 * @author Thomas Famula <famula@leifos.de>
 */
class ilCronStatusMonitorPlugin extends ilCronHookPlugin
{
    private static ?ilCronStatusMonitorPlugin $instance = null;

    const PNAME = "CronStatusMonitor";
    const PLUGIN_ID = "cronstatusmonitor";

    public static function getInstance(): ilCronStatusMonitorPlugin
    {
        global $DIC;
        if (isset(self::$instance)) {
            return self::$instance;
        }
        /** @var ilComponentFactory $component_factory */
        $component_factory = $DIC["component.factory"];
        /** @var ilCronStatusMonitorPlugin $plugin */
        $plugin = $component_factory->getPlugin(self::PLUGIN_ID);
        return $plugin;
    }

    public function getPluginName() : string
    {
        return self::PNAME;
    }

    public function getCronJobInstances() : array
    {
        $job = new ilCronStatusMonitorCronJob($this);
        return array($job);
    }

    public function getCronJobInstance(string $jobId) : ilCronStatusMonitorCronJob
    {
        return new ilCronStatusMonitorCronJob($this);
    }

    /**
     * Delete the database tables, which were created for the plugin, when the plugin became uninstalled
     */
    protected function afterUninstall() : void
    {
        global $ilDB;

        if ($ilDB->tableExists('crn_sts_mtr')) {
            $ilDB->dropTable("crn_sts_mtr");
        }

        if ($ilDB->tableExists('crn_sts_mtr_settings')) {
            $ilDB->dropTable("crn_sts_mtr_settings");
        }
    }
}
