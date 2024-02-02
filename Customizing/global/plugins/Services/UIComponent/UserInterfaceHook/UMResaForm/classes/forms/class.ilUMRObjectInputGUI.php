<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA-Forms) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/
require_once('Services/Form/classes/class.ilTextInputGUI.php');
require_once('Services/Search/classes/class.ilSearchSettings.php');
require_once('Services/Search/classes/Lucene/class.ilLuceneQueryParser.php');
require_once('Services/Search/classes/Lucene/class.ilLuceneSearcher.php');


/**
 * Class: ilUMRObjectInputGUI
 *  Wrapper around normal ilTextInputGUI with special ILIAS object handling.
 *
 * @ingroup	ServicesForm
 */
class ilUMRObjectInputGUI extends ilTextInputGUI {
  // Plugin object for translations, etc.
  protected $plugin;
  // Requested object types
  protected $objTypes;


  /**
   * Function: __construct($title, $postvar)
   *  Wrap parent constrcutor to add additional setup steps
   */
  public function __construct($title = '', $postvar = '', $acClass = null, $acMethod = 'autocompleteObject') {
    global $ilCtrl, $DIC;

    // Call parent constructor
    parent::__construct($title, $postvar);

    // Fetch ILIAS plugin object for access to translations and such
    $component_factory = $DIC['component.factory'];
    $this->plugin = $component_factory->getPlugin('xesaform');

    // Add autocompletion for ilias users
    if ($acClass)
		  $this->setDataSource($ilCtrl->getLinkTarget($acClass, $acMethod, '', true));

    // ilNumberInputGUI sucks balls a bit more then ilTextInputGUI, so we emulate it using validation regex
    $this->setValidationRegexp('/[0-9]+/');

    // Don't filter anything by default
    $this->objTypes = false;
  }


  /**
   * Function: setObjectTypes($types)
   *  Sets the types of objects that should pass through checkInput().
   *  Use false to allow all.
   *
   * @param $types <Array> List of allowed objects types (as returned by ilObject->getType())
   */
  public function setObjectTypes($types) {
    $this->objTypes = $types;
  }


  /**
   * Function: getCourseTypes()
   *  Getter function, @see setObjectTypes()
   */
  public function getCourseTypes() {
    return $this->objTypes;
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
        $refId = $_POST[$this->getPostVar()];
        return $this->checkSingleObject($refId);
      }
      // Check multiple login values
      elseif (is_array($_POST[$this->getPostVar()])) {
        $refIds = $_POST[$this->getPostVar()];
        foreach ($refIds as $refId)
          return $this->checkSingleObject($refId);
      }
    }

    // Return state of parent checks
    return $checkParent;
  }
  protected function checkSingleObject($refId) {
    // Fetch object with given id
    $obj = ilObjectFactory::getInstanceByRefId($refId, false);

    // Check if there is an ilias object with this id
    if (!($obj instanceof ilObject)) {
      $this->setAlert($this->plugin->txt('ERROR::OBJ::NOT-EXISTS'));
      return false;
    }
    // Check if object type is of requested type
    elseif ($this->getCourseTypes() && !in_array($obj->getType(), $this->getCourseTypes())) {
      $this->setAlert(sprintf($this->plugin->txt('ERROR::OBJ::WRONG-TYPE'), implode(', ', $this->getCourseTypes())));
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
  public static function autocomplete($term, $types) {
    if ($types) {
      // Basic result object
      $result = array(
        'items'          => array(),
        'hasMoreResults' => false
      );

      // Fetch matching (maximum) results (via lucene or DB)
      $max             = ilSearchSettings::getInstance()->getAutoCompleteLength() ? ilSearchSettings::getInstance()->getAutoCompleteLength() : 10;
      $result['items'] = (ilSearchSettings::getInstance()->enabledLucene()) ? self::getLuceneList($term, $max, $types) : self::getDBList($term, $max, $types);

      // Echo generated list of roles
      echo json_encode($result);
    }

    // Terminate to stop template rendering
    exit();
  }


  /**
   * Function: getLuceneList($term, $max, $types)
   *  Fetches a list of ilias objects matching term (partially) using lucene.
   *
   * @param $term <String> Term to (partially) search for
   * @param $max <Number> Maximum number of results to return
   * @param $types <Array> Arrayof ILIAS types (as returned by ilObject->getType()) to should be included in the search
   *
   * @return <Array> List of search results, with entries 'value' -> ReferenceID, 'label' -> Title, 'id' -> ObjectID for each result
   */
  protected static function getLuceneList($term, $max, $types) {
    // Create new query quarser from search term (title only)
		$qp = new ilLuceneQueryParser("title:{$term}*");
		$qp->parse();

    // Init new lucene search (query)
		$searcher = ilLuceneSearcher::getInstance($qp);
		$searcher->setType(ilLuceneSearcher::TYPE_STANDARD);
		$searcher->search();

    // Fecth results...
		$result = $searcher->getResult()->getCandidates();

    // Loop through results and format according to resired return type
		$list  = array();
		$count = 0;
		foreach($result as $objId) {
      // Fetch single ref-id and title
      $refId = reset(ilObject::_getAllReferences($objId));
			$title = ilObject::_lookupTitle($objId, true);

      // Add formated to return value
      array_pust($list, array(
        'value' => $refId,
        'label' => $title,
        'id'    => $objId,
      ));

      // Abort if limit is reached
			if (++$count >= $max)
				return $list;
		}

    // Return formated list of search results
    return $list;
	}


  /**
   * Function: getDBList($term, $max, $types)
   *  Fetches a list of ilias objects matching term (partially) via database queries.
   *
   * @param $term <String> Term to (partially) search for
   * @param $max <Number> Maximum number of results to return
   * @param $types <Array> Arrayof ILIAS types (as returned by ilObject->getType()) to should be included in the search
   *
   * @return <Array> List of search results, with entries 'value' -> ReferenceID, 'label' -> Title, 'id' -> ObjectID for each result
   */
  protected static function getDBList($term, $max, $types) {
    $list  = array();
  	$count = 0;
    foreach ($types as $type) {
      // Fetch (partially) matching roles and add to result items
      $objIds = ilObject::_getIdsForTitle($term, $type, true);
      foreach ($objIds as $objId) {
        // Fetch single ref-id and title
        $refId = reset(ilObject::_getAllReferences($objId));
        $title = ilObject::_lookupTitle($objId);

        // Add formated to return value
        array_push($list, array(
          'value' => $refId,
          'label' => $title,
          'id'    => $roleId,
        ));

        // Abort if limit is reached
        if (++$count >= $max)
          return $list;
      }
    }

    // Return formated list of search results
    return $list;
  }
}
