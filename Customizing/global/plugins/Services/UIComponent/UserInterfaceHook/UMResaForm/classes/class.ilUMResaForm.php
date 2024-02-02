<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA-Forms) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/


/**
 * Class: ilUMResaForm
 *  Custom class for handling ESA specific program logic
 *  required by ilUMResaFormGUI.
 */
class ilUMResaForm {
  // Default phone number
  const DEFAULT_PHONE  = '28-';
  // Possible course-type (translated into 'OBJ::CRS_TYPE::<type>')
  const CRS_TYPES      = array('SE', 'US', 'PS', 'MS', 'HS', 'FS', 'UE', 'KO', 'VL', 'KU', 'AK', 'SO');
  // Possible esa-type (translated into 'OBJ::ESA_TYPE::<type>')
  const ESA_TYPES      = array('PDF', 'A4', 'UB', 'MISC');
  // Stores description for each language
  const DESC_TABLE     = 'ui_uihk_xesa_desc';
  // Stores each ESA admin as own row
  const ADMIN_TABLE    = 'ui_uihk_xesa_admins';
  // Stores single row config
  const CONFIG_TABLE   = 'ui_uihk_xesa_config';
  // Stores avaiable semesters
  const SEMESTER_TABLE = 'ui_uihk_xesa_sem';


  /**
   * Function: DBUpdate($dbVer) / DBUninstall()
   *  Called by dbupdate.php with with iterting version number,
   *  while DBUninstall is called when the plugin should be uninstalled.
   *
   * @param $dbVer <Number> Iterative DB version pre-update
   */
  public static function DBUpdate($dbVer) {
    global $ilDB;

    // Create single-value config table
    if (!$ilDB->tableExists(self::CONFIG_TABLE)) {
      $fields = array(
      	'hrz_mail'  => array('type' => 'text',    'length' => 48,  'notnull' => true),
      	'ub_mail'   => array('type' => 'text',    'length' => 48,  'notnull' => true),
      	'ub_role'   => array('type' => 'integer', 'length' => 4,   'notnull' => true),
      	'template'  => array('type' => 'integer', 'length' => 4,   'notnull' => true),
      	'container' => array('type' => 'integer', 'length' => 4,   'notnull' => true),
      	'ub_link'   => array('type' => 'text',    'length' => 128, 'notnull' => true)
      );
    	$ilDB->createTable(self::CONFIG_TABLE, $fields);

      // Add single row for this table, stores all config values
      $ilDB->insert(self::CONFIG_TABLE, array(
        'hrz_mail'  => array('text',    'ilias@hrz.uni-marburg.de'),
        'ub_mail'   => array('text',    'ilias@ub.uni-marburg.de'),
        'ub_role'   => array('integer', 0),
        'template'  => array('integer', 0),
        'container' => array('integer', 0),
        'ub_link'   => array('text',    'http://www.uni-marburg.de/bis/digitale_bibliothek/esa')
      ));
    }

    // Create esa admins table
    if (!$ilDB->tableExists(self::ADMIN_TABLE)) {
      $fields = array(
        'admin_id' => array('type' => 'integer', 'length' => 4, 'notnull' => true)
      );
    	$ilDB->createTable(self::ADMIN_TABLE, $fields);
    	$ilDB->addPrimaryKey(self::ADMIN_TABLE, array('admin_id'));
    }

    // Create translated form description table
    if (!$ilDB->tableExists(self::DESC_TABLE)) {
      $fields = array(
      	'lang_key' => array('type' => 'text', 'length' => 4, 'notnull' => true),
      	'value'    => array('type' => 'blob',                'notnull' => true)
      );
    	$ilDB->createTable(self::DESC_TABLE, $fields);
    	$ilDB->addPrimaryKey(self::DESC_TABLE, array('lang_key'));
    }

    // Create translated form description table
    if (!$ilDB->tableExists(self::SEMESTER_TABLE)) {
      $fields = array(
      	'id'        => array('type' => 'integer', 'length' => 4,  'notnull' => true),
      	'name'      => array('type' => 'text',    'length' => 40, 'notnull' => true),
      	'unixstart' => array('type' => 'integer', 'length' => 4,  'notnull' => true),
        'unixend'   => array('type' => 'integer', 'length' => 4,  'notnull' => true),
      );
    	$ilDB->createTable(self::SEMESTER_TABLE, $fields);
    	$ilDB->addPrimaryKey(self::SEMESTER_TABLE, array('id'));
      $ilDB->query(sprintf('ALTER TABLE %s MODIFY id int(4) AUTO_INCREMENT', self::SEMESTER_TABLE));
    }
  }
  public static function DBUninstall() {
    global $ilDB;

    // Drop all tables when uninstalling
    $ilDB->dropTable(self::DESC_TABLE, false);
    $ilDB->dropTable(self::ADMIN_TABLE, false);
    $ilDB->dropTable(self::CONFIG_TABLE, false);
    $ilDB->dropTable(self::SEMESTER_TABLE, false);
  }


  /**
   * Function: __construct()
   *  Fetches the ILIAS plugin object for this plugin on creation
   *  and includes classes required for form generation.
   */
  public function __construct($plugin = null) {
        global $DIC;
		// Fetch plugin object for access to translations and such
        $component_factory = $DIC['component.factory'];
        $this->plugin = $component_factory->getPlugin('xesaform');
  }


  /**
   * Function: hasDescription($langKey)
   *  Checks wether there is a translation available for the language given by $langKey.
   *  @see ilMDLanguageItem
   *
   * @param $langKey <String> Language key to look-up description for
   */
  public function hasDescription($langKey) {
    global $ilDB;

    $query  = sprintf(
      'SELECT 1 FROM %s WHERE lang_key = %s LIMIT 1',
      self::DESC_TABLE,
      $ilDB->quote($langKey, 'text')
    );
    $result = $ilDB->query($query);
    return ($result->numRows() > 0);
  }


  /**
   * Function: getDescription($langKey) / setDescription($langKey, $desc)
   *  Getter- / Setter method for language specific esa-form description.
   *
   * @param $langKey <String> The language key to fetch description for
   * @param $desc <String> New description to store to DB
   *
   * @return <String> Description fetched from DB
   */
  public function getDescription($langKey = null) {
    global $ilDB;

    $query  = ($langKey && strlen($langKey) > 0) ? sprintf(
      'SELECT value FROM %s WHERE lang_key = %s LIMIT 1',
      self::DESC_TABLE,
      $ilDB->quote($langKey, 'text')
    ) : sprintf(
      'SELECT value FROM %s WHERE lang_key = \'en\' LIMIT 1',
      self::DESC_TABLE
    );
    $result = $ilDB->query($query);
    $row    = $ilDB->fetchAssoc($result);
    return ($row && isset($row['value'])) ? $row['value'] : "";
  }


  public function setDescription($langKey, $desc = null) {
    global $ilDB;

    if ($desc) {
      if ($this->hasDescription($langKey))
        $ilDB->update(self::DESC_TABLE, array(
          'value'    => array('clob', $desc)
        ), array(
          'lang_key' => array('text', $langKey)
        ));
      else
        $ilDB->insert(self::DESC_TABLE, array(
          'lang_key' => array('text', $langKey),
          'value'    => array('clob', $desc)
        ));
    }
    else {
      $query = sprintf(
        'DELETE FROM %s WHERE lang_key = %s',
        self::DESC_TABLE,
        $ilDB->quote($langKey, 'text')
      );
      $ilDB->manipulate($query);
    }
  }


  /**
   * Function: getHRZMail() / setHRZMail($mail)
   *  Getter- / Setter method for hrz ticket-system mail-address.
   *
   * @param $mail <String> New mail adress to be stored
   *
   * @return <String> Mail address loaded from DB
   */
  public function getHRZMail() {
    global $ilDB;

    $query  = sprintf(
      'SELECT hrz_mail FROM %s LIMIT 1',
      self::CONFIG_TABLE
    );
    $result = $ilDB->query($query);
    $row    = $ilDB->fetchAssoc($result);
    return $row['hrz_mail'];
  }
  public function setHRZMail($mail) {
    global $ilDB;

    $ilDB->update(self::CONFIG_TABLE, array(
      'hrz_mail' => array('text', $mail)
    ), array(
        'hrz_mail' => array('text', $this->getHRZMail())
    ));
  }


  /**
   * Function: getUBMail() / setUBMail($mail)
   *  Getter- / Setter method for university-library ticket-system mail-address.
   *
   * @param $mail <String> New mail adress to be stored
   *
   * @return <String> Mail address loaded from DB
   */
  public function getUBMail() {
    global $ilDB;

    $query  = sprintf(
      'SELECT ub_mail FROM %s LIMIT 1',
      self::CONFIG_TABLE
    );
    $result = $ilDB->query($query);
    $row    = $ilDB->fetchAssoc($result);
    return $row['ub_mail'];
  }
  public function setUBMail($mail) {
    global $ilDB;

    $ilDB->update(self::CONFIG_TABLE, array(
      'ub_mail' => array('text', $mail)
    ), array(
        'ub_mail' => array('text', $this->getUBMail())
    ));
  }


  /**
   * Function: getUBRole() / setUBRole($role)
   *  Getter- / Setter method for university library admin role in ILIAS.
   *
   * @param $role <String> New role name to be stored in DB
   * @param $asId <Boolean> Wether to return role-id (true) or role-name (false)
   *
   * @return <String> Current role name loaded from DB
   */
  public function getUBRole($asId = true) {
    global $ilDB;

    $query  = sprintf(
      'SELECT ub_role FROM %s LIMIT 1',
      self::CONFIG_TABLE
    );
    $result = $ilDB->query($query);
    $row    = $ilDB->fetchAssoc($result);

    if ($asId)
      return intval($row['ub_role']);
    return ilObject::_lookupTitle($row['ub_role']);

  }
  public function setUBRole($role) {
    global $ilDB;

    if ($role && !is_numeric($role))
      $roleIds = ilObject::_getIdsForTitle($role, 'role');
      $role = reset($roleIds);

    if ($role)
      $ilDB->update(self::CONFIG_TABLE, array(
        'ub_role' => array('integer', intval($role))
      ), array(
        'ub_role' => array('integer', $this->getUBRole())
      ));
  }


  /**
   * Function: getRoleTemplate()/setRoleTemplate($tpl)
   *  Getter- / Setter method for university library admin role in ILIAS.
   *
   * @param $tpl <String> New role template name to store in DB
   * @param $asId <Boolean> Wether to return role-id (true) or role-name (false)
   *
   * @return <String> Current role template loaded from DB
   */
  public function getRoleTemplate($asId = true) {
    global $ilDB;

    $query  = sprintf(
      'SELECT template FROM %s LIMIT 1',
      self::CONFIG_TABLE
    );
    $result = $ilDB->query($query);
    $row    = $ilDB->fetchAssoc($result);

    if ($asId)
      return intval($row['template']);
    return ilObject::_lookupTitle($row['template']);
  }
  public function setRoleTemplate($roleTpl) {
    global $ilDB;

    if ($roleTpl && !is_numeric($roleTpl))
      $roleTplIds = ilObject::_getIdsForTitle($roleTpl, 'rolt');
      $roleTpl = reset($roleTplIds);

    if ($roleTpl)
      $ilDB->update(self::CONFIG_TABLE, array(
        'template' => array('integer', intval($roleTpl))
      ), array(
        'template' => array('integer', $this->getRoleTemplate())
      ));
  }


  /**
   * Function: getContainerId() / setContainerId($obj)
   *  Getter- / Setter method for container element  where course without sufficient
   *  permissions will be stored.
   *
   * @param $obj <Number> New reference ID of object to use as container
   *
   * @return <Number> Current container object reference if read from DB
   */
  public function getContainerId() {
    global $ilDB;

    $query  = sprintf(
      'SELECT container FROM %s LIMIT 1',
      self::CONFIG_TABLE
    );
    $result = $ilDB->query($query);
    $row    = $ilDB->fetchAssoc($result);
    return intval($row['container']);
  }
  public function setContainerId($obj) {
    global $ilDB;

    $ilDB->update(self::CONFIG_TABLE, array(
      'container' => array('integer', $obj)
    ), array(
      'container' => array('integer', $this->getContainerId())
    ));
  }


  /**
   * Function: getUBInfoLink() / setUBInfoLink($link)
   *  Getter- / Setter method for information website about ESA.
   *
   * @param $link <String> Link to information website that should be stored in DB
   *
   * @return <String> Current information website link read from DB
   */
  public function getUBInfoLink() {
    global $ilDB;

    $query  = sprintf(
      'SELECT ub_link FROM %s LIMIT 1',
      self::CONFIG_TABLE
    );
    $result = $ilDB->query($query);
    $row    = $ilDB->fetchAssoc($result);
    return $row['ub_link'];
  }
  public function setUBInfoLink($link) {
    global $ilDB;

    $ilDB->update(self::CONFIG_TABLE, array(
      'ub_link' => array('text', $link)
    ), array(
      'ub_link' => array('text', $this->getUBInfoLink())
    ));
  }


  /**
   * Function: isESAAdmin($login)
   *  Checks wether the ilias user given by its login-name is an ESA-Admin
   *  who gets new courses placed on their desktop.
   *
   * @param $login <String> ILIAS login of user to check
   *
   * @return <Boolean> True if the user is ESA admin
   */
  public function isESAAdmin($id) {
    global $ilDB;

    if ($id && !is_numeric($id))
      $id = ilObjUser::_lookupId($id);

    $query  = sprintf(
      'SELECT 1 FROM %s WHERE admin_id = %s LIMIT 1',
      self::ADMIN_TABLE,
      $ilDB->quote(intval($id), 'integer')
    );
    $result = $ilDB->query($query);
    return ($result->numRows() > 0);
  }


  /**
   * Function: addESAAdmin($login) / removeESAAdmin($login)
   *  Add or remove the given ILIAS user to the list of ESA admins.
   *
   * @param $login <String> ILIAS login to add to or remove from esa admin list
   */
  public function addESAAdmin($id) {
    global $ilDB;

    if ($id && !is_numeric($id))
      $id = ilObjUser::_lookupId($id);

    $ilDB->insert(self::ADMIN_TABLE, array(
      'admin_id' => array('integer', intval($id))
    ));
  }
  public function removeESAAdmin($id = null) {
    global $ilDB;

    if ($id) {
      if (!is_numeric($id))
        $id = ilObjUser::_lookupId($id);

      if ($id) {
        $query  = sprintf(
          'DELETE IGNORE FROM %s WHERE admin_id = %s',
          self::ADMIN_TABLE,
          $ilDB->quote(intval($id), 'integer')
        );
        $ilDB->manipulate($query);
      }
    }
    else {
      $query = sprintf(
        'DELETE IGNORE FROM %s',
        self::ADMIN_TABLE
      );
      $ilDB->manipulate($query);
    }
  }


  /**
   * Function: setESAAdmins($admins)
   *  Getter- / Setter method for list of esa admins.
   *  Sets or gets the complete list onstead of adding or removing a single user.
   *
   * @param $admins <Array> List of ILIAS logins to set as esa admins
   * @param $asId <Boolean> Wether to return admins as id 8true 9 or logins
   *
   * @return <Array> List of ILIAS logins that currently are esa admins
   */
  public function setESAAdmins($admins) {
    global $ilDB;

    $this->removeESAAdmin();
    foreach ($admins as $admin)
      $this->addESAAdmin($admin);
  }
  public function getESAAdmins($asId = true) {
    global $ilDB;

    $query  = sprintf(
      'SELECT admin_id FROM %s',
      self::ADMIN_TABLE
    );
    $result = $ilDB->query($query);
    $rows   = array();
    while ($row = $ilDB->fetchAssoc($result))
      if ($asId)
        array_push($rows, $row['admin_id']);
      else {
        $login = ilObjUser::_lookupLogin($row['admin_id']);
        if ($login)
          array_push($rows, $login);
      }

    return $rows;
  }


  /**
   * Function: addSemester($name, $begin, $end) / removeSemester($id)
   *  Add or remove a semster from the list of available semesters.
   *
   * @param $name <String> Name of semester to add (mostly for GUI)
   * @param $begin <Number> Unix-timestamp when semester starts
   * @param $end <Number> Unix-timestamp when semester ends
   * @param $id <Number> ID of semster to remove
   */
  public function addSemester($name, $unixStart, $unixEnd) {
    global $ilDB;

    if (!is_numeric($unixStart)) {
      $startDate = new ilDateTime($unixStart, IL_CAL_DATE);
      $unixStart = $startDate->get(IL_CAL_UNIX);
    }
    if (!is_numeric($unixEnd)) {
      $endDate = new ilDateTime($unixEnd, IL_CAL_DATE);
      $unixEnd = $endDate->get(IL_CAL_UNIX);
    }

    $ilDB->insert(self::SEMESTER_TABLE, array(
      'name'      => array('text',    $name),
      'unixstart' => array('integer', $unixStart),
      'unixend'   => array('integer', $unixEnd),
    ));
  }
  public function removeSemester($id = null) {
    global $ilDB;

    if ($id) {
      $query  = sprintf(
        'DELETE IGNORE FROM %s WHERE id = %s',
        self::SEMESTER_TABLE,
        $ilDB->quote(intval($id), 'integer')
      );
      $ilDB->manipulate($query);
    }
    else {
      $query = sprintf(
        'DELETE IGNORE FROM %s',
        self::SEMESTER_TABLE
      );
      $ilDB->manipulate($query);
    }
  }


  /**
   * Function: getSemesterStart($id) / getSemesterEnd($id)
   *  Helper functions to fetch name and dates from semester array.
   *
   * @param $id <Number> Unique id of semester to fetch information for
   *
   * @return <Number> Unix timestamp of semester start/end
   */
  public function getSemesterStart($id) {
    $semester = $this->getSemester($id);
    return $semester['unixstart'];
  }
  public function getSemesterEnd($id) {
    $semester = $this->getSemester($id);
    return $semester['unixend'];
  }


  /**
   * Function: getSemesterName($id)
   *  Helper functions to fetch name and dates from semester array.
   *
   * @param $id <Number> Unique id of semester to fetch information for
   *
   * @return <String> Defined name of given semester
   */
  public function getSemesterName($id) {
    $semester = $this->getSemester($id);
    return $semester['name'];
  }


  /**
   * Function setSemeter($id, $name, $unixStart, $unixEnd) / getSemester($id)
   *  Getter and Setter methods for one possible semester, given by its it.
   *
   * @param $id <Number> Unique identifier of semester
   * @param $name <String> Name of semester (for GUI)
   * @param $unixStart <Number> Start date of semester (unix time)
   * @param $unixEnd <Number> End date of semester (unix time)
   *
   * @return <Array> Information about semester, containing keys name, start (ilDate), end (ilDate), unixstart (unix) and unixend (unix)
   */
  public function setSemeter($id, $name, $unixStart, $unixEnd) {
    global $ilDB;

    if (!is_numeric($unixStart)) {
      $startDate = new ilDateTime($unixStart, IL_CAL_DATE);
      $unixStart = $startDate->get(IL_CAL_UNIX);
    }
    if (!is_numeric($unixEnd)) {
      $endDate = new ilDateTime($unixEnd, IL_CAL_DATE);
      $unixEnd = $endDate->get(IL_CAL_UNIX);
    }

    $ilDB->update(self::SEMESTER_TABLE, array(
      'name'      => array('text',    $name),
      'unixstart' => array('integer', $unixStart),
      'unixend'   => array('integer', $unixEnd)
    ), array('id' => array('integer', $id)));
  }
  public function getSemester($id) {
    global $ilDB;

    $query  = sprintf(
      'SELECT * FROM %s WHERE id = %s LIMIT 1',
      self::SEMESTER_TABLE,
      $ilDB->quote(intval($id), 'integer')
    );
    $result = $ilDB->query($query);
    $row    = $ilDB->fetchAssoc($result);

    // Convert to 'Y-m-d' format
    $startDate = new ilDateTime($row['unixstart'], IL_CAL_UNIX);
    $endDate   = new ilDateTime($row['unixend'],   IL_CAL_UNIX);
    $row['start']     = $startDate->get(IL_CAL_DATE);
    $row['end']       = $endDate->get(IL_CAL_DATE);
    $row['unixstart'] = intval($row['unixstart']);
    $row['unixend']   = intval($row['unixend']);

    return $row;
  }


  /**
   * Function: getSemesters() / setSemesters($semesters)
   *  Getter- & Setter-Methods for list of available semesters.
   *
   * @param $semesters <Array> List of semesters, with keys 'name', 'unixstart', 'unixend'
   *
   * @return <Array> List of available semester, each with information about semester, containing keys
   *                 name, start (ilDate), end (ilDate), unixstart (unix) and unixend (unix)
   */
  public function setSemesters($semesters) {
    $this->removeSemester();
    foreach ($semesters as $semester)
      $this->addSemester($semester['name'], ($semester['unixstart']) ?: $semester['start'], ($semester['unixend']) ?: $semester['end']);
  }
  public function getSemesters($filter = false) {
    global $ilDB;

    $query  = ($filter) ? sprintf(
      'SELECT * FROM %s WHERE unixend > %s',
      self::SEMESTER_TABLE,
      time()
    ) : sprintf(
      'SELECT * FROM %s',
      self::SEMESTER_TABLE
    );
    $result = $ilDB->query($query);
    $rows   = array();
    while ($row = $ilDB->fetchAssoc($result)) {
      // Convert to 'Y-m-d' format
      $startDate = new ilDateTime($row['unixstart'], IL_CAL_UNIX);
      $endDate   = new ilDateTime($row['unixend'],   IL_CAL_UNIX);
      $row['start']     = $startDate->get(IL_CAL_DATE);
      $row['end']       = $endDate->get(IL_CAL_DATE);
      $row['unixstart'] = intval($row['unixstart']);
      $row['unixend']   = intval($row['unixend']);

      array_push($rows, $row);
    }

    return $rows;
  }


  /**
   * Function: getCourseTypes()
   *  Returns a list of avaiable course-types to be used are key => value
   *  pairs in an ilSelectInputGUI object.
   *
   * @return <Array> - Associative array '<typ>' => '<typ> - trans(<type>)' of all TYPES
   */
  public function getCourseTypes() {
    // Add default value (non-selected)
    $types = array('' => $this->plugin->txt('SELECT'));

    // Add one value for each entry in TYPES
    foreach (self::CRS_TYPES as $type) {
      $lng = $this->plugin->txt("OBJ::CRS_TYPE::{$type}");
      $types[$type] = "{$type} - {$lng}";
    }

    // Return translated array
    return $types;
  }


  /**
   * Function: getESATypes()
   *  Returns a list of avaiable esa-types to be used are key => value
   *  pairs in an ilSelectInputGUI object.
   *
   * @return <Array> - Associative array '<typ>' => 'trans(<type>)' of all TYPES
   */
  public function getESATypes() {
    // Add default value (non-selected)
    $types = array('' => $this->plugin->txt('SELECT'));

    // Add one value for each entry in TYPES
    foreach (self::ESA_TYPES as $type)
      $types[$type] = $this->plugin->txt("OBJ::ESA_TYPE::{$type}");

    // Return translated array
    return $types;
  }


	/**
	 * Function: getLanguages()
	 *  Returns a list of available languages, as well as adding a star
	 *  symbol to those languages that have a text assigned to them.
	 *
	 * @return @see ilMDLanguageItem::_getLanguages()
	 */
	public function getLanguages() {
		$languages = ilMDLanguageItem::_getLanguages();

		// Append asterix to languages that have a description
		foreach($languages as $langKey => $language)
			if ($this->hasDescription($langKey))
				$languages[$langKey] = "{$language} *";

		return $languages;
	}


  /**
   * Function: processForm($form)
   *  Process submited (and validated) form, creating a new ESA-Object
   *  adding it to the right container, sending mail, etc.
   *
   * @param $from <ilPropertyFormGUI> Form to fetch values from
   *
   * @return <Number> RefID of newly created ESA-Object (or course if access-rights are available)
   */
  public function processForm($form) {
    global $ilUser, $objDefinition;

    // Import class file
    $path   = $objDefinition->getLocation('xesa');
    require_once("{$path}/class.ilObjUMResa.php");

    // Create new ESA object and set values based on form
    $esaObj = new ilObjUMResa();
    $esaObj->setCreatorId($ilUser->getId());
    $esaObj->setCourseTitle($form->getInput('obj_title'));
    $esaObj->setCourseType($form->getInput('obj_type'));
    $esaObj->setNotes($form->getInput('obj_comment'));

    // Set container ...
    $parentRefId = $form->getInput('cat_picker');
    $esaObj->setParentId($parentRefId);

    // Add creator as admin?
    if ($form->getInput('creator_errand_admin'))
      $esaObj->addExtraAdmin($ilUser->getId());

    // Set owner based on access-rights
    if (!$esaObj->canCreateCourse())
      $esaObj->setOwner(SYSTEM_USER_ID);
    else
      $esaObj->setOwner($ilUser->getId());

    // Set ESA settings
    $wantsESA = $form->getInput('obj_esa');
    $esaObj->wantsESA($wantsESA);
    if ($wantsESA) {
      $esaObj->setESANumber($form->getInput('obj_esa_nr'));
      $esaObj->setESAFormat($form->getInput('obj_esa_format'));
      $esaObj->setESASemesterId($form->getInput('obj_esa_semester'));
    }

    // Set teacher id
    switch ($form->getInput('creator')) {
      case 'creator_self':
        if ($form->getInput('creator_self_login') === $ilUser->getLogin())
          $esaObj->setTeacherId($ilUser->getId());
        break;
      case 'creator_errand':
        $login = $form->getInput('creator_errand_login');
        $esaObj->setTeacherId($form->getInput('creator_errand_login'));
        break;
    }

    // Create ILIAS object
    $containerId = $this->getContainerId();
    $esaObj->create();
    $esaObj->createReference();
    $esaObj->putInTree($containerId);
    $esaObj->setPermissions($containerId);

    // Convert to course if rights are available
    $esaRefId = $esaObj->getRefId();
    if ($esaObj->canCreateCourse())
      $crsObj = $esaObj->publishCourse();
    // Otherwise add items to admin-desktops
    else {
      $crsObj = null;
      $admins = $this->getESAAdmins();
      foreach ($admins as $admin)
        $favourites = new ilFavouritesManager();
        $favourites->add($admin, $esaObj->getRefId());

      $esaObj->sendRequestMail();
    }

    // Send maisl to HRZ, UB and Creator
    $esaObj->sendTicketMail($crsObj);

    // Return RefIdof new Course (or ESA-Object without enough permissions)
    return array('esa' => $esaObj, 'crs' => $crsObj);
  }
}
