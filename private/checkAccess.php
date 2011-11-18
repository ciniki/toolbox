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
	// Check the user is authenticated
	//
	if( !isset($ciniki['session'])
		|| !isset($ciniki['session']['user'])
		|| !isset($ciniki['session']['user']['id'])
		|| $ciniki['session']['user']['id'] < 1 ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'58', 'msg'=>'User not authenticated'));
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
		. "AND type = 1 "		// This is a business owner
		. "";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbRspQuery.php');
	$rsp = ciniki_core_dbRspQuery($ciniki, $strsql, 'businesses', 'perms', 'perm', array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'59', 'msg'=>'Access denied')));
	if( $rsp['stat'] != 'ok' ) {
		return $rsp;
	}
	if( $rsp['num_rows'] == 1 
		&& $rsp['perms'][0]['perm']['business_id'] == $business_id
		&& $rsp['perms'][0]['perm']['user_id'] == $ciniki['session']['user']['id'] ) {
		return array('stat'=>'ok');
	}

	//
	// Check if an excel file is specified, that the file is attached
	// to the requested business_id
	//
	if( $excel_id > 0 ) {
		$strsql = "SELECT id, business_id FROM ciniki_toolbox_excel "
			. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. "AND id = '" . ciniki_core_dbQuote($ciniki, $excel_id) . "' ";
		$rsp = ciniki_core_dbRspQuery($ciniki, $strsql, 'toolbox', 'files', 'file', array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'61', 'msg'=>'Access denied')));
		if( $rsp['stat'] != 'ok' ) {
			return $rsp;
		}
		//
		// Check that the file returned has the same credientials, and that only 
		// 1 file was returned.
		//
		if( $rsp['num_rows'] == 1 
			&& $rsp['files'][0]['file']['business_id'] == $business_id
			&& $rsp['files'][0]['file']['id'] == $excel_id ) {
			return array('stat'=>'ok');
		}
	}

	//
	// By default fail
	//
	return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'60', 'msg'=>'Access denied'));
}
?>
