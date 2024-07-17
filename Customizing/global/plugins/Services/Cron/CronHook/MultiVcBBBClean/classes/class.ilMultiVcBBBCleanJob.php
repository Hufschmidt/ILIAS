<?php

include_once "Services/Cron/classes/class.ilCronJob.php";

class ilMultiVcBBBCleanJob extends ilCronJob
{
	protected $plugin;

	public function __construct($plugin)
	{
		$this->plugin = $plugin;
	}

	public function getId(): string
	{
		return "multivcbbb_clean";
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

		$days = "14"; // hardcoded days after recordings will be deleted
		// Auslesen der BBB-Aufzeichnungen aus der ILIAS-DB. !Möglicherweise sind nicht alle Aufzeichnungen vollständig erfasst!
		$bbbRecs = $this->getBBBRecs($days);
 
		$deletedCount = 0;
		if (!empty($bbbRecs)) {
			foreach ($bbbRecs as $rec) {
				$refId = (int)$rec['ref_id'];
				$recId = $rec['rec_id'];
 
				$objId = $this->getObjIdFromRefId($refId);
				$object = ilObjectFactory::getInstanceByObjId($objId);
				$this->settings = ilMultiVcConfig::getInstance($object->getConnId());

				$this->bbb = new \BigBlueButton\BigBlueButton($this->settings->getSvrPrivateUrl(), $this->settings->getSvrSalt());
				$delRecParam = new \BigBlueButton\Parameters\DeleteRecordingsParameters($recId);
				// Löscht die Aufzeichung faktisch auf den BBB-Servern
				$response = $this->bbb->deleteRecordings($delRecParam);
				if ($response->getReturnCode() == 'SUCCESS') {
					$deletedCount++;
					// Löscht den ILIAS-DB-Eintrag über die BBB-Aufzeichnung
					$object->deleteBBBRecById($refId, $recId);
				}
			}
		}

		$message = "BBB Recordings processed: " . count($bbbRecs) . ", Successfully deleted: " . $deletedCount;
		$result = new ilCronJobResult();
		$result->setMessage($message);
		return $result;
	}

	private function getBBBRecs(int $days): array {
		global $ilDB;

		// Errechne das Datum, das $days Tage vor heute liegt
		$dateThreshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

		$query = "SELECT * FROM rep_robj_xmvc_recs_bbb WHERE create_date <= '{$dateThreshold}'";
		$set = $ilDB->query($query);
		$results = array();

		while ($rec = $ilDB->fetchAssoc($set)) { 
			$results[] = $rec;
		}

		return $results;
	}

	function getObjIdFromRefId($refId) {
		global $ilDB;

		// SQL-Abfrage, um obj_id basierend auf der ref_id abzurufen
		$query = "SELECT obj_id FROM object_reference WHERE ref_id = " . $ilDB->quote($refId, 'integer');
		$result = $ilDB->query($query);

		if ($result->numRows() > 0) {
			$row = $result->fetchAssoc();
			return (int)$row['obj_id'];
		} else {
			return false;
		}
	}

}