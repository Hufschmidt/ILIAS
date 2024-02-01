<?php

include_once("./Services/Cron/classes/class.ilCronHookPlugin.php");

class ilExpiredUserCronPlugin extends ilCronHookPlugin
{
	function getPluginName(): string
	{
		return "ExpiredUserCron";
	}

	function getCronJobInstances(): array
	{
		return array($this->getCronJobInstance('exuser_cron'));
	}

	function getCronJobInstance(string $jobId): ilCronJob
	{
		require_once('class.ilExpiredUserCronJob.php');
		return new ilExpiredUserCronJob($this);
	}

	/**
	* Delete the database tables, which were created for the plugin, when the plugin became uninstalled
	*/
	function afterUninstall(): void
	{
		global $ilDB;

		if ($ilDB->tableExists('cron_exuser_config')) {
			$ilDB->dropTable("cron_exuser_config");
		}
	}
}