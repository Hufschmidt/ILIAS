<?php

include_once("./Services/Cron/classes/class.ilCronHookPlugin.php");

class ilUMRUserPlugin extends ilCronHookPlugin
{
	function getPluginName(): string
	{
		return "UMRUser";
	}

	function getCronJobInstances(): array
	{
		return array($this->getCronJobInstance('UMRU'));
	}

	function getCronJobInstance(string $jobId): ilCronJob
	{
		require_once('class.ilUMRUserJob.php');
		return new ilUMRUserJob($this);
	}

	/**
	* Delete the database tables, which were created for the plugin, when the plugin became uninstalled
	*/
	function afterUninstall(): void
	{
		global $ilDB;

		if ($ilDB->tableExists('cron_umruser_config')) {
			$ilDB->dropTable("cron_umruser_config");
		}
	}
}