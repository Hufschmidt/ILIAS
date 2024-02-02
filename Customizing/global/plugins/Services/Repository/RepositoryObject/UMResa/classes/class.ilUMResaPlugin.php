<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/


/**
 * Class: ilUMResaPlugin
 *  Responsible for registering the new repository object
 *  plugin in ILIAS, as well as handling activation and uninstall logic.
 */
class ilUMResaPlugin extends ilRepositoryObjectPlugin {
  //
  protected $esaFormPlugin;


  /**
   * Function: getUMResaFormPlugin()
   *  Return the sibling UMResaForm plugin.
   *
   * @return <ilPlugin> A reference to the UMResaForm plugin
   */
  public function getUMResaFormPlugin() {

    if ($this->esaFormPlugin)
      return $this->esaFormPlugin;

    global $DIC;
    $component_factory = $DIC['component.factory'];
    $this->esaFormPlugin = $component_factory->getPlugin('xesaform');
    return $this->esaFormPlugin;
  }


  /**
   * Function: getPluginName()
   *  Returns the name of the Plugin. This must match with
   *  the directory-name of the plugin or else ILIAS fails
   *  to parse its configuration...
   *
   * @returns <String> Name of plugin, matching its directory-name
   */
  public function getPluginName(): string {
    return 'UMResa';
  }


  /**
   * Function: beforeActivation()
   *  This code is run before the plugin gets activated
   *  in ILIAS and can stop its activation by returning
   *  false (or throwing an exception).
   *
   * @returns <Boolean> False if activation should fail, true otherwise
   */
  protected function beforeActivation(): bool {
     global $tpl;
    
    $esaFormPlugin = $this->getUMResaFormPlugin();

    if (!is_object($esaFormPlugin)) {
      $tpl->setOnScreenMessage('failure', 'UMResaForm-Plugin is not available!', true);
      return false;
    }

		return true;
  }


  /**
   * Function: uninstallCustom()
   *  This code is run after the plugin was uninstalled
   *  and should make sure that all database entries are
   *  cleaned up.
   * Funny that this is called 'afterUninstall' for UIHook Plugins,
   * consistency be praised...
   */
  protected function uninstallCustom(): void {
	$this->includeClass('class.ilObjUMResa.php');
    ilObjUMResa::DBUninstall();
  }
}
