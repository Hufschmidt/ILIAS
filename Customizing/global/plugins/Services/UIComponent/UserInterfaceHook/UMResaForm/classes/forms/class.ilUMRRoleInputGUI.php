<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA-Forms) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/
require_once('Services/Form/classes/class.ilTextInputGUI.php');


/**
 * Class: ilUMRRoleInputGUI
 *  Wrapper around normal ilTextInputGUI with special ILIAS role handling
 *
 * @ingroup	ServicesForm
 */
class ilUMRRoleInputGUI extends ilTextInputGUI {
  // Plugin object for translations, etc.
  protected $plugin;
  // Wether to acccept role, templates or both
  protected $roleType;


  /**
   * Function: __construct($title, $postvar)
   *  Wrap parent constrcutor to add additional setup steps
   */
  public function __construct($title = '', $postvar = '', $acClass = null, $acMethod = 'autocompleteRole') {
    global $ilCtrl, $DIC;

    // Call parent constructor
    parent::__construct($title, $postvar);

    // Fetch ILIAS plugin object for access to translations and such
    $component_factory = $DIC['component.factory'];
    $this->plugin = $component_factory->getPlugin('xesaform');

    // Add autocompletion for ilias users
    if ($acClass)
		  $this->setDataSource($ilCtrl->getLinkTarget($acClass, $acMethod, '', true));

    // Don't filte by default
    $this->roleType = false;
  }


  /**
   * Function: setRoleType($type)
   *  Sets the types of roles that should pass through checkInput().
   *  Use false to allow all.
   *
   * @param $type <String> Either 'role' for roles, 'rolt' for role templates or false for all
   */
  public function setRoleType($type) {
    if (in_array($type, array('role', 'rolt', false)))
      $this->roleType = $type;
    else
      $this->roleType = false;
  }


  /**
   * Function: getRoleType()
   *  Getter function, @see setRoleType()
   */
  public function getRoleType() {
    return $this->roleType;
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
      // Check single role value
      if (!$this->getMulti()) {
        $role = $_POST[$this->getPostVar()];
        return $this->checkSingleRole($role);
      }
      // Check multiple role values
      elseif (is_array($_POST[$this->getPostVar()])) {
        $roles = $_POST[$this->getPostVar()];
        foreach ($roles as $role)
          return $this->checkSingleRole($role);
      }
    }

    // Return state of parent checks
    return $checkParent;
  }
  protected function checkSingleRole($role) {
    global $rbacreview;

    if (!$rbacreview->roleExists($role)) {
      $this->setAlert($this->plugin->txt('ERROR::ROLE::NOT-EXISTS'));
      return false;
    }
    // Validate role type?
    elseif ($this->getRoleType()) {
      // Fetch role-object from its id
      $roleIds = ilObject::_getIdsForTitle($role, $this->getRoleType());
      $roleId = reset($roleIds);
      $roleObj = ilObjectFactory::getInstanceByObjId($roleId, false);

      // Check if role or role-template
      if ($this->getRoleType() === 'role' && !($roleObj instanceof ilObjRole)){
        $this->setAlert($this->plugin->txt('ERROR::ROLE::NOT-ROLE'));
        return false;
      }
      if ($this->getRoleType() === 'rolt' && !($roleObj instanceof ilObjRoleTemplate)){
        $this->setAlert($this->plugin->txt('ERROR::ROLE::NOT-TEMPLATE'));
        return false;
      }
    }
    return true;
  }


  /**
   * Function: autocomplete()
   *  Connects to the ILIAS ilTextInputGUI input field via AJAX requests
   *  and fetches a list of ilias roles based on criteria.
   */
  public static function autocomplete($type, $term) {
    if ($type) {
      // Basic result object
      $result = array(
        'items'          => array(),
        'hasMoreResults' => false
      );

      // Fetch (partially) matching roles and add to result items
      $roleIds = ilObject::_getIdsForTitle($term, $type, true);
      foreach ($roleIds as $roleId) {
        $title = ilObject::_lookupTitle($roleId);

        array_push($result['items'], array(
          'value' => $title,
          'label' => $title,
          'id'    => $roleId,
        ));
      }

      // Echo generated list of roles
      echo json_encode($result);
    }

    // Terminate to stop template rendering
    exit();
  }
}
