<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/


/**
 * Class: ilObjUMResaListGUI
 *  Class for managing list-display of ESA-Object in ILIAS, eg. repository.
 */
class ilObjUMResaListGUI extends ilObjectPluginListGUI {
  //
  protected $esaForm;


  /**
   *
   */
  public function __construct() {
    parent::__construct();
  }


  /**
   * Function: initType()
   *  This is called by the constructor and should set the type to the value matching plugin.php
   */
  public function initType(): void {
    $this->setType('xesa');
  }


	/**
	 * Function: getProperties()
   *
   *
   * @return
   */
	function getProperties(): array {
		global $lng, $ilUser;

    // Fetch program-logic
    $esaObj  = new ilObjUMResa($this->ref_id);
    $esaObj->doRead();

    // Semester, ESA-Nummer
		$props = parent::getProperties();

    if ($esaObj->wantsESA()) {
      // Add semester info to list-view properties
      $props[] = array(
  			'alert'    => false,
  			'newline'  => true,
  			'property' => $this->plugin->txt('EDIT::SEMESTER'),
  			'value'    => $esaObj->getESASemesterName()
  		);
    }

		return $props;
	}


  /**
   * Function: getGuiClass()
   *  Used by the constrcutor to initialize the GUI class for thos object.
   *
   * @return <String> Name of class to use as GUI class
   */
  public function getGuiClass(): string {
    return 'ilObjUMResaGUI';
  }


  /**
   * Function: initCommands()
   *  Returns list of commands (and permissions) that are supported
   *  by this repository object (in list-view).
   *
   * @param <Array> array of arrays with entries 'permission', 'cmd', 'txt' and optionally 'default'
   */
  public function initCommands(): array {
    global $lng;

    return array(
      array(
        'permission' => 'read',
        'cmd'        => 'infoScreen',
        'default'    => true
      ),
      array(
        'permission' => 'write',
        'cmd'        => 'editProperties',
        'txt'        => $lng->txt('edit'),
        'default'    => false
      ),
      array(
        'permission' => 'publish',
        'cmd'        => 'publishCourse',
        'txt'        => $this->plugin->txt('PUBLISH'),
        'default'    => false
      ),
    );
  }
}
