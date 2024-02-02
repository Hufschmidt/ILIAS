<?php
/**
 * University of Marburg 'Elektronische Kurs/Semesterapparat' (ESA) Plugin for ILIAS
 *
 * @author Thomas Hufschmidt <hufschmidt@hrz.uni-marburg.de>
 **/


/**
 * Class: ilObjUMResaAccess
 *  Manages access checks for ESA-Object
 */
class ilObjUMResaAccess extends ilObjectPluginAccess {
	/**
	 * Function: _checkAccess($cmd, $permission, $refId, $objId, $userId = 0)
	 *  Checks for permission of object giben by refId for provided user.
	 *
	 * @param $cmd <String> Command to check
	 * @param $permission <String> Permission to check
	 * @param $refId <Number> Object to check (by refId)
	 * @param $objId <Number> Object to check (by objId)
	 * @param $userId <Number> User to check permissions for
	 *
	 * @return <Boolean> Access is allowed for given parameters (cmd, perm, user, object)
	 */
	public function _checkAccess(string $cmd, string $permission, int $refId, int $objId, ?int $userId = null): bool {
		global $ilUser, $ilAccess;

		// Fallback to current user if non was given
		if ($userId == 0)
			$userId = $ilUser->getId();

		// Check by permission
		switch ($permission) {
			// Read access
			case 'read':
				if (!$ilAccess->checkAccessOfUser($userId, 'write', '', $refId))
					return false;
				break;
		}

		// All allowed by default
		return true;
	}
}
