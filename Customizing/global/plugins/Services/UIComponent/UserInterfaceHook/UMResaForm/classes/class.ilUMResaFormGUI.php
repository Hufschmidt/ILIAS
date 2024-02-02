<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA-Forms) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/
require_once('Services/Link/classes/class.ilLink.php');
require_once('Services/Tree/classes/class.ilPathGUI.php');
require_once('Services/Form/classes/class.ilPropertyFormGUI.php');
require_once("Services/Form/classes/class.ilCheckboxInputGUI.php");
require_once('Services/Form/classes/class.ilRepositorySelectorInputGUI.php');


/**
 * Class: ilUMResaFormGUI
 *  Custom GUI class for rendering ESA specific interface-elements
 *  inside ILIAS while the actual ESA login is implemented by ilUMResaForm.
 *  This class should/will be invoked via the ilUIPluginRouterGUI class,
 *  see ilUMResaFormUIHookGUI for details.
 *
 * @ilCtrl_Calls ilUMResaFormGUI: ilPropertyFormGUI
 * @ilCtrl_IsCalledBy ilUMResaFormGUI: ilUIPluginRouterGUI, ilObjPluginDispatchGUI
 */
class ilUMResaFormGUI {
	// Plugin object for translations, etc.
	protected $plugin;
	// Reference id of selected parent object (category)
	protected $parentRefId;
	// Store reference to logic code
	protected $esaForm;


	/**
	 * Function: __construct()
	 *  Fetches the ILIAS plugin object for this plugin on creation
	 *  and includes classes required for form generation.
	 */
	public function __construct() {
        global $DIC;
		// Fetch plugin object for access to translations and such
        $component_factory = $DIC['component.factory'];
        $this->plugin = $component_factory->getPlugin('xesaform');

		// Create instance to manage
		$this->esaForm = new ilUMResaForm($this->plugin);

		// No parent selected by default
		$this->parentRefId = null;
	}


	/**
	 * Function: executeCommand()
   *  This method gets called by ilUIPluginRouterGUI (actually ilCtrl) when being
   *  displayed using ilCtrl (and routed through ilUIPluginRouterGUI as BaseClass).
	 *  It renders the ILIAS standard template and leaves the rest (filling it) to others.
	 */
	public function executeCommand() {
    global $tpl, $ilCtrl;

		// Render custom-content inside ilias standard template
		$tpl->loadStandardTemplate();
		$tpl->setTitle($this->plugin->txt('TITLE'));

		// Forward command to nested GUI class?
		$next = $ilCtrl->getNextClass();

		// Forward to item of property form
		if ($next === 'ilpropertyformgui') {
			$form = $this->getEditForm();
			$ilCtrl->setReturn($this, 'editProperties');
			$ilCtrl->forwardCommand($form);
		}
		// Handle command ourself
		else
			$this->performCommand($ilCtrl->getCmd('editProperties'));

		// Finally show generated template
		$tpl->printToStdout();
	}


	/**
	 * Function: performCommand($cmd)
	 *  This methods gets called by executeCommand() with the ilCtrl command
	 *  given as parameter. It is responsible for selecting the correct method
	 *  (based on $command) that should be used to add content to the template.
	 *
	 * @param $cmd <String> Command that should be executed
	 *  Supported commands: editProperties, saveProperties, autocompleteLogin
	 */
	public function performCommand(string $cmd): void {
		switch ($cmd) {
			// Allowed commands
			case 'editProperties':
			case 'saveProperties':
			case 'redirectDone':
				$this->$cmd();
				break;
			// Special AJAX command
			case 'autocompleteLogin':
				ilUMRLoginInputGUI::autocomplete($_REQUEST['term']);
				break;
			// Fallback
			default:
				$this->editProperties();
				break;
		}
	}


	/**
	 * Function: redirectDone()
	 *  This function is called by the GUI via redirect once
	 *  the course-object was successfully created.
	 *  The redirect is required for ILIAS to update is RBAC checks...
	 */
	protected function redirectDone() {
		global $ilCtrl, $ilAccess, $tpl;

		$refId = $_GET['ref_id'];
		if ($refId && $ilAccess->checkAccess('read', '', $refId)) {
			// Build success txt
			$txtDone = sprintf($this->plugin->txt('CREATED::DONE'), $this->getPath($refId));
			$txtInfo = $this->plugin->txt('CREATED::DONE::PERM');

			// Send success and recirect to course
            $tpl->setOnScreenMessage('success', "{$txtDone} <br><br>{$txtInfo}", true);
			ilUtil::redirect(ilLink::_getLink($refId));
		}
		else {
			// Build success txt
			$txtDone = sprintf($this->plugin->txt('CREATED::DONE'), $this->getPath($refId));
			$txtInfo = $this->plugin->txt('CREATED::DONE::NOPERM');

			// Send succes and go to repository
            $tpl->setOnScreenMessage('success', "{$txtDone} <br><br>{$txtInfo}", true);
			$ilCtrl->redirectByClass('ilRepositoryGUI');
		}
	}


	/**
	 * Function: editProperties()
	 *  This method gets called when rendering this GUI class
	 *  with command 'editProperties' and will display the initial view
	 *  for the generation of an ESA.
	 */
	protected function editProperties() {
		global $tpl;

		// Fetch form and display its content
		$form = $this->getEditForm();
		$tpl->setContent($form->getHTML());
	}


	/**
	 * Function: saveProperties()
	 *  This method gets called when rendering the GUI class
	 *  with command 'saveProperties' and use program logic in ilUMResaForm
	 *  to create courses or helper-objects for ESA.
	 */
	protected function saveProperties() {
		global $tpl, $lng, $ilCtrl;

		// Generate and validate form
		$form = $this->getEditForm();
		if ($form->checkInput()) {
			// Process form (create esa-obj and try to convert to course)
			$objects = $this->esaForm->processForm($form);

			// Completetely done!
			if ($objects['crs']) {
				$ilCtrl->setParameter($this, 'ref_id', $objects['crs']->getRefId());
				ilUtil::redirect($ilCtrl->getLinkTarget($this, 'redirectDone', '', false, false));
			}
			// Pending
			else {
				// Build success txt
				$txtPend = $this->plugin->txt('CREATED::PENDING');
				$txtInfo = $this->plugin->txt('CREATED::DONE::NOPERM');

				// Send success and go repository
                $tpl->setOnScreenMessage('success', "{$txtPend} <br>{$txtInfo}", true);
				$ilCtrl->redirectByClass('ilRepositoryGUI');
			}
		}
		// Form validation failed
		else {
            $tpl->setOnScreenMessage('failure', $lng->txt('msg_form_save_error'), true);
			$form->setValuesByPost();
			$tpl->setContent($form->getHTML());
		}
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
		$form->setTitle($this->plugin->txt('SUBTITLE'));
		$form->setDescription($this->getFormDescription());
		$form->setShowTopButtons(false);
		$form->setFormAction($ilCtrl->getFormAction($this, 'saveProperties'));
		$form->addCommandButton('saveProperties', $lng->txt('compose'));

		// Add sub-sections
		$this->addPickerSection($form);
		$this->addCreatorSection($form);
		$this->addObjectSection($form);

		// Return generated form element
		return $form;
	}


	/**
	 * Function: addPickerSection($form)
	 *  Create category picker section and add it given property form object.
	 *
	 * @param form <ilPropertyFormGUI> Property form object to add section to
	 */
	protected function addPickerSection($form) {
		// Add sub-section for target categorie selection
		$pickerSection = new ilFormSectionHeaderGUI();
		$pickerSection->setTitle($this->plugin->txt('CAT::SEC'));
		$form->addItem($pickerSection);

		// Add repository picker to select target categorie
		$catPpicker = new ilRepositorySelectorInputGUI($this->plugin->txt('CAT::PICK'), 'cat_picker');
		$catPpicker->setInfo($this->plugin->txt('CAT::PICK::INFO'));
		$catPpicker->setClickableTypes(['cat']);
		$catPpicker->setSelectText($this->plugin->txt('CAT::PICK::SELECT'));
		$catPpicker->setHeaderMessage($this->plugin->txt('CAT::PICK::HEADER'));
		$catPpicker->setRequired(true);
		$form->addItem($catPpicker);

		// Load selected target categorie since this is handle by another class via redirects
		$catPpicker->readFromSession();
		$this->parentRefId = $catPpicker->getValue();

		// Display information about access-rights (can create course-object)
		$catRBAC = new ilCheckBoxInputGUI($this->plugin->txt('CAT::RBAC'), 'cat_rbac');
		$catRBAC->setOptionTitle($this->plugin->txt('CAT::RBAC::TITLE'));
		$catRBAC->setInfo($this->plugin->txt('CAT::RBAC::INFO'));
		$catRBAC->setChecked($this->canCreateCourse($this->parentRefId));
		$catRBAC->setDisabled(true);
		$form->addItem($catRBAC);
	}


	/**
	 * Function: addCreatorSection($form)
	 *  Create creator section and add it given property form object.
	 *
	 * @param form <ilPropertyFormGUI> Property form object to add section to
	 */
	protected function addCreatorSection($form) {
		global $ilUser, $ilCtrl;

		// Add sub-section for owner/creator of new course
		$creatorSection = new ilFormSectionHeaderGUI();
		$creatorSection->setTitle($this->plugin->txt('CREATOR::SEC'));
		$form->addItem($creatorSection);

		// Add sub-option to use current ilUser as creator or set via input-field
		$creator       = new ilRadioGroupInputGUI($this->plugin->txt('CREATOR'), 'creator');
		$creatorSelf   = new ilRadioOption($this->plugin->txt('CREATOR::SELF'),   'creator_self');
		$creatorErrand = new ilRadioOption($this->plugin->txt('CREATOR::ERRAND'), 'creator_errand');

		// Show current ilUser name
		$creatorSelfName = new ilTextInputGUI($this->plugin->txt('CREATOR::SELF::NAME'), 'creator_self_name');
		$creatorSelfName->setDisabled(true);
		$creatorSelfName->setValue($ilUser->getFullName());
		$creatorSelf->addSubItem($creatorSelfName);

		// Show current ilUser login
		$creatorSelfLogin = new ilTextInputGUI($this->plugin->txt('CREATOR::SELF::LOGIN'), 'creator_self_login');
		$creatorSelfLogin->setDisabled(true);
		$creatorSelfLogin->setValue($ilUser->getLogin());
		$creatorSelf->addSubItem($creatorSelfLogin);

		// Show input dialog for using an existing ilias account as creator
		$creatorErrandLogin = new ilUMRLoginInputGUI($this->plugin->txt('CREATOR::ERRAND::LOGIN'), 'creator_errand_login', $this);
		$creatorErrandLogin->setInfo($this->plugin->txt('CREATOR::ERRAND::LOGIN::INFO'));
		$creatorErrandLogin->setRequired(true);
		$creatorErrand->addSubItem($creatorErrandLogin);

		// Check wether to add current ilUser as admin to created course
		$creatorErrandAdmin = new ilCheckBoxInputGUI($this->plugin->txt('CREATOR::ERRAND::ADMIN'), 'creator_errand_admin');
		$creatorErrandAdmin->setOptionTitle($this->plugin->txt('CREATOR::ERRAND::ADMIN::TITLE'));
		$creatorErrandAdmin->setInfo($this->plugin->txt('CREATOR::ERRAND::ADMIN::INFO'));
		$creatorErrand->addSubItem($creatorErrandAdmin);

		// Add sub-options to form
		$creator->addOption($creatorSelf);
		$creator->addOption($creatorErrand);
		$creator->setValue('creator_self');
		$creator->setRequired(true);
		$form->addItem($creator);

		// Phone number is required for feedback whithout enough access-rights
		if (!$this->canCreateCourse($this->parentRefId)) {
			$creatorErrandPhone = new ilTextInputGUI($this->plugin->txt('CREATOR::ERRAND::PHONE'), 'creator_errand_phone');
			$creatorErrandPhone->setInfo($this->plugin->txt('CREATOR::ERRAND::PHONE::INFO'));
			$creatorErrandPhone->setValue(ilUMResaForm::DEFAULT_PHONE);
			$creatorErrandPhone->setRequired(true);
			$creatorErrandPhone->setValidationRegexp('/^(?!(' . preg_quote(ilUMResaForm::DEFAULT_PHONE) . '$))[0-9- \/]+$/i');
			$form->addItem($creatorErrandPhone);
		}
	}


	/**
	 * Function: addObjectSection($form)
	 *  Create esa-object picker section and add it given property form object.
	 *
	 * @param form <ilPropertyFormGUI> Property form object to add section to
	 */
	protected function addObjectSection($form) {
		// Construct info with link
		$esaInfo = $this->plugin->txt('OBJ::ESA::INFO');
		$ubLink = $this->esaForm->getUBInfoLink();
		if ($ubLink && strlen($ubLink) > 0)
			$esaInfo = sprintf($esaInfo, $ubLink);

		// Add sub-section for course information
		$objSection = new ilFormSectionHeaderGUI();
		$objSection->setTitle($this->plugin->txt('OBJ::SEC'));
		$form->addItem($objSection);

		// Add input field for course title
		$objTitle = new ilTextInputGUI($this->plugin->txt('OBJ::TITLE'), 'obj_title');
		$objTitle->setRequired(true);
		$form->addItem($objTitle);

		// Add input selection for type of course
		$objType = new ilSelectInputGUI($this->plugin->txt('OBJ::TYPE'), 'obj_type');
		$objType->setOptions($this->esaForm->getCourseTypes());
		$objType->setRequired(true);
		$form->addItem($objType);

		// Add sub-option to use current ilUser as creator or set via input-field
		$objESA = new ilCheckBoxInputGUI($this->plugin->txt('OBJ::ESA'), 'obj_esa');
		$objESA->setOptionTitle($this->plugin->txt('OBJ::ESA::TITLE'));
		$objESA->setInfo($esaInfo);

		// Show input dialog for using an existing ilias account as creator
		$objESAnr = new ilTextInputGUI($this->plugin->txt('OBJ::ESA::NR'), 'obj_esa_nr');
		$objESAnr->setRequired(true);
		$objESA->addSubItem($objESAnr);

		// Check wether to add current ilUser as admin to created course
		$objESAformat = new ilSelectInputGUI($this->plugin->txt('OBJ::ESA::FORMAT'), 'obj_esa_format');
		$objESAformat->setOptions($this->esaForm->getESATypes());
		$objESAformat->setRequired(true);
		$objESA->addSubItem($objESAformat);

		// Add date selection for start of course
		$objSemester = new ilSelectInputGUI($this->plugin->txt('OBJ::SEMESTER'), 'obj_esa_semester');
		$objSemester->setOptions($this->getSemesterList());
		$objSemester->setRequired(true);
		$objESA->addSubItem($objSemester);

		// Add sub-options to form
		$form->addItem($objESA);

		// Additional (optional) comments
		$objComment = new ilTextAreaInputGUI($this->plugin->txt('OBJ::COMMENT'), 'obj_comment');
		$objComment->setRows(4);
		$form->addItem($objComment);
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
    // Add default value (non-selected)
    $types = array('' => $this->plugin->txt('SELECT'));

    foreach ($this->esaForm->getSemesters(true) as $semester)
      $types[$semester['id']] = $semester['name'];

    // Return translated array
    return $types;
  }


	/**
	 * Function: getFormDescription()
	 *  Returns language-key based description of the ESA form
	 *  from the tpl.description_<key>.html templates.
	 *
	 * @return <String> Translated description of ESA form header
	 */
	protected function getFormDescription() {
		global $lng;

		// Fetch description based on users language key or fallback to 'en'
		$desc = '';
		if ($this->esaForm->hasDescription($lng->getLangKey()))
			$desc = $this->esaForm->getDescription($lng->getLangKey());
		elseif ($this->esaForm->hasDescription('en'))
			$desc = $this->esaForm->getDescription('en');

		// Replace {UB_LINK} just like in templates
		$ubLink = $this->esaForm->getUBInfoLink();
		if ($desc && $ubLink && strlen($ubLink) > 0)
			$desc = str_replace('{UB_LINK}', $ubLink, $desc);

		return $desc;
	}


  /**
   * Function: canCreateCourse($refId)
   *  Checks if the user is allowed to create new course-objects inside the object given by $refId.
   *
   * @param $refId <Number> Ref-id ob object to check creation right for new course object on
   *
   * @return <Boolean> True if user is allowed to create course inside object given by refId, false otherwise
   */
  protected function canCreateCourse($refId) {
    global $rbacsystem;

    // Check create(crs) access rights on given refid
    if ($refId)
      return $rbacsystem->checkAccess('create', $refId, 'crs');

    // No object selected
    return false;
  }


  /**
   * Function: getPath($refId, $textOnly)
	 *  Returns formated path (string) to given object.
	 *
	 * @param $refId <Number> Object to construct path to
	 * @param $textOnly <Boolean> Wether to return text (true) or full html code (false)
	 *
	 * @return <String> Constructed path to object
   */
	public function getPath($refId, $textOnly = true) {
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
