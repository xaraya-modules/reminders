<?php
/**
 * Reminders Module
 *
 * @package modules
 * @subpackage reminders
 * @category Third Party Xaraya Module
 * @version 1.0.0
 * @copyright (C) 2019 Luetolf-Carroll GmbH
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @author Marc Lutolf <marc@luetolf-carroll.com>
 */
/**
 * Suppress the reminders of an entry
 *
 */
function reminders_user_remove()
{
    if (!xarSecurityCheck('ReadReminders')) return;

    if (!xarVarFetch('code',    'str',      $data['code'],       '', XARVAR_NOT_REQUIRED)) return;
    if (!xarVarFetch('confirm', 'checkbox', $data['confirm'],    false, XARVAR_NOT_REQUIRED)) return;

    sys::import('modules.dynamicdata.class.objects.master');
    $entries = DataObjectMaster::getObjectList(array('name' => 'reminders_entries'));
        
    // Set all the relevant properties active here
    foreach($entries->properties as $name => $property) {
        if ($property->getDisplayStatus() == DataPropertyMaster::DD_DISPLAYSTATE_DISABLED) continue;
        $entries->properties[$name]->setDisplayStatus(DataPropertyMaster::DD_DISPLAYSTATE_ACTIVE);
    }
    $entries->setFieldList();

    $q = $entries->dataquery;
    
    // Only active reminders
    $q->eq('entries.state', 3);
    // The reminder corresponding to this code
    $q->eq('entries.code', $data['code']);

    $items = $entries->getItems();
    $data['item'] = reset($items);
    
    // If we have an active reminder, pass the ID. If not, remove the code
    if (!empty($data['item'])) {
	    $data['itemid'] = $data['item']['id'];
	} else {
    	$data['code'] = '';
    }

    $email_dates = xarMod::apiFunc('reminders', 'user', 'get_email_dates', array('array' => $data['item']));
	$data['remaining'] = count($email_dates);
	
    $data['authid'] = xarSecGenAuthKey('reminders');

    if ($data['confirm']) {
    	if (!xarVarFetch('itemid'           , 'int',    $itemid ,    0,    XARVAR_NOT_REQUIRED)) return;
    	if (!xarVarFetch('remove_recurring' , 'int',    $recurring , 0,    XARVAR_NOT_REQUIRED)) return;
    	
    	// To remove this reminder we set it inactive
    	$tables = xarDB::getTables();
    	$q = new Query('UPDATE', $tables['reminders_entries']);
    	$q->addfield('state', 2);
    	$q->eq('id', $itemid);
//    	$q->qecho(); exit;
    	$q->run();
    	
    	// If we keep recurring reminders, then we need to spawn a new reminder from this one
    	if ($recurring == 1) {
    		$entry = DataObjectMaster::getObject(array('name' => 'reminders_entries'));
    		$item = $entry->getItem(array('itemid' => $itemid));
    		$spawned = xarMod::apiFunc('reminders', 'admin', 'spawn', array('object' => $entry));
    		if (!$spawned) {
        		return xarTpl::module('reminders','user','errors',array('layout' => 'not_spawned'));
    		}
    	}
    	
    	// Update flags to give the proper message
    	$data['code'] = '';
    	$data['removed'] = 1;
    }

    $data['debugmode'] = xarModVars::get('reminders', 'debugmode');
	xarTpl::setPageTemplateName('user_full');
	return $data;
}
?>
