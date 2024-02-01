<?php

include_once "Services/Cron/classes/class.ilCronJob.php";

class ilExpiredUserCronJob  extends ilCronJob
{
	protected $plugin;

	public function __construct($plugin)
	{
		$this->plugin = $plugin;
	}

	public function getId(): string
	{
		return "exuser_cron";
	}

	public function getTitle(): string
	{
		return $this->plugin->txt('job_title');
	}

	public function getDescription(): string
	{
		return $this->plugin->txt('job_description');
	}

	public function getDefaultScheduleType(): int
	{
		return self::SCHEDULE_TYPE_DAILY;
	}

	public function getDefaultScheduleValue(): ?int
	{
		return null;
	}

	public function hasAutoActivation(): bool
	{
		return false;
	}

	public function hasFlexibleSchedule(): bool
	{
		return true;
	}

	public function hasCustomSettings(): bool
	{
		return true;
	}

	/**
	 * Defines whether or not a cron job can be started manually
	 * @return bool
	 */
	public function isManuallyExecutable(): bool
	{
		return parent::isManuallyExecutable();
	}

	/**
	 * Run the cron job
	 * @return ilCronJobResult
	 */
	public function run(): ilCronJobResult
	{
		global $DIC;
		$rbacreview = $DIC['rbacreview'];

		// Get the number of days ago that we should consider accounts as expired and the ID of the role we are interested in
		$days_ago = $this->getDaysAgo();
		$role_id = $this->getRoleID();
		// Calculate the timestamp corresponding to the specified number of days ago
		$timestamp = strtotime("-$days_ago days");
		// Get the IDs of all expired user accounts that were last active before the specified timestamp.
		$usr_ids = $this->getExpiredUsers($timestamp);

		$counter = 0;
		foreach ($usr_ids as $usr_id) {
			// Skip anonymous and system users
			if ($usr_id == ANONYMOUS_USER_ID || $usr_id == SYSTEM_USER_ID) {
				continue;
			}
			// Skip users that are not assigned to the specified role.
			if (!$rbacreview->isAssigned($usr_id, $role_id)) {
				continue;
			}
			// Delete the user account
			$user = ilObjectFactory::getInstanceByObjId($usr_id);
			$user->delete();
			$counter++;
		}

		$result = new ilCronJobResult();
		$result->setMessage($counter . ' account deleted');
		return $result;
	}

	/**
	 * Add custom settings to form
	 *
	 * @param ilPropertyFormGUI $a_form
	 * @throws ilDateTimeException
	 */
	public function addCustomSettingsToForm(ilPropertyFormGUI $a_form): void {

		$setDaysAgo = new ilNumberInputGUI($this->plugin->txt('days_ago'), 'set_days_ago');
		$setDaysAgo->setInfo($this->plugin->txt('days_ago_info'));
		$setDaysAgo->allowDecimals(false);
		$setDaysAgo->setSize(4);
		$setDaysAgo->setMaxLength(4);
		$setDaysAgo->setRequired(true);
		$setDaysAgo->setValue($this->getDaysAgo());
		$a_form->addItem($setDaysAgo);

		$setRoleID = new ilNumberInputGUI($this->plugin->txt('role_id'), 'set_role_id');
		$setRoleID->setInfo($this->plugin->txt('role_id_info'));
		$setRoleID->allowDecimals(false);
		$setRoleID->setSize(8);
		$setRoleID->setMaxLength(8);
		$setRoleID->setRequired(true);
		$setRoleID->setValue($this->getRoleID());
		$a_form->addItem($setRoleID);
	}

	/**
	 * Save custom settings
	 *
	 * @param ilPropertyFormGUI $a_form
	 * @return boolean
	 */
	public function saveCustomSettings(ilPropertyFormGUI $a_form): bool {

		$DaysAgo = $a_form->getInput("set_days_ago");
		$RoleID = $a_form->getInput("set_role_id");

		$this->setConfig($DaysAgo, $RoleID);

		return true;
	}

	private function getExpiredUsers($timestamp): array {
		global $ilDB;

		$set = $ilDB->query("SELECT usr_id FROM usr_data WHERE time_limit_unlimited = 0 AND time_limit_until < " . $timestamp);

		$user_ids = array();
		while ($rec = $ilDB->fetchAssoc($set)) {
			$user_ids[] = $rec["usr_id"];
		}

		return $user_ids;
	}

	private function getDaysAgo(): ?int {
		global $ilDB;

		$set = $ilDB->query("SELECT * FROM cron_exuser_config");
		if ($rec = $ilDB->fetchAssoc($set)) {
			return $rec["days_ago"];
		}
		return null;
	}

	private function getRoleID(): ?int {
		global $ilDB;

		$set = $ilDB->query("SELECT * FROM cron_exuser_config");
		if ($rec = $ilDB->fetchAssoc($set)) { 
			return $rec["role_id"];
		}
		return null;
	}

	private function setConfig($DaysAgo, $RoleID): void {
		global $ilDB;
	
		$ilDB->manipulate("DELETE FROM cron_exuser_config");
		$ilDB->manipulate("INSERT INTO cron_exuser_config (days_ago,role_id) VALUES (" . $ilDB->quote($DaysAgo, "integer"). "," . $ilDB->quote($RoleID, "integer"). ")");
	}
}