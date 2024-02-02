<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA-Forms) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/
require_once('Services/UIComponent/classes/class.ilUIHookPluginGUI.php');


/**
 * Class: ilUMResaFormUIHookGUI
 *  This class is loaded by ILIAS (for UserInterfaceHook-Plugins)
 *  and hooks into the goto.php execution to enable shortcut(s).
 *
 * @ingroup ServicesUIComponent
 */
class ilUMResaFormUIHookGUI extends ilUIHookPluginGUI {
	/**
	 * Function: gotoHook()
	 *  Called pretty early by goto.php, without any parameters.
	 *  Enables shortcut 'goto.php?target=xesaform'
	 */
	public function gotoHook(): void {
		// Watch when target matches plugin id (should match plugin.php)
		if ($_GET['target'] === 'xesaform') {
            global $ilCtrl;

			// Use ilUIPluginRouterGUI to display the custom GUI-Class ilUMResaFormGUI
			//$ilCtrl->initBaseClass('ilUIPluginRouterGUI');
			$ilCtrl->setTargetScript('ilias.php');
			$ilCtrl->redirectByClass(array('ilUIPluginRouterGUI', 'ilUMResaFormGUI'));
		}
	}
}