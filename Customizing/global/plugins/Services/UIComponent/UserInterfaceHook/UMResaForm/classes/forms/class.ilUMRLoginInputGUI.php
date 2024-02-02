<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA-Forms) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/
require_once('Services/User/classes/class.ilUserAutoComplete.php');
require_once('Services/Form/classes/class.ilTextInputGUI.php');


/**
 * Class: ilUMRLoginInputGUI
 *  Wrapper around normal ilTextInputGUI with special ILIAS login handling.
 *
 * @ingroup	ServicesForm
 */
class ilUMRLoginInputGUI extends ilTextInputGUI {
  // Plugin object for translations, etc.
  protected $plugin;


  /**
   * Function: __construct($title, $postvar)
   *  Wrap parent constrcutor to add additional setup steps
   */
  public function __construct($title = '', $postvar = '', $acClass = null, $acMethod = 'autocompleteLogin') {
    global $ilCtrl, $DIC;

    // Call parent constructor
    parent::__construct($title, $postvar);

    // Fetch ILIAS plugin object for access to translations and such
    $component_factory = $DIC['component.factory'];
    $this->plugin = $component_factory->getPlugin('xesaform');

    // Add autocompletion for ilias users
    if ($acClass)
		  $this->setDataSource($ilCtrl->getLinkTarget($acClass, $acMethod, '', true));
  }


  /**
   * Function: checkInput()
   *  Wrap checkInput method to add checks for validity of given login.
   *
   * @return <Boolean> True of all checks succeeded, false otherwise
   */
  public function checkInput(): bool {
    // Delegate initial checks to parent implementation
    $checkParent = parent::checkInput();
    if ($checkParent) {
      // Check single login value
      if (!$this->getMulti()) {
        $login = $_POST[$this->getPostVar()];
        return $this->checkSingleLogin($login);
      }
      // Check multiple login values
      elseif (is_array($_POST[$this->getPostVar()])) {
        $logins = $_POST[$this->getPostVar()];
        foreach ($logins as $login)
          return $this->checkSingleLogin($login);
      }
    }

    // Return state of parent checks
    return $checkParent;
  }
  protected function checkSingleLogin($login) {
    $login = trim($login);
    if (strlen($login) > 0 && !ilObjUser::_loginExists($login)) {
      $this->setAlert($this->plugin->txt('ERROR::LOGIN::NOT-EXISTS'));
      return false;
    }
    return true;
  }


  /**
   * Function: autocomplete()
   *  Connects to the ILIAS ilTextInputGUI input field via AJAX requests
   *  and fetches a list of ilias logins based on criteria such as login,
   *  firstname, lastname and email.
   */
  public static function autocomplete($term) {
    // Create and configure new user-search (based on setSearchFields)
    $ac = new ilUserAutoComplete();
    $ac->setSearchFields(array('login', 'firstname', 'lastname', 'email'));
    $ac->enableFieldSearchableCheck(false);
    $ac->setMoreLinkAvailable(true);

    // The AJAX-Request will send the current value via the 'term' parameter
    // Search user with given parameter, print returned JSON
    echo $ac->getList($term);

    // Terminate php to prevent theme rendering
    exit();
  }
}
