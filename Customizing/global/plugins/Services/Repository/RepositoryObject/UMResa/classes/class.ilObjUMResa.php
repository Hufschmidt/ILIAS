<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/
require_once('Services/Link/classes/class.ilLink.php');
require_once('Services/Mail/classes/class.ilMail.php');
require_once('Services/Tree/classes/class.ilPathGUI.php');
require_once('Modules/Course/classes/class.ilObjCourse.php');
require_once('Services/Object/classes/class.ilObjectActivation.php');
require_once('Modules/LearningModule/classes/class.ilObjLearningModule.php');


/**
 * Class: ilObjUMResa
 *  TODO: Document
 */
class ilObjUMResa extends ilObjectPlugin {
  // Database table names for storing additional object data
  const OBJ_TABLE   = 'rep_robj_xesa_data';
  const ADMIN_TABLE = 'rep_robj_xesa_admins';
  // List of object properties
  protected $teacherPhone;
  protected $teacherId;
  protected $creatorId;
  protected $container;
  protected $crsTitle;
  protected $crsType;
  protected $esa;
  protected $esaNumber;
  protected $esaFormat;
  protected $notes;
  protected $semeserId;
  protected $admins;
	// Store reference to logic code
  protected $formPlugin;
	protected $esaForm;


  /**
   * Function: DBUpdate($dbVer) / DBUninstall()
   *  Called by dbupdate.php with with iterting version number,
   *  while DBUninstall is called when the plugin should be uninstalled.
   *
   * @param $dbVer <Number> Iterative DB version pre-update
   */
  public static function DBUpdate($dbVer) {
    global $ilDB;

    // Create basic object settings table
    if (!$ilDB->tableExists(self::OBJ_TABLE)) {
      $fields = array(
      	'obj_id'       => array('type' => 'integer', 'length' => 4,   'notnull' => true),
      	'phone'        => array('type' => 'text',    'length' => 40,  'notnull' => false),
      	'teacher_id'   => array('type' => 'integer', 'length' => 4,	 'notnull' => false),
        'creator_id'   => array('type' => 'integer', 'length' => 4,	 'notnull' => false),
      	'container'    => array('type' => 'integer', 'length' => 4,   'notnull' => true),
      	'title'        => array('type' => 'text',    'length' => 255, 'notnull' => true),
      	'type'         => array('type' => 'text',    'length' => 2,   'notnull' => true),
      	'esa'          => array('type' => 'integer', 'length' => 1,   'notnull' => true),
      	'esa_nr'       => array('type' => 'text',    'length' => 20,  'notnull' => false),
      	'esa_format'   => array('type' => 'text',    'length' => 4,   'notnull' => false),
        'esa_semester' => array('type' => 'integer', 'length' => 4,   'notnull' => true),
      	'notes'        => array('type' => 'blob',                     'notnull' => false),
      );
    	$ilDB->createTable(self::OBJ_TABLE, $fields);
    	$ilDB->addPrimaryKey(self::OBJ_TABLE, array('obj_id'));
    }

    // create table for additional admins
    if (!$ilDB->tableExists(self::ADMIN_TABLE)) {
      $fields = array(
      	'obj_id'   => array('type' => 'integer', 'length' => 4, 'notnull' => true),
      	'admin_id' => array('type' => 'integer', 'length' => 4, 'notnull' => true),
      );
    	$ilDB->createTable(self::ADMIN_TABLE, $fields);
    	$ilDB->addPrimaryKey(self::ADMIN_TABLE, array('obj_id', 'admin_id'));
    }
  }
  public static function DBUninstall() {
    global $ilDB;

    // Drop all tables when uninstalling
    $ilDB->dropTable(self::OBJ_TABLE, false);
    $ilDB->dropTable(self::ADMIN_TABLE, false);
  }


  /**
   * TODO: Document
   */
  public function __construct($refId = 0) {
		// Call parent constructor
    parent::__construct($refId);

    // Include ilMailFix
    $phpMailer = stream_resolve_include_path('Services/Mail/phpmailer/class.phpmailer.php');
    if ($phpMailer)
      require_once($phpMailer);

    // Load the UMResaForm plugin
    $formPlugin = $this->plugin->getUMResaFormPlugin();
    $this->esaForm = new ilUMResaForm($formPlugin);

    // Init internals (also defaults)
    $this->teacherPhone = '';
    $this->teacherId    = 0;
    $this->creatorId    = 0;
    $this->container    = 0;
    $this->crsTitle     = '';
    $this->crsType      = 0;
    $this->esa          = false;
    $this->esaNumber    = '';
    $this->esaFormat    = 0;
    $this->notes        = '';
    $this->semeserId    = 0;
    $this->admins       = array();
  }


  /**
   * TODO: Document
   */
  public final function initType(): void {
    $this->setType('xesa');
  }


  /**
   * TODO: Document
   */
  public function doCreate(bool $clone_mode = false): void {
    global $ilDB;

    // Store new object data
    $ilDB->insert(
      self::OBJ_TABLE,
      array(
        'obj_id'       => array('integer', $this->getId()),
        'phone'        => array('text',    $this->getPhone()),
        'teacher_id'   => array('integer', $this->getTeacherId()),
        'creator_id'   => array('integer', $this->getCreatorId()),
        'container'    => array('integer', $this->getParentId()),
        'title'        => array('text',    $this->getCourseTitle()),
        'type'         => array('text',    $this->getCourseType()),
        'esa'          => array('integer', $this->wantsESA() ? 1 : 0),
        'esa_nr'       => array('text',    $this->getESANumber()),
        'esa_format'   => array('text',    $this->getESAFormat()),
        'esa_semester' => array('integer', $this->getESASemesterId()),
        'notes'        => array('clob',    $this->getNotes())
      )
    );

    // Store object admins
    foreach ($this->getExtraAdmins() as $admin)
      $ilDB->insert(
        self::ADMIN_TABLE,
        array(
          'obj_id'   => array('integer', $this->getId()),
          'admin_id' => array('integer', $admin)
        )
      );
  }


  /**
   * TODO: Document
   */
  public function doRead(): void {
    global $ilDB;

    // Fetch object values
    $sqlObj     = sprintf('SELECT * FROM %s WHERE obj_id = %s LIMIT 1',
      self::OBJ_TABLE,
      $ilDB->quote($this->getId(), 'integer')
    );
    $resultObj  = $ilDB->query($sqlObj);
    if ($row = $ilDB->fetchAssoc($resultObj)) {
      $this->setPhone($row['phone']);
      $this->setTeacherId($row['teacher_id']);
      $this->setCreatorId($row['creator_id']);
      $this->setParentId($row['container']);
      $this->setCourseTitle($row['title']);
      $this->setCourseType($row['type']);
      $this->wantsESA($row['esa']);
      $this->setESANumber($row['esa_nr']);
      $this->setESAFormat($row['esa_format']);
      $this->setESASemesterId($row['esa_semester']);
      $this->setNotes($row['notes']);
    }

    // Fetch object admins
    $sqlAdm    = sprintf('SELECT * FROM %s WHERE obj_id = %s',
      self::ADMIN_TABLE,
      $ilDB->quote($this->getId(), 'integer')
    );
    $resultAdm = $ilDB->query($sqlAdm);
    $admins    = array();
    while ($row = $ilDB->fetchAssoc($resultAdm))
      $admins[] = $row['admin_id'];
    $this->setExtraAdmins($admins);
  }


  /**
   * TODO: Document
   */
  public function doUpdate(): void {
    global $ilDB;

    // Delete and recreate
    $this->doDelete();
    $this->doCreate();
  }


  /**
   * TODO: Document
   */
  public function doDelete(): void {
    global $ilDB;

    // Delete object entry
    $sqlObj = sprintf('DELETE FROM %s WHERE obj_id = %s',
      self::OBJ_TABLE,
      $ilDB->quote($this->getId(), 'integer')
    );
    $ilDB->manipulate($sqlObj);

    // Delete object admins
    $sqlAdm = sprintf('DELETE FROM %s WHERE obj_id = %s',
      self::ADMIN_TABLE,
      $ilDB->quote($this->getId(), 'integer')
    );
    $ilDB->manipulate($sqlAdm);
  }


  /**
   * TODO: Document
   */
  public function doClone($targetId, $copyId, $newObj) {
    // Update properties
    $newObj->setPhone($this->getPhone());
    $newObj->setTeacherId($this->getTeacherId());
    $newObj->setCreatorId($this->getCreatorId());
    $newObj->setParentId($this->getParentId());
    $newObj->setCourseTitle($this->getCourseTitle());
    $newObj->setCourseType($this->getCourseType());
    $newObj->setESASemesterId($this->getESASemesterId());
    $newObj->wantsESA($this->wantsESA());
    $newObj->setESANumber($this->getESANumber());
    $newObj->setESAFormat($this->getESAFormat());
    $newObj->setNotes($this->getNotes());
    $newObj->setExtraAdmins($this->getExtraAdmins());

    // Update DB
    $newObj->update();
  }


  /**
   * Function: canCreateCourse($refId)
   *  Checks if the user is allowed to create new course-objects inside the object given by $refId.
   *
   * @param $refId <Number> Ref-id ob object to check creation right for new course object on
   *
   * @return <Boolean> True if user is allowed to create course inside object given by refId, false otherwise
   */
  public function canCreateCourse() {
    global $rbacsystem;

    // Check create(crs) access rights on given refid
    if ($this->getParentId())
      return $rbacsystem->checkAccess('create', $this->getParentId(), 'crs');

    // No object selected
    return false;
  }


  /**
   * TODO: Document
   */
  protected function updateTitle() {
    // Fetch paramaters
    $names   = ilObjUser::_lookupName($this->getTeacherId());
    $crsType = $this->getCourseType();
    $title   = $this->getCourseTitle();

    // Update the title
    $this->setTitle("{$names['lastname']}: {$crsType} {$title}");
  }


  /**
   * TODO: Document
   */
  public function setPhone($phone) {
    $this->teacherPhone = $phone;
  }
  public function getPhone() {
    return $this->teacherPhone;
  }


  /**
   * TODO: Document
   */
  public function setTeacherId($id, $titleUpdate = true) {
    // Convert login to id
    if ($id && !is_numeric($id))
      $id = ilObjUser::_lookupId($id);

    // Store
    if ($id)
      $this->teacherId = intval($id);

    // Update title
    if ($titleUpdate)
      $this->updateTitle();
  }
  public function getTeacherId() {
    return $this->teacherId;
  }


  /**
   * TODO: Document
   */
  public function setCreatorId($id) {
    // Convert login to id
    if ($id && !is_numeric($id))
      $id = ilObjUser::_lookupId($id);

    // Store
    if ($id)
      $this->creatorId = intval($id);
  }
  public function getCreatorId() {
    return $this->creatorId;
  }


  /**
   * TODO: Document
   */
  public function setParentId($container) {
    $this->container = intval($container);
  }
  public function getParentId() {
    return $this->container;
  }


  /**
   * TODO: Document
   */
  public function setCourseTitle($title, $titleUpdate = true){
    // Store
    $this->crsTitle = $title;

    // Update title
    if ($titleUpdate)
      $this->updateTitle();
  }
  public function getCourseTitle() {
    return $this->crsTitle;
  }


  /**
   * TODO: Document
   */
  public function setCourseType($type, $titleUpdate = true) {
    // Store
    $this->crsType = $type;

    // Update title
    if ($titleUpdate)
      $this->updateTitle();
  }
  public function getCourseType($asName = false) {
    // Return as text instead of id
    if ($asName) {
      $types = $this->esaForm->getCourseTypes();
      return $types[$this->crsType];
    }
    else
      return $this->crsType;
  }


  /**
   * TODO: Document
   */
  public function wantsESA($esa = null) {
    // Set value
    if (!is_null($esa))
      $this->esa = boolval($esa);
    // Get value
    else
      return $this->esa;
  }


  /**
   * TODO: Document
   */
  public function setESANumber($esaNumber) {
    $this->esaNumber = $esaNumber;
  }
  public function getESANumber() {
    return $this->esaNumber;
  }


  /**
   * TODO: Document
   */
  public function setESAFormat($esaFormat) {
    $this->esaFormat = $esaFormat;
  }
  public function getESAFormat($asName = false) {
    // Return as text instead of id
    if ($asName) {
      $types = $this->esaForm->getESATypes();
      return $types[$this->esaFormat];
    }
    else
      return $this->esaFormat;
  }


  /**
   * TODO: Document
   */
  public function setESASemesterId($semeserId) {
    $this->semeserId = intval($semeserId);
  }
  public function getESASemesterId() {
    return $this->semeserId;
  }
  public function getESASemesterName() {
    return $this->esaForm->getSemesterName($this->semeserId);
  }
  public function getESASemesterStart() {
    return $this->esaForm->getSemesterStart($this->semeserId);
  }
  public function getESASemesterEnd() {
    return $this->esaForm->getSemesterEnd($this->semeserId);
  }


  /**
   * TODO: Document
   */
  public function setNotes($notes) {
    $this->notes = $notes;
  }
  public function getNotes() {
    return $this->notes;
  }


  /**
   * TODO: Document
   */
  public function setExtraAdmins($admins) {
    // Convert and filter
    foreach ($admins as $key => $admin) {
      // Convert login to id
      if (!is_numeric($admin))
        $admin = intval(ilObjUser::_lookupId($admin));

      if ($admin && $admin !== 0)
        $admins[$key] = $admin;
      else
        unset($admins[$key]);
    }

    // Store (converted) list of admins
    $this->admins = $admins;
  }
  public function getExtraAdmins($asLogins = false) {
    // Convert ids to logins
    if ($asLogins) {
      $admins = array();
      foreach ($this->admins as $id => $admin) {
        $login       = ilObjUser::_lookupLogin($admin);
        if ($login)
          $admins[$id] = $login;
      }

      return $admins;
    }

    // Return ids
    return $this->admins;
  }


  /**
   * TODO: Document
   */
  public function isExtraAdmin($admin) {
    // Convert login to id
    if ($admin && !is_numeric($admin) && strlen($admin) > 0)
      $admin = ilObjUser::_lookupId($admin);

    // Check admin
    if ($admin)
      return in_array($admin, intval($this->admins));
    return false;
  }
  public function addExtraAdmin($admin) {
    // Convert login to id
    if ($admin && !is_numeric($admin) && strlen($admin) > 0)
      $admin = ilObjUser::_lookupId($admin);

    // Add admin
    $admin = intval($admin);
    if ($admin && $admin !== 0)
      array_push($this->admins, $admin);
  }
  public function removeExtraAdmin($admin) {
    // Convert login to id
    if ($admin && !is_numeric($admin) && strlen($admin) > 0)
      $admin = ilObjUser::_lookupId($admin);

    // Delete admin
    if ($admin) {
      $index = array_search(intval($admin), $this->admins);
      if ($index)
        array_splice($this->admins, $index, 1);
    }
  }


  /**
   * TODO: Document
   */
  public function publishCourse() {
    // Convert esa-obj to course
    $crsObj = $this->convertToCourse();

    // Send creation-mail and information to UB
    $this->sendUBMail($crsObj);
    $this->sendCreatedMail($crsObj);

    return $crsObj;
  }


  /**
   * TODO: Document
   */
  public function convertToCourse() {
    // Create new course object
    $crsObj = new ilObjCourse();
    $crsObj->setTitle($this->getTitle());
    $crsObj->setOwner($this->getTeacherId());

    // Create in DB and add to repository (under selected container)
    $crsObj->create();
    $crsObj->createReference();
    $crsObj->putInTree($this->getParentId());
    $crsObj->setPermissions($this->getParentId());

    // Set more options after creation
    if ($this->getESASemesterId()) {
      //$crsObj->setActivationType(ilCourseConstants::SUBSCRIPTION_LIMITED); deleted in ILIAS 5.4
      $crsObj->setActivationStart($this->getESASemesterStart());
      $crsObj->setActivationEnd($this->getESASemesterEnd());
    }

    // Add requested admins
    $mbrObj = $crsObj->getMembersObject();
    $mbrObj->add($this->getTeacherId(), ilParticipants::IL_CRS_ADMIN);
    foreach ($this->getExtraAdmins() as $admin)
      $mbrObj->add($admin, ilParticipants::IL_CRS_ADMIN);

    // Update DB once more
    $crsObj->update();

    // Add Learning-Module?
    if ($this->wantsESA())
      $this->createLM($crsObj);

    // Delete UMResa Object on success
    ilRepUtil::deleteObjects(0, array($this->getRefId()));

    // Return ref-id of new course
    return $crsObj;
  }


  /**
   * TODO: Document
   */
  protected function createLM($crsObj) {
    global $rbacadmin, $rbacreview;

    // Create new local template for UB-Role in crs with given template as base
    $crsRefId  = $crsObj->getRefId();
    $ubRoleId  = $this->esaForm->getUBRole();
    $esaRoleId = $this->esaForm->getRoleTemplate();
    $rbacadmin->copyRoleTemplatePermissions($esaRoleId, ROLE_FOLDER_ID, $crsRefId, $ubRoleId);

    // Grant permissions from new local template to crs
    $operations = $rbacreview->getOperationsOfRole($ubRoleId, 'crs', $crsRefId);
    $rbacadmin->grantPermission($ubRoleId, $operations, $crsRefId);

    // Create new Learning-Module to contain ESA data
    $lmObj = new ilObjLearningModule();
    $lmObj->setTitle("Semesterapparat (Kurs-Nr. {$this->getESANumber()})");
    $lmObj->setOwner($this->getTeacherId());

    // Create object in DB and add to repository tree
    $lmObj->create();
    $lmObj->createReference();
    $lmObj->putInTree($crsObj->getRefId());
    $lmObj->setPermissions($crsObj->getRefId());

    // Basic LM setup (add page)
    $lmObj->createLMTree();
    $lmObj->addFirstChapterAndPage();
    $lmObj->setPageHeader('pg_title');
    $lmObj->setTOCMode('pages');

    // Ubdate DB once more
    $lmObj->update();

    // Grant permissions from new local template to lm
    $operations = $rbacreview->getOperationsOfRole($ubRoleId, 'lm', $crsRefId);
    $rbacadmin->grantPermission($ubRoleId, $operations, $lmObj->getRefId());

    // Create entry in "activation management table" for the learning module.
    ilObjectActivation::getItem($lmObj->getRefId());

    // Setup activation-time for LM
    $activation = new ilObjectActivation();
    $activation->setTimingType(ilObjectActivation::TIMINGS_ACTIVATION);
    $activation->setTimingStart($this->getESASemesterStart());
    $activation->setTimingEnd($this->getESASemesterEnd());
    $activation->toggleVisible(false);

    // Used only if "Time Target" is enabled
    $activation->setSuggestionStart(0);
    $activation->setSuggestionEnd(0);
    // $activation->setEarliestStart(0); deletet in ILIAS 8
    // $activation->setLatestEnd(0); deletet in ILIAS 5.4
    $activation->toggleChangeable(false);

    // Update activation-time of LM
    $activation->update($lmObj->getRefId());

    // Return generated LM object
    return $lmObj;
  }


  /**
   * TODO: Document
   */
  protected function getNoReply() {
    global $ilSetting;

    $email = trim($ilSetting->get('mail_external_sender_noreply'));

    if (strlen($email)) {
      if (strpos($email, '@') === false)
        $email = 'noreply@' . $email;

      if(!ilUtil::is_email($email))
        $email = 'noreply@' . $_SERVER['SERVER_NAME'];

      return $email;
    }

    return 'noreply@' . $_SERVER['SERVER_NAME'];
  }


  /**
   * TODO: Document
   */
  protected function sendMail($subject, $message, $from, $to, $cc = null, $headers = null) {
    global $ilSetting;

    // Quick exit if mailing is disabled globally
    if ($ilSetting->get('prevent_smtp_globally'))
      return false;

    // Create new PHPMailer instance
    $mail = new PHPMailer();

    // Set return path if one is configured
		if ($ilSetting->get('mail_system_return_path', ''))
			$mail->Sender = $ilSetting->get('mail_system_return_path', '');

    // Set from and reply-to adresses
    if (is_array($from)) {
		  $mail->addReplyTo($from[0], $from[1]);
		  $mail->setFrom($from[0], $from[1]);
    }
    else {
		  $mail->addReplyTo($from, '');
		  $mail->setFrom($from, '');
    }

    // Add to addresses
    if ($to) {
      $to = (is_array($to)) ? $to : array($to);
      foreach ($to as $email)
        $mail->AddAddress($email, '');
    }

    // Add cc addresses
    if ($cc) {
      $cc = (is_array($cc)) ? $cc : array($cc);
      foreach ($cc as $email)
        $mail->AddCC($email, '');
    }

    // Add headers addresses
    if ($headers) {
      $headers = (is_array($headers)) ? $headers : array($headers);
      foreach ($headers as $header)
        if (is_array($header))
          $mail->addCustomHeader($header[0], $header[1]);
        else
          $mail->addCustomHeader($header);
    }

    // Add subject
		$mail->CharSet = 'utf-8';
		$mail->Subject = $subject;

    // Add message
    $mail->IsHTML(false);
    $mail->Body = $message;

    // Send
		return $mail->Send();
  }


  /**
   * TODO: Document
   */
  public function sendTicketMail($crsObj = null) {
    global $ilUser;

    // Fetch teacher and creator user-obj for subject generation
    $teacher = new ilObjUser($this->getTeacherId());
    $creator = new ilObjUser($this->getCreatorId());

    // Mail: Subject
    if ($teacher->getId() === $creator->getId())
      $subject = "Kursanmeldung von {$teacher->getFullName()}";
    else
      $subject = "Kursanmeldung von {$creator->getFullName()} für {$teacher->getFullName()}";

    // Mail: BCC-Address
    $to     = array($this->esaForm->getHRZMail());

    // Mail: Message
    $message = $this->getTicketMail($crsObj)->get();

    // Mail: Headers
    $headers = ($crsObj) ? 'X-RT-Template: Anmeldung-Geschlossen' : 'X-RT-Template: Anmeldung-Offen';

    // Send new mail with given constructed data
    $this->sendMail($subject, $message, $creator->getEmail(), $to, null, $headers);
  }


  /**
   * TODO: Document
   */
  public function sendUBMail($crsObj) {
    global $ilUser;

    // Only send when ESA is requested
    if ($this->wantsESA()) {
      // Fetch teacher and creator user-obj for subject generation
      $teacher = new ilObjUser($this->getTeacherId());
      $creator = new ilObjUser($this->getCreatorId());

      // Mail: Subject
      if ($teacher->getId() === $creator->getId())
        $subject = "Kursanmeldung von {$teacher->getFullName()}";
      else
        $subject = "Kursanmeldung von {$creator->getFullName()} für {$teacher->getFullName()}";

      // Mail: BCC-Address
      $to = array($this->esaForm->getUBMail());

      // Mail: Message
      $message = $this->getTicketMail($crsObj)->get();

      // Send new mail with given constructed data
      $this->sendMail($subject, $message, $this->esaForm->getHRZMail(), $to);
    }
  }


  /**
   * TODO: Document
   */
  public function sendCreatedMail($crsObj) {
    // Mail: Subject
    $subject = "Abgeschlossene Kursanmeldung: \"{$this->getCourseTitle()}\"";

    // Mail: To-Address
    $to      = array();
    $teacher = new ilObjUser($this->getTeacherId());
    if ($teacher)
      $to[]  = $teacher->getEmail();

    // Mail: CC-Address(es) -> creator
    $cc      = array();
    $creator = new ilObjUser($this->getCreatorId());
    if ($creator && !in_array($creator->getEmail(), $to))
      $cc[] = $creator->getEmail();

    // Mail: CC-Address(es) -> admins
    foreach ($this->getExtraAdmins() as $admin) {
      $adminUser = new ilObjUser($admin);
      if ($adminUser && !in_array($adminUser->getEmail(), $cc))
        $cc[]    = $adminUser->getEmail();
    }

    // Mail: Message
    $message = $this->getCreatedMail($crsObj)->get();

    // Send new mail with given constructed data
    $this->sendMail($subject, $message, $this->esaForm->getHRZMail(), $to, $cc);
  }


  /**
   * TODO: Document
   */
  public function sendRequestMail() {
    // Mail: Subject
    $subject = "Kursantrag in Bearbeitung: \"{$this->getCourseTitle()}\"";

    // Mail: To-Address
    $to      = array();
    $teacher = new ilObjUser($this->getTeacherId());
    if ($teacher)
      $to[]  = $teacher->getEmail();

    // Mail: CC-Address(es) -> creator
    $cc      = array();
    $creator = new ilObjUser($this->getCreatorId());
    if ($creator && !in_array($creator->getEmail(), $to))
      $cc[] = $creator->getEmail();

    // Mail: CC-Address(es) -> admins
    foreach ($this->getExtraAdmins() as $admin) {
      $adminUser = new ilObjUser($admin);
      if ($adminUser && !in_array($adminUser->getEmail(), $cc))
        $cc[]    = $adminUser->getEmail();
    }

    // Mail: Message
    $message = $this->getRequestMail()->get();

    // Send new mail with given constructed data
    $this->sendMail($subject, $message, $this->esaForm->getHRZMail(), $to, $cc);
  }


  /**
   * TODO: Document
   */
  protected function getTicketMail($crsObj = null) {
    global $ilUser;

    // Fetch teacher user-obj
    $teacher = new ilObjUser($this->getTeacherId());

    // Load and fill in mail template
    $mailTpl = $this->plugin->getTemplate('tpl.ticket_mail.txt', true, true);
    $mailTpl->setVariable('TEACHERNAME',  $teacher->getFullName());
    $mailTpl->setVariable('TEACHERLOGIN', $teacher->getLogin());
    $mailTpl->setVariable('TEACHERMAIL',  $teacher->getEmail());
    $mailTpl->setVariable('COURSETITLE',  $this->getCourseTitle());
    $mailTpl->setVariable('COURSETYPE',   $this->getCourseType(true));
    $mailTpl->setVariable('USERNAME',     $ilUser->getFullName());
    $mailTpl->setVariable('USERLOGIN',    $ilUser->getLogin());
    $mailTpl->setVariable('USERMAIL' ,    $ilUser->getEmail());
    $mailTpl->setVariable('NOW',          date('Y-m-d H:i:s'));

    // Add notes?
    if ($this->getNotes()) {
      $mailTpl->setCurrentBlock('hasNotes');
      $mailTpl->setVariable('COMMENT',      $this->getNotes());
      $mailTpl->parseCurrentBlock();
    }

    // Add phone
    if ($this->getPhone()) {
      $mailTpl->setCurrentBlock('hasPhone');
      $mailTpl->setVariable('TEACHERPHONE', $this->getPhone());
      $mailTpl->parseCurrentBlock();
    }

    // Add ESA block to template
    if ($this->wantsESA()) {
      $mailTpl->setCurrentBlock('wantsESA');
      $mailTpl->setVariable('ESANUMBER',  $this->getESANumber());
      $mailTpl->setVariable('ESAFORMAT',  $this->getESAFormat(true));
      $mailTpl->setVariable('ESA_START',  date("Y-m-d\TH:i:s\Z", $this->getESASemesterStart()));
      $mailTpl->setVariable('ESA_END',    date("Y-m-d\TH:i:s\Z", $this->getESASemesterEnd()));
      $mailTpl->setVariable('SEMESTER',   $this->getESASemesterName());
      $mailTpl->parseCurrentBlock();
    }

    // Add course block to template
    if ($crsObj) {
      $mailTpl->setCurrentBlock('canCreateCourse');
      $mailTpl->setVariable('COURSELINK', ilLink::_getLink($crsObj->getRefId()));
      $mailTpl->parseCurrentBlock();
    }
    // Add course-container block to template instead
    else {
      $mailTpl->setCurrentBlock('cantCreateCourse');
      $mailTpl->setVariable('CONTAINER', $this->getPath($this->getParentId()));
      $mailTpl->parseCurrentBlock();
    }

    // Return template
    return $mailTpl;
  }


  /**
   * TODO: Document
   */
  protected function getCreatedMail($crsObj) {
    // Fetch teacher user-obj
    $teacher = new ilObjUser($this->getTeacherId());
    $creator = new ilObjUser($this->getCreatorId());

    // Load and fill in mail template
    $mailTpl = $this->plugin->getTemplate('tpl.created_mail.txt', true, true);
    $mailTpl->setVariable('SALUTATION', $teacher->getGender() == 'm' ? 'Sehr geehrter Herr' : 'Sehr geehrte Frau');
    $mailTpl->setVariable('NAME',       $teacher->getFullName());
    $mailTpl->setVariable('TITLE',      $this->getCourseTitle());
    $mailTpl->setVariable('PERMALINK',  ilLink::_getLink($crsObj->getRefId()));
    $mailTpl->setVariable('PATH',       $this->getPath($this->getParentId()));
    if (intval($teacher->getId()) != intval($creator->getId())  && isset($mailTpl->blocklist['CreatedBy'])) {
      $mailTpl->setCurrentBlock('CreatedBy');
      $mailTpl->setVariable('CREATOR', $creator->getFullName());
      $mailTpl->parseCurrentBlock();
    }
    if ($this->wantsESA() && isset($mailTpl->blocklist['WantsESA']))
      $mailTpl->touchBlock('WantsESA');

    // Return template
    return $mailTpl;
  }


  /**
   * TODO: Document
   */
  protected function getRequestMail() {
    // Fetch teacher user-obj
    $teacher = new ilObjUser($this->getTeacherId());
    $creator = new ilObjUser($this->getCreatorId());

    // Load and fill in mail template
    $mailTpl = $this->plugin->getTemplate('tpl.request_mail.txt', true, true);
    $mailTpl->setVariable('SALUTATION', $teacher->getGender() == 'm' ? 'Sehr geehrter Herr' : 'Sehr geehrte Frau');
    $mailTpl->setVariable('NAME',       $teacher->getFullName());
    $mailTpl->setVariable('TITLE',      $this->getCourseTitle());
    $mailTpl->setVariable('PATH',       $this->getPath($this->getParentId()));
    if (intval($teacher->getId()) != intval($creator->getId()) && isset($mailTpl->blocklist['CreatedBy'])) {
      $mailTpl->setCurrentBlock('CreatedBy');
      $mailTpl->setVariable('CREATOR', $creator->getFullName());
      $mailTpl->parseCurrentBlock();
    }
    if ($this->wantsESA() && isset($mailTpl->blocklist['WantsESA']))
      $mailTpl->touchBlock('WantsESA');

    // Return template
    return $mailTpl;
  }


  /**
   * TODO: Document
   */
  public static function getPath($refId, $textOnly = true) {
    if ($refId) {
      // Configure path-generation helper
      $path = new ilPathGUI();
      $path->enableTextOnly($textOnly);
      $path->enableHideLeaf(false);

      // Build path from root to given ref-id
      return trim($path->getPath(ROOT_FOLDER_ID, $refId));
    }

    return '';
  }
}
