<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA-Forms) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/
require_once('Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php');


/**
 * Class: ilUMResaFormPlugin
 *  This class is loaded by ILIAS (for all plugin types) and
 *  (together with its plugin.php) defines the plugin such that
 *  it can be managed by ILIAS.
 */
class ilUMResaFormPlugin extends ilUserInterfaceHookPlugin {
  //
  protected $esaPlugin;


  /**
   * Function: getUMResaPlugin()
   *  Return the sibling UMResa plugin.
   *
   * @return <ilPlugin> A reference to the UMResa plugin
   */
  public function getUMResaPlugin() {
    if ($this->esaPlugin)
      return $this->esaPlugin;
      
    global $DIC;
    $component_factory = $DIC['component.factory'];
    $this->esaPlugin = $component_factory->getPlugin('xesa');
    return $this->esaPlugin;
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
		return 'UMResaForm';
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
    
    $esaPlugin = $this->getUMResaPlugin();

    if (!is_object($esaPlugin)) {
      $tpl->setOnScreenMessage('failure', 'UMResa-Plugin is not available!', true);
      return false;
    }

		return true;
	}


  /**
   * Function: afterUninstall()
   *  This code is run after the plugin was uninstalled
   *  and should make sure that all database entries are
   *  cleaned up.
   * Funny that this is called 'uninstallCustom' for Repository-Object Plugins,
   * consistency be praised...
   */
  protected function afterUninstall(): void {
    $this->includeClass('class.ilUMResaForm.php');
    ilUMResaForm::DBUninstall();
  }
}