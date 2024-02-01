<?php

include_once "Services/Cron/classes/class.ilCronJob.php";

class ilUMRUserJob  extends ilCronJob
{
	protected $plugin;

	public function __construct($plugin)
	{
		$this->plugin = $plugin;
	}

	public function getId(): string
	{
		return "UMRU";
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
		return true;
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
	public function run(): ilCronJobResult {
		// get users with role students
		$studentUsers = ilObjUser::_getUsersForRole($this->getRoleIDStudents(),1); // 1 = activ
		// get users with role staff
		$staffUsers = ilObjUser::_getUsersForRole($this->getRoleIDStaff(),1); // 1 = activ
		// start xml writer
		include_once('./Services/Xml/classes/class.ilXmlWriter.php');
		$this->writer = new ilXmlWriter();
		$this->writer->xmlStartTag('Users');
		
		$countUpdateStudents = 0;
		foreach ($studentUsers as $user) {
			// Check if first letter is capital, rest is low and and external account is given
			if (preg_match("/^[a-z]/", $user['login']) && !empty($user['ext_account']) or
				preg_match("/[A-Z]/", substr($user['login'],1)) && !empty($user['ext_account'])) {
				
				$countUpdateStudents++;
				$this->writer->xmlStartTag('User',array('Id' => $user['usr_id'],'Action' => 'Update'));
				$new_login = ucfirst(strtolower($user['login'])); //Make it First capital, rest low
				$this->writer->xmlElement('Login',array(), $new_login);
				$this->writer->xmlEndTag('User');
			}
		}
		
		$countUpdateStaff = 0;
		foreach ($staffUsers as $user) {
			// Check if any letter is capital and and external account is given
			if (preg_match("/[A-Z]/", $user['login']) && !empty($user['ext_account'])) {
				
				$countUpdateStaff++;
				$this->writer->xmlStartTag('User',array('Id' => $user['usr_id'],'Action' => 'Update'));
				$new_login = strtolower($user['login']); //Make low
				$this->writer->xmlElement('Login',array(), $new_login);
				$this->writer->xmlEndTag('User');
			}
		}
		// end xml writer
		$this->writer->xmlEndTag('Users');
		// update user data
		$this->importParsedXML();
		//print Result
		$result = new ilCronJobResult();
		$result->setMessage('Update of '.$countUpdateStudents.' students and '.$countUpdateStaff.' staff');
		return $result;
	}

	/**
	 * Add custom settings to form
	 *
	 * @param ilPropertyFormGUI $a_form
	 * @throws ilDateTimeException
	 */
	public function addCustomSettingsToForm(ilPropertyFormGUI $a_form): void {
		$setRoleIdStudent = new ilNumberInputGUI($this->plugin->txt('set_role_id_students'), 'set_role_id_students');
		$setRoleIdStudent->setInfo($this->plugin->txt('set_role_id_info'));
		$setRoleIdStudent->allowDecimals(false);
		$setRoleIdStudent->setSize(8);
		$setRoleIdStudent->setMaxLength(8);
		$setRoleIdStudent->setRequired(true);
		$setRoleIdStudent->setValue($this->getRoleIDStudents());
		$a_form->addItem($setRoleIdStudent);
		
		$setRoleIdStaff = new ilNumberInputGUI($this->plugin->txt('set_role_id_staff'), 'set_role_id_staff');
		$setRoleIdStaff->setInfo($this->plugin->txt('set_role_id_info'));
		$setRoleIdStaff->allowDecimals(false);
		$setRoleIdStaff->setSize(8);
		$setRoleIdStaff->setMaxLength(8);
		$setRoleIdStaff->setRequired(true);
		$setRoleIdStaff->setValue($this->getRoleIDStaff());
		$a_form->addItem($setRoleIdStaff);
	}

	/**
	 * Save custom settings
	 *
	 * @param ilPropertyFormGUI $a_form
	 * @return boolean
	 */
	public function saveCustomSettings(ilPropertyFormGUI $a_form): bool {

		$RoleIDStudents = $a_form->getInput("set_role_id_students");
		$RoleIDStaff = $a_form->getInput("set_role_id_staff");

		$this->setConfig($RoleIDStudents, $RoleIDStaff);

		return true;
	}

	private function setConfig(int $RoleIDStudents, int $RoleIDStaff): void {
		global $ilDB;
	
		$ilDB->manipulate("DELETE FROM cron_umruser_config");
		$ilDB->manipulate("INSERT INTO cron_umruser_config (role_id_students,role_id_staff) VALUES (" . $ilDB->quote($RoleIDStudents, "integer"). "," . $ilDB->quote($RoleIDStaff, "integer"). ")");
	}

	private function importParsedXML() {
		include_once('./Services/User/classes/class.ilUserImportParser.php');
		$importParser = new ilUserImportParser();
		$importParser->setXMLContent($this->writer->xmlDumpMem(false));
		$importParser->setUserMappingMode(IL_USER_MAPPING_ID);
		$importParser->setFolderId(USER_FOLDER_ID);
		$importParser->startParsing();
	}

	private function getRoleIDStudents(): ?int {
		global $ilDB;
	
		$set = $ilDB->query("SELECT * FROM cron_umruser_config");
		if ($rec = $ilDB->fetchAssoc($set)) {
			return $rec["role_id_students"];
		}
		return null;
	}

	private function getRoleIDStaff(): ?int {
		global $ilDB;
	
		$set = $ilDB->query("SELECT * FROM cron_umruser_config");
		if ($rec = $ilDB->fetchAssoc($set)) {
			return $rec["role_id_staff"];
		}
		return NULL;
	}

}