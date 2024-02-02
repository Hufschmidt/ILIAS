<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/
require_once('Services/Form/classes/class.ilPropertyFormGUI.php');
require_once('Services/Repository/classes/class.ilRepositorySelectorExplorerGUI.php');


/**
 * Class: ilObjUMResaGUI
 *  GUI class for UMResa ILIAS objects. Contains methods which are called for viewing and modifying
 *  UMResa objects.
 *
 * @ilCtrl_isCalledBy ilObjUMResaGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls ilObjUMResaGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 */
class ilObjUMResaGUI extends ilObjectPluginGUI {
  // List of visiable and clickables types
  const VISIBILE_TYPES  = array('root', 'cat', 'grp', 'fold', 'crs');
  const CLICKABLE_TYPES = array('cat');
  // Stores a reference to the UMResaForm logic object
  protected $esaForm;


  /**
   * Function: __construct()
   *  Fetches the ILIAS plugin object for this plugin on creation
   *  and includes classes required for form generation.
   */
  public function __construct($refId = 0, $idType = self::REPOSITORY_NODE_ID, $parentNodeId = 0) {
    parent::__construct($refId, $idType, $parentNodeId);

    // Load the UMResaForm logic class for later use
    $formPlugin = $this->plugin->getUMResaFormPlugin();
    $this->esaForm = new ilUMResaForm();
  }


  /**
   * Function: getType()
   *  Returns type/id of plugin such that ILIAS knows
   *  of which type this repository object is.
   *
   * @return <String> Type/ID of plugin, should match plugin.php
   */
  public final function getType(): string {
 		return 'xesa';
 	}


  /**
   * Function: setTabs()
   *  Called by ILIAS when rendering the objects GUI, should add all
   *  required tabs.
   */
  public function setTabs(): void {
    global $ilTabs, $ilCtrl, $ilAccess;

    // Add edit-properties tab
    if ($ilAccess->checkAccess('write', '', $this->object->getRefId())) {
      $ilTabs->addTab('properties', $this->plugin->txt('TAB::REQUEST'), $ilCtrl->getLinkTarget($this, 'editProperties'));
      $ilTabs->addTab('parent',     $this->plugin->txt('TAB::PARENT'),  $ilCtrl->getLinkTarget($this, 'editParent'));
    }

    // Add publish-course tab
    if ($ilAccess->checkAccess('publish', '', $this->object->getRefId()))
    {
        $ilTabs->addNonTabbedLink("publishCourse", $this->txt("PUBLISH"), $ilCtrl->getLinkTarget($this, "publishCourse"));
    }

    // Add standard info screen tab
    $this->addInfoTab();

    // Add standard permission tab
    $this->addPermissionTab();
  }


  /**
   * Function: getStandardCmd()
   *  This command will be executed when the class is called without 'cmd'.
   *
   * @return <String> Command that should be executed when no command was given
   */
 	public function getStandardCmd(): string {
 		return 'infoScreen';
 	}


  /**
   * Function: getAfterCreationCmd()
   *  This command will be executed after the class was created, eg by ILIAS ilRepositoryGUI.
   *
   * @return <String> Command to be executed after creation
   */
	public function getAfterCreationCmd(): string {
		return 'infoScreen';
	}


	/**
	 * Function: performCommand($cmd)
	 *  This methods gets called by executeCommand() with the ilCtrl command
	 *  given as parameter. It is responsible for selecting the correct method
	 *  (based on $command) that should be used to add content to the template.
	 *
	 * @param $cmd <String> Command that should be executed
	 *  Supported commands: editProperties, saveProperties, editParent, saveParent, autocompleteLogin
	 */
	public function performCommand(string $cmd): void {
		switch ($cmd) {
			// Allowed commands
			case 'editProperties':
			case 'saveProperties':
      case 'editParent':
      case 'saveParent':
      case 'publishCourse':
				$this->checkPermission('write');
				$this->$cmd();
				break;
			// Special AJAX command
			case 'autocompleteLogin':
				ilUMRLoginInputGUI::autocomplete($_REQUEST['term']);
				break;
			// Fallback
			default:
				$this->infoScreen();
				break;
		}
	}


  /**
   * Function: publishCourse()
   *  Converts this esa-object into a course-object with its internal settings.
   *  This also deletes the esa-object.
   */
  public function publishCourse() {
    global $tree, $tpl;

    // Convert esa-obj to course
    $crsObj = $this->object->publishCourse();

    // ESA-Object was published as course
    if ($crsObj) {
      $tpl->setOnScreenMessage('success', $this->plugin->txt('PUBLISHED'), true);
      ilUtil::redirect(ilLink::_getLink($crsObj->getRefId()));
    }
    // Converion failed, go to parent...
    else {
      $tpl->setOnScreenMessage('failure', $this->plugin->txt('PUBLISHED::NOT'), true);
      $refId    = $this->object->getRefId();
      $parentId = $tree->getParentId($refId);
      ilUtil::redirect(ilLink::_getLink($parentId));
    }
  }


	/**
	 * Function: editProperties()
	 *  This method gets called when rendering this GUI class
	 *  with command 'editProperties' and will display the initial view
	 *  for the generation of an ESA.
	 */
	protected function editProperties() {
		global $tpl, $ilTabs;

		// Fetch form and display its content
		$form = $this->getEditForm();

    // Load values fromm DB
		$form->setValuesByArray(array(
			'teacher'    => ilObjUser::_lookupLogin($this->object->getTeacherId()),
      'title'      => $this->object->getCourseTitle(),
      'type'       => $this->object->getCourseType(),
      'admins'     => $this->object->getExtraAdmins(true),
		));

    //
		$tpl->setContent($form->getHTML());
    $ilTabs->setTabActive('properties');
	}


  /**
   * Function: editParent()
   *  This methid is called with command 'editParent'.
   *  Shows the edit parent object GUI tab. Also forwards commands to
   *  RepositorySelectorInput in order to render sub-commands/guis.
   */
  protected function editParent() {
    global $tpl, $ilTabs;

    // Fetch form and let RepositorySelectorInput handle command too
    $form = $this->getParentForm($this->object->getParentId());
    if ($form->handleCommand())
      return;

    // Display form content if command wasn't handled by RepositorySelectorInput
    $tpl->setContent($form->getHTML());
    $ilTabs->setTabActive('parent');
  }


	/**
	 * Function: saveProperties()
	 *  This method gets called when rendering the GUI class
	 *  with command 'saveProperties' and use program logic in ilUMResaForm
	 *  to create courses or helper-objects for ESA.
	 */
	protected function saveProperties() {
		global $tpl, $lng, $ilCtrl, $ilTabs;

		// Generate and validate form
		$form = $this->getEditForm();
		if ($form->checkInput()) {

			// Store values in DB
      $this->object->setTeacherId(ilObjUser::_lookupId($form->getInput('teacher')));
      $this->object->setCourseTitle($form->getInput('title'));
      $this->object->setCourseType($form->getInput('type'));
      $this->object->setExtraAdmins($form->getInput('admins'));
      $this->object->doUpdate();

			// Notify about success and go back to main config
            $tpl->setOnScreenMessage('success', $lng->txt('saved_successfully'), true);
			$ilCtrl->redirect($this, 'editProperties');
		}
		// Form validation failed
		else {
            $tpl->setOnScreenMessage('failure', $lng->txt('msg_form_save_error'), true);
			$form->setValuesByPost();
			$tpl->setContent($form->getHTML());
      $ilTabs->setTabActive('properties');
		}
	}


  /**
   * Function: saveParent()
   *  This method is called with the command 'saveParent' and checks
   *  the 'parent_id' parameter, saving it on success.
   */
  protected function saveParent() {
    global $tpl, $lng, $ilCtrl, $ilTabs;

		// Fetch 'parent_id' from RepositorySelectorInput (ILIAS...)
    $parentId = $_REQUEST['parent_id'];
    if (in_array(ilObject::_lookupType($parentId, true), self::VISIBILE_TYPES)) {
      // Update parent and store to DB
      $this->object->setParentId($parentId);
      $this->object->doUpdate();

      // Send success
      $tpl->setOnScreenMessage('success', $lng->txt('saved_successfully'), true);
    }
    // New parent selection failed
    else
      $tpl->setOnScreenMessage('failure', $lng->txt('msg_form_save_error'), true);

    // Show the parent-selection form again
    $form = $this->getParentForm($this->object->getParentId());
    $tpl->setContent($form->getHTML());
    $ilTabs->setTabActive('parent');
  }


	/**
	 * Function: getEditForm()
	 *  Creates and returns the property form object for creating a new ESA course object.
	 *
	 * @return <ilPropertyFormGUI> Property form to create new ESA course object
	 */
	public function getEditForm() {
		global $ilCtrl, $lng;

		// Create a new property form and configure it
		$form   = new ilPropertyFormGUI();
		$form->setTitle($this->plugin->txt('EDIT::SEC::TITLE'));
		$form->setShowTopButtons(true);
		$form->setFormAction($ilCtrl->getFormAction($this, 'saveProperties'));
		$form->addCommandButton('saveProperties', $lng->txt('save'));

    // Add text form for teacher input
		$teacher = new ilUMRLoginInputGUI($this->plugin->txt('EDIT::TEACHER'), 'teacher', $this);
		$teacher->setRequired(true);
		$form->addItem($teacher);

    // Add text form for title input
		$title = new ilTextInputGUI($this->plugin->txt('EDIT::TITLE'), 'title');
		$title->setRequired(true);
		$form->addItem($title);

    // Add form selection input for type
		$type = new ilSelectInputGUI($this->plugin->txt('EDIT::TYPE'), 'type');
		$type->setOptions($this->esaForm->getCourseTypes());
		$type->setRequired(true);
		$form->addItem($type);

    // Add multi-text input for additional admins
		$admins = new ilUMRLoginInputGUI($this->plugin->txt('EDIT::ADMINS'), 'admins', $this);
		$admins->setMulti(true);
		$form->addItem($admins);

		// Return generated form element
		return $form;
	}


  /**
   * TODO: Document
   */
  public function getParentForm($parentId = null) {
    // Create new repository selection input for parent object selection
    $exp = new ilRepositorySelectorExplorerGUI($this, 'editParent', $this, 'saveParent', 'parent_id');
    $exp->setTypeWhiteList(self::VISIBILE_TYPES);
    $exp->setClickableTypes(self::CLICKABLE_TYPES);

    // Highlight currently active parent if one is available (saved)
    if ($parentId) {
      $exp->setPathOpen($parentId);
      $exp->setHighlightedNode($parentId);
    }

    // Return selector
    return $exp;
  }


  /**
   * Function: initCreationForms($newType)
   *  This methid is called by ILIAS when creating a new UMResa object
   *  via the ilRepositoryGUI. This returns an empty form since this
   *  task is for the UMResaForm plugin.
   *
   * @return
   */
  protected function initCreationForms(string $newType): array {
		global $ilCtrl, $lng;

    // Create an empty object creation form, since this object is created by the UMResaForm plugin!
		$form = new ilPropertyFormGUI();
		$form->setFormAction($ilCtrl->getParentReturn($this));
		$form->setTitle($this->txt("{$newType}_new"));
		$form->setDescription($this->plugin->txt('NEW::DESC'));
		$form->addCommandButton('cancel', $lng->txt('cancel'));

    // Return as 'new object' form
		return array(
			self::CFORM_NEW => $form
		);
  }


	/**
	 * Function: addInfoItems($info)
   *  This method is called by ILIAS when opening the infoScreen tab.
   *  Items can be added to the given $info parameter.
   *
   * @param $info <ilInfoScreenGUI> Info screen that will get rendered by ILIAS on the infoScreen tab
	 */
  public function addInfoItems(ilInfoScreenGUI $info): void {
    global $ilCtrl, $lng;

    // Add info for settings that can be edited
    $info->addSection($this->plugin->txt('INFO::SETTINGS'));
    $info->addProperty($this->plugin->txt('INFO::PARENT'),    ilObjUMResa::getPath($this->object->getParentId()));
    $info->addProperty($this->plugin->txt('INFO::TEACHER'),   ilObjUser::_lookupLogin($this->object->getTeacherId()));
    $info->addProperty($this->plugin->txt('INFO::TITLE'),     $this->object->getCourseTitle());
    $info->addProperty($this->plugin->txt('INFO::FULLTITLE'), $this->object->getTitle());
    $info->addProperty($this->plugin->txt('INFO::TYP'),       $this->object->getCoursetype(true));
    if (count($this->object->getExtraAdmins()) > 0)
      $info->addProperty($this->plugin->txt('INFO::ADMINS'),    implode(', ', $this->object->getExtraAdmins(true)));

    // Add info for settings that could only be set during creation
    $info->addSection($this->plugin->txt('INFO::DATA'));
    $info->addProperty($this->plugin->txt('INFO::CREATOR'), ilObjUser::_lookupLogin($this->object->getCreatorId()));
    if ($this->object->getPhone())
      $info->addProperty($this->plugin->txt('INFO::PHONE'), $this->object->getPhone());
    $info->addProperty($this->plugin->txt('INFO::ESA'), $this->object->wantsESA() ? $lng->txt('yes') : $lng->txt('no'));
    if ($this->object->wantsESA()) {
      $info->addProperty($this->plugin->txt('INFO::ESA::NUMBER'), $this->object->getESANumber());
      $info->addProperty($this->plugin->txt('INFO::ESA::FORMAT'), $this->object->getESAFormat(true));
      $info->addProperty($this->plugin->txt('INFO::ESA::SEMESTER'), $this->object->getESASemesterName());
    }
    if ($this->object->getNotes())
      $info->addProperty($this->plugin->txt('INFO::NOTES'), $this->object->getNotes());
  }


  /**
   * Function: getSemesterList($plugin)
   *  Returns the list of semesters useable in selection form.
   *
   * @param $plugin <ilPlugin> Plugin-object to fetch translations
   *
   * @return <Array> List of $id => $name for all available semesters
   */
  protected function getSemesterList() {
		global $lng;

    // Translate each semester
    foreach ($this->esaForm->getSemesters() as $semester)
      $types[$semester['id']] = "{$semester['name']}";

    // Return translated array
    return $types;
  }
}
