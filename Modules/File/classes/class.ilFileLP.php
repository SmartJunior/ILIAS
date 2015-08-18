<?php
// cognos-blu-patch: begin
/* Copyright (c) 1998-2015 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Services/Object/classes/class.ilObjectLP.php';

/**
 * File to lp connector
 * @author Michael Jansen <mjansen@databay.de>
 * @package ModulesFile
 */
class ilFileLP extends ilObjectLP
{
	/**
	 * @return int
	 */
	public function getDefaultMode()
	{
		return ilLPObjSettings::LP_MODE_DOWNLOADED;
	}

	public function getValidModes()
	{
		return array(
			ilLPObjSettings::LP_MODE_DEACTIVATED,
			ilLPObjSettings::LP_MODE_DOWNLOADED
		);
	}
}
// cognos-blu-patch: end