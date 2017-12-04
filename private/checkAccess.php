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
// Status:          defined
//
// Arguments
// ---------
// ciniki:
//
// Returns
// -------
// <rsp stat='ok' />
//
function ciniki_toolbox_checkAccess($ciniki, $tnid, $method, $excel_id) {
    //
    // Check if the tenant is active and the module is enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'checkModuleAccess');
    $rc = ciniki_tenants_checkModuleAccess($ciniki, $tnid, 'ciniki', 'toolbox');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( !isset($rc['ruleset']) ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.4', 'msg'=>'No permissions granted'));
    }
    $modules = $rc['modules'];

    //
    // Check the user has permission to the tenant, 
    // owners have full permissions, as do sysadmins
    //
    // Check if user is superuser
    if( ($ciniki['session']['user']['perms'] & 0x01) == 0x01 ) {
        return array('stat'=>'ok');
    }

    //
    // Check the authenticated user is the tenant owner
    // 
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
    $strsql = "SELECT tnid, user_id FROM ciniki_tenant_users "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "AND user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
        . "AND package = 'ciniki' "
        . "AND status = 10 "
        . "AND (permission_group = 'owners' OR permission_group = 'employees' OR permission_group = 'resellers' ) "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbRspQuery');
    $rc = ciniki_core_dbRspQuery($ciniki, $strsql, 'ciniki.tenants', 'perms', 'perm', array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.5', 'msg'=>'Access denied')));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.6', 'msg'=>'Access denied', 'err'=>$rc['err']));
    }
    if( $rc['num_rows'] <= 0 ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.7', 'msg'=>'Access denied'));
    }

    //
    // Check if an excel file is specified, that the file is attached
    // to the requested tnid
    //
    if( $excel_id > 0 ) {
        $strsql = "SELECT id, tnid FROM ciniki_toolbox_excel "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND id = '" . ciniki_core_dbQuote($ciniki, $excel_id) . "' ";
        $rc = ciniki_core_dbRspQuery($ciniki, $strsql, 'ciniki.toolbox', 'files', 'file', array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.8', 'msg'=>'Access denied')));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.9', 'msg'=>'Access denied', 'err'=>$rc['err']));
        }
        //
        // Check that the file returned has the same credientials, and that only 
        // 1 file was returned.
        //
        if( $rc['num_rows'] != 1 
            && $rc['files'][0]['file']['tnid'] == $tnid
            && $rc['files'][0]['file']['id'] == $excel_id ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.toolbox.10', 'msg'=>'Access denied'));
        }
    }
    
    //
    // All check cleared, grant access
    //
    return array('stat'=>'ok');
}
?>
