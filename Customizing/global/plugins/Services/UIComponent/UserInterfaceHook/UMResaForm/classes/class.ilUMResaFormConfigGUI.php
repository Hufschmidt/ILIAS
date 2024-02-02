<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA-Forms) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/


/**
 * Class: ilUMResaFormConfigGUI
 *  This class handles configuration of the plugin and
 *  is loaded by ILIAS (for all plugin types).
 *
 * @ilCtrl_Calls ilUMResaFormConfigGUI: ilPropertyFormGUI
 * @ilCtrl_IsCalledBy ilUMResaFormConfigGUI: ilObjComponentSettingsGUI
 *
 */
class ilUMResaFormConfigGUI extends ilPluginConfigGUI {
	// Plugin object for translations, etc.
	protected $plugin;
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
		$this->setPluginObject($this->plugin);

		// Create instance to manage
		$this->esaForm = new ilUMResaForm($this->plugin);
	}


	/**
	 * Function: performCommand($cmd)
	 *  Called by ILIAS via its ilCtrl when the configuration
	 *  interface is requested with ilCtrl command given as parameter.
	 *
	 * @param $cmd <String> Command that should be executed
	 *  Supported commands: edit*, save*, delete* autocomplete*
	 */
	public function performCommand(string $cmd): void {
		switch ($cmd) {
			// Allowed commands
			case 'edit':
			case 'editLanguage':
			case 'editSemester':
			case 'save':
			case 'saveLanguage':
			case 'saveSemester':
		  case 'deleteLanguage':
			case 'deleteSemester':
				$this->$cmd();
				break;
			// Special AJAX command
			case 'autocompleteLogin':
				ilUMRLoginInputGUI::autocomplete($_REQUEST['term']);
				break;
			// Special AJAX command
			case 'autocompleteRole':
				ilUMRRoleInputGUI::autocomplete('role', $_REQUEST['term']);
				break;
			// Special AJAX command
			case 'autocompleteRoleTemplate':
				ilUMRRoleInputGUI::autocomplete('rolt', $_REQUEST['term']);
				break;
			// Special AJAX command
			case 'autocompleteObject':
				ilUMRObjectInputGUI::autocomplete($_REQUEST['term'], array('cat'));
				break;
			// Fallback
			default:
				$this->edit();
				break;
		}
	}


	/**
   * Function: editSemester()
	 *  This method gets executed when command is set to 'editSemester' and
	 *  should fill global $tpl object with the lang-configuration form.
	 */
	public function editSemester() {
		global $tpl;

		// Fetch selected language from previous form
		$semSel  = $this->getSemesterSelectForm();
		$semSel->checkInput();
		$selected = $semSel->getInput('semester_sel');

		// Fetch and display language configuration
		$sem = $this->getSemesterEditForm();

		// Load selected language for editing
		if (strlen($selected) > 0) {
			$semester  = $this->esaForm->getSemester($selected, IL_CAL_DATE);
			$sem->setValuesByArray(array(
				'semester_sel'   => $selected,
				'semester_name'  => $semester['name'],
				'semester_start' => $semester['start'],
				'semester_end'   => $semester['end']
			));
		}

		// Display content in template
		$tpl->setContent($sem->getHTML());
	}


	/**
	 * Function: editLanguage()
	 *  This method gets executed when command is set to 'editLanguage' and
	 *  should fill global $tpl object with the lang-configuration form.
	 */
	public function editLanguage() {
		global $tpl;

		// Fetch selected language from previous form
		$langSel  = $this->getLanguageSelectForm();
		$langSel->checkInput();
		$selected = $langSel->getInput('lang_sel');

		// Fetch and display language configuration
		$lang = $this->getLanguageEditForm();

		// Load selected language for editing
		$lang->setValuesByArray(array(
			'lang_sel' => $selected,
			'lang_txt' => $this->esaForm->getDescription($selected),
		));

		// Display content in template
		$tpl->setContent($lang->getHTML());
	}


	/**
   * Function: edit()
	 *  This method gets executed when command is set to 'edit' (or 'configure') and
	 *  should fill global $tpl object with the configuration form.
	 */
	protected function edit() {
		global $tpl;

		// Generate form elements
		$semSel   = $this->getSemesterSelectForm();
		$langSel  = $this->getLanguageSelectForm();
		$baseEdit = $this->getBaseEditForm();

		// Load values fromm DB
		$baseEdit->setValuesByArray(array(
			'mail_hrz'    => $this->esaForm->getHRZMail(),
			'mail_ub'     => $this->esaForm->getUBMail(),
			'login_hrz'   => $this->esaForm->getESAAdmins(false),
			'role_ub'     => $this->esaForm->getUBRole(false),
			'template_ub' => $this->esaForm->getRoleTemplate(false),
			'container'   => $this->esaForm->getContainerId(),
			'ub_url'      => $this->esaForm->getUBInfoLink()
		));

		// Display content in template
		$semSel   = $semSel->getHTML();
		$langSel  = $langSel->getHTML();
		$baseEdit = $baseEdit->getHTML();
		$tpl->setContent("{$semSel}{$langSel}{$baseEdit}");
	}


	/**
	 * Function: saveSemester()
	 *  This method gets executed when command is set to 'saveSemester' and
	 *  should save the form data filled during 'editSemester' to the databse
	 *  as well as fill global $tpl object with the configuration form.
	 */
	protected function saveSemester() {
		global $tpl, $lng, $ilCtrl;

		// Fetch language configuration form
		$sem = $this->getSemesterEditForm();

		// Generate and validate form
		if ($sem->checkInput()) {
			// Store values in DB
			$selected  = $sem->getInput('semester_sel');
			$name      = $sem->getInput('semester_name');
			$startDate = $sem->getInput('semester_start');
			$endDate   = $sem->getInput('semester_end');
			if (strlen($selected) > 0)
				$this->esaForm->setSemeter($selected, $name, $startDate, $endDate);
			else
				$this->esaForm->addSemester($name, $startDate, $endDate);

			// Notify about success and go back to main config
            $tpl->setOnScreenMessage('success', $lng->txt('saved_successfully'), true);
			$ilCtrl->redirect($this, 'edit');
		}
		// Form validation failed
		else {
            $tpl->setOnScreenMessage('failure', $lng->txt('msg_form_save_error'), true);
			$sem->setValuesByPost();
			$tpl->setContent($sem->getHTML());
		}
	}


	/**
	 * Function: saveLanguage()
	 *  This method gets executed when command is set to 'saveLanguage' and
	 *  should save the form data filled during 'editLanguage' to the databse
	 *  as well as fill global $tpl object with the configuration form.
	 */
	protected function saveLanguage() {
		global $tpl, $lng, $ilCtrl;

		// Fetch language configuration form
		$lang = $this->getLanguageEditForm();

		// Generate and validate form
		if ($lang->checkInput()) {
			// Store values in DB
			$this->esaForm->setDescription($lang->getInput('lang_sel'), $lang->getInput('lang_txt'));

			// Notify about success and go back to main config
            $tpl->setOnScreenMessage('success', $lng->txt('saved_successfully'), true);
			$ilCtrl->redirect($this, 'edit');
		}
		// Form validation failed
		else {
            $tpl->setOnScreenMessage('failure', $lng->txt('msg_form_save_error'), true);
			$lang->setValuesByPost();
			$tpl->setContent($lang->getHTML());
		}
	}


	/**
	 * Function: save()
	 *  This method gets executed when command is set to 'save' and
	 *  should save the form data filled during 'edit' to the databse
	 *  as well as fill global $tpl object with the configuration form.
	 */
	protected function save() {
		global $tpl, $lng, $ilCtrl;

		// Fetch and display base (and language-select) configuration
		$semSel     = $this->getSemesterSelectForm();
		$langSel    = $this->getLanguageSelectForm();
		$baseEdit = $this->getBaseEditForm();

		// Generate and validate form
		if ($baseEdit->checkInput()) {
			// Store values in DB
			$this->esaForm->setHRZMail($baseEdit->getInput('mail_hrz'));
			$this->esaForm->setUBMail($baseEdit->getInput('mail_ub'));
			$this->esaForm->setESAAdmins($baseEdit->getInput('login_hrz'));
			$this->esaForm->setUBRole($baseEdit->getInput('role_ub'));
			$this->esaForm->setRoleTemplate($baseEdit->getInput('template_ub'));
			$this->esaForm->setContainerId($baseEdit->getInput('container'));
			$this->esaForm->setUBInfoLink($baseEdit->getInput('ub_url'));

			// Notify about success and go back to main config
            $tpl->setOnScreenMessage('success', $lng->txt('saved_successfully'), true);
			$ilCtrl->redirect($this, 'edit');
		}
		// Form validation failed
		else {
            $tpl->setOnScreenMessage('failure', $lng->txt('msg_form_save_error'), true);

			$semSel->setValuesByPost();
			$langSel->setValuesByPost();
			$baseEdit->setValuesByPost();

			$semSel   = $semSel->getHTML();
			$langSel  = $langSel->getHTML();
			$baseEdit = $baseEdit->getHTML();
			$tpl->setContent("{$semSel}{$langSel}{$baseEdit}");
		}
	}


	/**
	 * Function: deleteSemester()
	 *  Deletes the semster selected by the semester selection GUI.
	 */
	protected function deleteSemester() {
		global $lng, $ilCtrl, $tpl;

		// Fetch selected language from previous form
		$semSel  = $this->getSemesterSelectForm();
		if ($semSel->checkInput()) {
			// Notify about success
            $tpl->setOnScreenMessage('success', $lng->txt('saved_successfully'), true);

			$selected = $semSel->getInput('semester_sel');
			$this->esaForm->removeSemester($selected);
		}

		// Go back to main config
		$ilCtrl->redirect($this, 'edit');
	}


	/**
	 * Function: deleteLanguage()
	 *  Deletes the description for the languages selected by the language selection GUI.
	 */
	protected function deleteLanguage() {
		global $lng, $ilCtrl, $tpl;

		// Fetch selected language from previous form
		$langSel  = $this->getLanguageSelectForm();
		if ($langSel->checkInput()) {
			// Notify about success
            $tpl->setOnScreenMessage('success', $lng->txt('saved_successfully'), true);

			// Delete description by leaving is content out
			$selected = $langSel->getInput('lang_sel');
			$this->esaForm->setDescription($selected);
		}

		// Go back to main config
		$ilCtrl->redirect($this, 'edit');
	}


	/**
   * Function: getSemesterSelectForm()
	 *  Generates and returns the configuration form elements
	 *  for display inside a template or validation during saving.
	 *
	 * @return <ilPropertyFormGUI> Generated configuration form object
	 */
	protected function getSemesterSelectForm() {
 		global $lng, $ilCtrl;

 		// Generate new property-form
 		$form = new ilPropertyFormGUI();
 		$form->setShowTopButtons(false);
 		$form->addCommandButton('deleteSemester', $lng->txt('delete'));
		$form->addCommandButton('editSemester',   $lng->txt('edit'));
 		$form->setFormAction($ilCtrl->getFormAction($this));

 		// Add sub-section for owner/creator of new course
 		$semesterSec = new ilFormSectionHeaderGUI();
 		$semesterSec->setTitle($this->plugin->txt('SEMESTER::SEC'));
 		$form->addItem($semesterSec);

 		// Add language selection dialog
 		$semSel = new ilSelectInputGUI($this->plugin->txt('SEMESTER::SEL'), 'semester_sel');
 		$semSel->setOptions($this->getSemesterList());
 		$form->addItem($semSel);

 		// Return generated form
 		return $form;
 	}


	/**
   * Function: getLanguageSelectForm()
	 *  Generates and returns the configuration form elements
	 *  for display inside a template or validation during saving.
	 *
	 * @return <ilPropertyFormGUI> Generated configuration form object
	 */
	protected function getLanguageSelectForm() {
 		global $lng, $ilCtrl;

 		// Generate new property-form
 		$form = new ilPropertyFormGUI();
 		$form->setShowTopButtons(false);
 		$form->addCommandButton('deleteLanguage', $lng->txt('delete'));
 		$form->addCommandButton('editLanguage',   $lng->txt('edit'));
 		$form->setFormAction($ilCtrl->getFormAction($this));

 		// Add sub-section for owner/creator of new course
 		$langSec = new ilFormSectionHeaderGUI();
 		$langSec->setTitle($this->plugin->txt('LANG::SEC'));
 		$form->addItem($langSec);

 		// Add language selection dialog
 		$langSel = new ilSelectInputGUI($this->plugin->txt('LANG::SEL'), 'lang_sel');
 		$langSel->setOptions($this->esaForm->getLanguages());
		$langSel->setValue($lng->getLangKey());
 		$form->addItem($langSel);

 		// Return generated form
 		return $form;
 	}


	/**
	 * Function: getBaseEditForm()
	 *  Generates and returns the configuration form elements
	 *  for display inside a template or validation during saving.
	 *
	 * @return <ilPropertyFormGUI> Generated configuration form object
	 */
	protected function getBaseEditForm() {
		global $lng, $ilCtrl;

		// Generate new property-form
		$form = new ilPropertyFormGUI();
		$form->setShowTopButtons(false);
		$form->addCommandButton('save', $lng->txt('save'));
		$form->setFormAction($ilCtrl->getFormAction($this));

		// Add sub-section for owner/creator of new course
		$mailSec = new ilFormSectionHeaderGUI();
		$mailSec->setTitle($this->plugin->txt('MAIL::SEC'));
		$form->addItem($mailSec);

		// Add config-element for setting hrz mail
		$hrzMail = new ilEMailInputGUI($this->plugin->txt('CONFIG::MAIL::HRZ'), 'mail_hrz');
		$hrzMail->setInfo($this->plugin->txt('CONFIG::MAIL::HRZ::INFO'));
		$hrzMail->setRequired(true);
		$form->addItem($hrzMail);

		// Add config-element for setting ub mail
		$ubMail = new ilEMailInputGUI($this->plugin->txt('CONFIG::MAIL::UB'), 'mail_ub');
		$ubMail->setInfo($this->plugin->txt('CONFIG::MAIL::UB::INFO'));
		$ubMail->setRequired(true);
		$form->addItem($ubMail);

		// Add sub-section for owner/creator of new course
		$adminSec = new ilFormSectionHeaderGUI();
		$adminSec->setTitle($this->plugin->txt('ADMIN::SEC'));
		$form->addItem($adminSec);

		// Add config-element for setting hrz admin accounts
		$hrzLogin = new ilUMRLoginInputGUI($this->plugin->txt('CONFIG::LOGIN::HRZ'), 'login_hrz', $this);
		$hrzLogin->setInfo($this->plugin->txt('CONFIG::LOGIN::HRZ::INFO'));
		$hrzLogin->setRequired(true);
		$hrzLogin->setMulti(true);
		$form->addItem($hrzLogin);

		// Add config-element for setting ub member role
		$ubRole = new ilUMRRoleInputGUI($this->plugin->txt('CONFIG::ROLE::UB'), 'role_ub', $this);
		$ubRole->setInfo($this->plugin->txt('CONFIG::ROLE::UB::INFO'));
		$ubRole->setRoleType('role');
		$ubRole->setRequired(true);
		$form->addItem($ubRole);

		// Add sub-section for owner/creator of new course
		$courceSec = new ilFormSectionHeaderGUI();
		$courceSec->setTitle($this->plugin->txt('COURSE::SEC'));
		$form->addItem($courceSec);

		// Add config-element for setting ub template
		$ubTemplate = new ilUMRRoleInputGUI($this->plugin->txt('CONFIG::TEMPLATE'), 'template_ub', $this, 'autocompleteRoleTemplate');
		$ubTemplate->setInfo($this->plugin->txt('CONFIG::TEMPLATE::INFO'));
		$ubTemplate->setRoleType('rolt');
		$ubTemplate->setRequired(true);
		$form->addItem($ubTemplate);

		// Add config-element for setting container object
		$container = new ilUMRObjectInputGUI($this->plugin->txt('CONFIG::CONTAINER'), 'container', $this);
		$container->setInfo($this->plugin->txt('CONFIG::CONTAINER::INFO'));
		$container->setObjectTypes(array('cat'));
		$container->setRequired(true);
		$form->addItem($container);

		// Add sub-section for owner/creator of new course
		$miscSec = new ilFormSectionHeaderGUI();
		$miscSec->setTitle($this->plugin->txt('MISC::SEC'));
		$form->addItem($miscSec);

		// Add config-element for setting ub mail
		$ubURL = new ilTextInputGUI($this->plugin->txt('MISC::UBURL'), 'ub_url');
		$ubURL->setInfo($this->plugin->txt('MISC::UBURL::INFO'));
		$form->addItem($ubURL);

		// Return generated form
		return $form;
	}


	/**
   * Function: getLangConfig()
	 *  Generates and returns the configuration form elements
	 *  for display inside a template or validation during saving.
	 *
	 * @return <ilPropertyFormGUI> Generated configuration form object
	 */
	protected function getSemesterEditForm() {
		global $tpl, $lng, $ilCtrl;

 		// Generate new property-form
 		$form = new ilPropertyFormGUI();
 		$form->setShowTopButtons(false);
		$form->addCommandButton('edit',         $lng->txt('cancel'));
 		$form->addCommandButton('saveSemester', $lng->txt('save'));
 		$form->setFormAction($ilCtrl->getFormAction($this));

 		// Add message editing header
 		$txtSec = new ilFormSectionHeaderGUI();
 		$txtSec->setTitle($this->plugin->txt('SEMESTER::EDIT::SEC'));
 		$form->addItem($txtSec);

		// Add input form for editing semester name
		$semName = new ilTextInputGUI($this->plugin->txt('SEMESTER::EDIT::NAME'), 'semester_name');
		$semName->setRequired(true);
		$form->addItem($semName);

		// Add input form for editing semester start time
		$semStart = new ilDateTimeInputGUI($this->plugin->txt('SEMESTER::EDIT::START'), 'semester_start');
		$semStart->setRequired(true);
		$form->addItem($semStart);

		// Add input form for editing semester end time
		$semEnd = new ilDateTimeInputGUI($this->plugin->txt('SEMESTER::EDIT::END'), 'semester_end');
		$semEnd->setRequired(true);
		$form->addItem($semEnd);

		// Add hidden input to remember which form is beeing edited
		$selectedSem = new ilHiddenInputGUI('semester_sel');
		$form->addItem($selectedSem);

		// Return generated form
		return $form;
	}


	/**
   * Function: getLangConfig()
	 *  Generates and returns the configuration form elements
	 *  for display inside a template or validation during saving.
	 *
	 * @return <ilPropertyFormGUI> Generated configuration form object
	 */
	protected function getLanguageEditForm() {
		global $tpl, $lng, $ilCtrl;

 		// Generate new property-form
 		$form = new ilPropertyFormGUI();
 		$form->setShowTopButtons(false);
		$form->addCommandButton('edit',         $lng->txt('cancel'));
 		$form->addCommandButton('saveLanguage', $lng->txt('save'));
 		$form->setFormAction($ilCtrl->getFormAction($this));

 		// Add message editing header
 		$txtSec = new ilFormSectionHeaderGUI();
 		$txtSec->setTitle($this->plugin->txt('TXT::SEC'));
 		$form->addItem($txtSec);

		//
		$txt = new ilTextAreaInputGUI($this->plugin->txt('TXT::EDIT'), 'lang_txt');
		$txt->setUseRte(true);
        $txt->setRteTagSet("extended");
		$form->addItem($txt);

		// Add hidden input to remember which form is beeing edited
		$selectedLang = new ilHiddenInputGUI('lang_sel');
		$form->addItem($selectedLang);

		// Return generated form
		return $form;
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

    // Add default value (non-selected)
    $types = array('' => $lng->txt('new'));

    foreach ($this->esaForm->getSemesters(false) as $semester)
      $types[$semester['id']] = "{$semester['name']}";

    // Return translated array
    return $types;
  }
}
