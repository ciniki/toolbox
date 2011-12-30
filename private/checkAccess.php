<?php
//
// Description
// -----------
// This function will check the user has access to the toolbox,
// the module is turned on, and they have access to the requested
// excel file.
//
// Info
// ----
// Status: 			defined
//
// Arguments
// ---------
// ciniki:
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_toolbox_checkAccess($ciniki, $business_id, $method, $excel_id) {
	//
	// Check if the module is enabled for this business, don't really care about the ruleset
	//
	$strsql = "SELECT ruleset FROM ciniki_businesses, ciniki_business_modules "
		. "WHERE ciniki_businesses.id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND ciniki_businesses.status = 1 "														// Business is active
		. "AND ciniki_businesses.id = ciniki_business_modules.business_id "
		. "AND ciniki_business_modules.package = 'ciniki' "
		. "AND ciniki_business_modules.module = 'toolbox' "
		. "";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'businesses', 'module');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'65', 'msg'=>'Access denied', 'err'=>$rc['err']));
	}

	//
	// Check the user has permission to the business, 
	// owners have full permissions, as do sysadmins
	//
	// Check if user is superuser
	if( ($ciniki['session']['user']['perms'] & 0x01) == 0x01 ) {
		return array('stat'=>'ok');
	}

	//
	// Check the authenticated user is the business owner
	// 
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	$strsql = "SELECT business_id, user_id FROM ciniki_business_users "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
		. "AND package = 'ciniki' "
		. "AND (permission_group = 'owners' OR permission_group = 'employees') "
		. "";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbRspQuery.php');
	$rc = ciniki_core_dbRspQuery($ciniki, $strsql, 'businesses', 'perms', 'perm', array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'59', 'msg'=>'Access denied')));
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'63', 'msg'=>'Access denied', 'err'=>$rc['err']));
	}
	if( $rc['num_rows'] <= 0 ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'64', 'msg'=>'Access denied'));
	}

	//
	// Check if an excel file is specified, that the file is attached
	// to the requested business_id
	//
	if( $excel_id > 0 ) {
		$strsql = "SELECT id, business_id FROM ciniki_toolbox_excel "
			. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. "AND id = '" . ciniki_core_dbQuote($ciniki, $excel_id) . "' ";
		$rc = ciniki_core_dbRspQuery($ciniki, $strsql, 'toolbox', 'files', 'file', array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'61', 'msg'=>'Access denied')));
		if( $rc['stat'] != 'ok' ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'67', 'msg'=>'Access denied', 'err'=>$rc['err']));
		}
		//
		// Check that the file returned has the same credientials, and that only 
		// 1 file was returned.
		//
		if( $rc['num_rows'] != 1 
			&& $rc['files'][0]['file']['business_id'] == $business_id
			&& $rc['files'][0]['file']['id'] == $excel_id ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'60', 'msg'=>'Access denied'));
		}
	}
	
	//
	// All check cleared, grant access
	//
	return array('stat'=>'ok');
}
?>
