<?php

include_once("./Services/Cron/classes/class.ilCronHookPlugin.php");

class ilMultiVcBBBCleanPlugin extends ilCronHookPlugin
{
	function getPluginName(): string
	{
		return "MultiVcBBBClean";
	}

	function getCronJobInstances(): array
	{
		return array($this->getCronJobInstance('multivcbbb_clean'));
	}

	function getCronJobInstance(string $jobId): ilCronJob
	{
		require_once('class.ilMultiVcBBBCleanJob.php');
		return new ilMultiVcBBBCleanJob($this);
	}

	/**
	* Delete the database tables, which were created for the plugin, when the plugin became uninstalled
	*/
	function afterUninstall(): void
	{
		// Nothing todo
	}
}