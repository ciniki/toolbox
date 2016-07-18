//
// This app will handle the upload/download and duplicate rows in excel spreadsheets
//
function ciniki_toolbox_excel() {
    //
    // Panels
    //
    this.files = null;
    this.upload = null;
    this.download = null;
    this.file = null;
    this.review = null;
    this.matches = null;

    this.cb = null;

    this.init = function() {
        //
        // files panel
        //
        this.files = new M.panel('Excel Files',
            'ciniki_toolbox_excel', 'files',
            'mc', 'medium', 'sectioned', 'ciniki.toolbox.excel.files');
        this.files.sections = {
            '_':{'label':'', 'type':'simplegrid', 'num_cols':1, 
                'headerValues':null,
                'cellClasses':['multiline'],
                }
            };
        this.files.sectionData = function(s) { return this.data; }
        this.files.data = {};

        this.files.listValue = function(s, i, d) { return d.excel.name; }
        this.files.cellValue = function(s, i, j, d) {
            return '<span class="maintext">' + d.excel.name + '</span><span class="subtext">' + d.excel.date_added + '</span>';
        };
        this.files.rowFn = function(s, i, d) { return 'M.ciniki_toolbox_excel.showFile(' + d.excel.id + ');' }
        this.files.noData = function(i) { return 'No excel files found'; }
        this.files.addButton('add', 'Add', 'M.ciniki_toolbox_excel.upload.show(\'M.ciniki_toolbox_excel.showFiles();\');');
        this.files.addClose('Back');

        //
        // The upload form panel
        //
        this.upload = new M.panel('Upload Excel',
            'ciniki_toolbox_excel', 'upload',
            'mc', 'medium', 'sectioned', 'ciniki.toolbox.excel.upload');
        this.upload.data = null;
        this.upload.sections = { 
            'file':{'label':'Upload Excel File', 'fields':{
                'excel':{'label':'', 'type':'image'},
                }}, 
            'pname':{'label':'Name', 'fields':{
                //
                // FIXME: Use the file name as the name, unless they change it
                //
                'name':{'label':'', 'hint':'', 'type':'text'},
                }}, 
            '_save':{'label':'', 'buttons':{
                'save':{'label':'Upload', 'fn':'M.ciniki_toolbox_excel.uploadFile();'},
                }},
            };  
        this.upload.fieldValue = function(s, i, d) { return ''; }
        this.upload.addClose('Cancel');

        //
        // file panel
        //
        this.file = new M.panel('Excel File',
            'ciniki_toolbox_excel', 'file',
            'mc', 'medium', 'sectioned', 'ciniki.toolbox.excel.file');
        this.file.sections = {
            'stats':{'label':'', 'list':{
                'rows':{'label':'Rows'},
                'matches':{'label':'Matches'},
                'reviewed':{'label':'Reviewed'},
                'deleted':{'label':'Deleted'},
                }},
            'actions':{'label':'', 'list':{
                'matches':{'label':'Find matches', 'fn':'M.ciniki_toolbox_excel.findMatches();'},
                'review_autoadv':{'label':'Review matches (Auto advance)', 'fn':'M.ciniki_toolbox_excel.reviewMatches(\'yes\');'},
                'review_noadv':{'label':'Review matches', 'fn':'M.ciniki_toolbox_excel.reviewMatches(\'no\');'},
//              'download':{'label':'Download (XLS)', 'fn':'M.ciniki_toolbox_excel.downloadFile();'},
//              'downloadcsv':{'label':'Download (CSV)', 'fn':'M.ciniki_toolbox_excel.downloadCSVFile(\'\');'},
//              'downloaddeletedcsv':{'label':'Download deleted rows (CSV)', 'fn':'M.ciniki_toolbox_excel.downloadCSVFile(\'only\');'},
                }},
            '_buttons':{'label':'', 'buttons':{
                'downloadxls':{'label':'Download', 'fn':'M.ciniki_toolbox_excel.downloadFile(null,"1,3");'},
                'downloadoriginal':{'label':'Download Original', 'fn':'M.ciniki_toolbox_excel.downloadFile();'},
                'downloaddeleted':{'label':'Download Deleted', 'fn':'M.ciniki_toolbox_excel.downloadFile(2);'},
//              'downloaddeleted':{'label':'Download Deleted', 'fn':'M.ciniki_toolbox_excel.downloadCSVFile(\'only\');'},
                'reset':{'label':'Reset', 'fn':'M.ciniki_toolbox_excel.resetFile();'},
                'delete':{'label':'Delete', 'fn':'M.ciniki_toolbox_excel.deleteFile();'},
                }},
            };
        this.file.excel_id = 0;
        this.file.listValue = function(s, i, d) { return d.label; }
        this.file.listFn = function(s, i, d) { 
            if( d.fn != null ) { 
                return d.fn; 
            } 
            return '';
        };
        this.file.listCount = function(s, i, d) { 
            if( s == 'stats' ) {
                if( this.data[i] != null ) { return '' + this.data[i]; }
                return '0';
            }
            return '';
        };
        
        this.file.noData = function(i) { return 'No excel files found'; }
//      this.file.addButton('reset', 'Reset', 'M.ciniki_toolbox_excel.resetFile();');
//      this.file.addButton('delete', 'Delete', 'M.ciniki_toolbox_excel.deleteFile();');
        this.file.addLeftButton('back', 'Back', 'M.ciniki_toolbox_excel.showFiles();');

        //
        // find matches panel
        //
        this.matches = new M.panel('Find Matches',
            'ciniki_toolbox_excel', 'matches',
            'mc', 'medium', 'sectioned', 'ciniki.toolbox.excel.matches');
        this.matches.data = {};
        this.matches.sections = {
            'columns':{'label':'', 'hidelabel':'yes', 'fields':{}},
            };
        this.matches.fieldValue = function(s, i, d) { return 0; }
        this.matches.addButton('find', 'Find', 'M.ciniki_toolbox_excel.find();');
        this.matches.addLeftButton('back', 'Back', 'M.ciniki_toolbox_excel.file.show();');

        //
        // review matches panel
        //
        this.review = new M.panel('Review Matches',
            'ciniki_toolbox_excel', 'review',
            'mc', 'wide', 'sectioned', 'ciniki.toolbox.excel.review');
        this.review.sections = {
            '_':{'label':'', 'type':'simplegrid', 'num_cols':2, 
                },
            };
        this.review.sectionData = function(s) { return this.data; }
        this.review.data = null;
        this.review.matches = null;
        this.review.rows = null;
        this.review.autoAdvance = 'yes';
        this.review.cellClass = function(s, r, c, d) { 
            if( c == 0 ) { return 'label border'; }
            else if( c > 0 && this.rows != null && this.rows[(c-1)] != null && this.rows[(c-1)].row.cells != null && this.rows[(c-1)].row.cells[1] != null 
                && this.rows[(c-1)].row.cells[1].cell.status == '2' ) { return 'border center excel_deleted'; }
            else if( c > 0 && this.rows != null && this.rows[c-1] != null && this.rows[c-1].row.cells != null && this.rows[c-1].row.cells[1] != null 
                && this.rows[(c-1)].row.cells[1].cell.status == '3' ) { return 'border center excel_keep'; }
            else { return 'border center'; }
        }
        this.review.cellValue = function(s, r, c, d) { 
            if( c == 0 ) { 
                return d.cell.data; 
            } else if( this.rows != null && this.rows[c-1] != null && this.rows[c-1].row != null && this.rows[c-1].row.cells[r] != null ) { 
                // return 'cell ' + r + ',' + c;
                return this.rows[c-1].row.cells[r].cell.data; 
            } else if( r == this.action_row ) {
                if( c == 0 ) {
                    return 'Actions'; 
//              } else if( c == 1 ) {
//                  return "<button onclick=\"M.ciniki_toolbox_excel.deleteMatchesOnRows(" + this.rows[0]['row']['id'] + "," + this.rows[(c-1)]['row']['id'] + ");\">Unique</button>";
                } else if( c > 0 ) {
                    if( this.autoAdvance == 'yes' ) {
                        if( c == 1 ) {
                            return "<button onclick=\"M.ciniki_toolbox_excel.deleteRow(" + this.rows[(c-1)]['row']['id'] + ");\">Delete</button>";
                        } else {
                            return "<button onclick=\"M.ciniki_toolbox_excel.deleteMatchesOnRows(" + this.rows[0]['row']['id'] + "," + this.rows[(c-1)]['row']['id'] + ");\">Unique</button>" + "<button onclick=\"M.ciniki_toolbox_excel.deleteRow(" + this.rows[(c-1)]['row']['id'] + ");\">Delete</button>";
                        }
                    } else {
                        if( this.rows != null && this.rows[c-1] != null && this.rows[c-1]['row']['cells'][0]['cell']['status'] == '2' ) {
                            return "<button onclick=\"M.ciniki_toolbox_excel.keepRow(" + this.rows[c-1]['row']['id'] + ");\">Keep</button>";
                        } else if( this.rows != null && this.rows[c-1] != null && this.rows[c-1]['row']['cells'][0]['cell']['status'] == '3' ) {
                            return "<button onclick=\"M.ciniki_toolbox_excel.deleteRow(" + this.rows[c-1]['row']['id'] + ");\">Delete</button>";
                        } else {
                            return "<button onclick=\"M.ciniki_toolbox_excel.keepRow(" + this.rows[c-1]['row']['id'] + ");\">Keep</button> <button onclick=\"M.ciniki_toolbox_excel.deleteRow(" + this.rows[c-1]['row']['id'] + ");\">Delete</button>";
                        }
                    }
                }
            } else { 
                return '';
            }
        }

        this.review.cellUpdateFn = function(s, r, c, d) {
            if( c > 0 && r < this.action_row ) {
                return M.ciniki_toolbox_excel.updateCell;
            }
            return null;
        }
        this.review.addButton('next', 'Next', 'M.ciniki_toolbox_excel.nextMatch(\'fwd\');');
        this.review.addLeftButton('close', 'Close', 'M.ciniki_toolbox_excel.showFile();');
    }

    //
    // Arguments:
    // aG - The arguments to be parsed into args
    //
    this.start = function(cb, appPrefix, aG) {
        args = {};
        if( aG != null ) {
            args = eval(aG);
        }

        //
        // Create the app container if it doesn't exist, and clear it out
        // if it does exist.
        //
        var appContainer = M.createContainer(appPrefix, 'ciniki_toolbox_excel', 'yes');
        if( appContainer == null ) {
            alert('App Error');
            return false;
        } 

        this.cb = cb;
        // this.files.show(cb);
        this.showFiles(cb);
    }

    //
    // The getFiles function will call the API method ciniki.toolbox.excelGetList
    // which will return the list of excel files that have been uploaded
    //
    this.showFiles = function(cb) {
        var rsp = M.api.getJSONCb('ciniki.toolbox.excelGetList', 
            {'business_id':M.curBusinessID}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_toolbox_excel.files;
                p.data = rsp.files;
                p.refresh();
                p.show(cb);
            });
    }


    //
    // Upload an excel spreadsheet
    //
    this.uploadFile = function() {
        var file = document.getElementById(M.ciniki_toolbox_excel.upload.panelUID + '_excel');
        var name = document.getElementById(M.ciniki_toolbox_excel.upload.panelUID + '_name');
        var rsp = M.api.postJSONFile('ciniki.toolbox.uploadXLS', 
            {'business_id':M.curBusinessID, 'name':name.value}, file.files[0], 
            function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                } 
                if( rsp.id > 0 ) {
                    M.ciniki_toolbox_excel.parseFile(rsp.id, 1);
                } else {
                    M.ciniki_toolbox_excel.showFiles();
                }
            });
        name.value = '';
        file.value = '';
    }

    //
    // Parse uploaded excel spreadsheet
    //
    this.parseFile = function(id, start) {
        var rsp = M.api.getJSONCb('ciniki.toolbox.uploadXLSParse', 
            {'business_id':M.curBusinessID, 'excel_id':id, 'start':start, 'size':1000},
            function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                if( rsp.last_row > 0 && rsp.count >= 1000 ) {
                    M.ciniki_toolbox_excel.parseFile(rsp.id, rsp.last_row+1);
                } else {
                    M.ciniki_toolbox_excel.finishParse(rsp.id);
                }
            });
    }

    this.finishParse = function(id) {
        var rsp = M.api.getJSONCb('ciniki.toolbox.uploadXLSDone', 
            {'business_id':M.curBusinessID, 'excel_id':id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_toolbox_excel.showFiles();
            });
    }
    
    //
    // download an excel spreadsheet
    //
    this.downloadFile = function(s, l) {
        if( s != null && s > 0 ) {
            M.api.openFile('ciniki.toolbox.download2XLS', 
                {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'status':s});
        } else if( l != null && l != '' ) {
            M.api.openFile('ciniki.toolbox.download2XLS', 
                {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'status_list':l});
        } else {
            M.api.openFile('ciniki.toolbox.download2XLS', 
                {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id});
        }
    }

    this.downloadCSVFile = function(deleted) {
        M.api.openFile('ciniki.toolbox.downloadCSV', 
            {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'deleted':deleted});
    }
    
    //
    // Open file
    //
    // arguments:
    // i - the index number of the file to open from the M.ciniki_toolbox_excel.files.data[] array.
    //
    this.showFile = function(fid) {
        //
        // Get file information include statistics
        //
        if( fid != null ) {
            this.file.excel_id = fid;
        }
        var rsp = M.api.getJSONCb('ciniki.toolbox.excelGetStats', 
            {'business_id':M.curBusinessID, 'excel_id':this.file.excel_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_toolbox_excel.file;
                p.data = rsp.stats;
                p.refresh();
                p.show();
            });
    }

    //
    // Display the match for this file open by this.file
    // 
    this.showMatch = function(i) {
        var rsp = M.api.getJSONCb('ciniki.toolbox.excelNextMatch', 
            {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'last_row':i}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_toolbox_excel.review;
                p.data = rsp.matches;
                p.rows = rsp.rows;
                p.refresh();
            });
    }

    //
    // Display the matches panel
    //
    this.findMatches = function() {
        var rsp = M.api.getJSONCb('ciniki.toolbox.excelGetRows', 
            {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'rows':1}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                if( rsp.rows == null || rsp.rows[0] == null || rsp.rows[0].row.columns != null ) {
                    alert("No rows found");
                    return false;
                }
                var p = M.ciniki_toolbox_excel.matches;
                var cells = rsp.rows[0].row.cells;
                p.sections.columns.fields = {};
                for(i in cells) {
                    p.sections.columns.fields[i] = {'label':cells[i].cell.data, 'col':cells[i].cell.col, 'none':'yes', 'type':'toggle', 'toggles':{'1':'include'}};
                }
                p.refresh();
                p.show();
            });
    }

    this.find = function() {
        var fields = M.ciniki_toolbox_excel.matches.sections.columns.fields;
        var c = '';
        var columns = '';
        for(i in fields) {
            if( this.matches.formFieldValue(fields[i], i) == 1 ) {
                columns += c + fields[i].col;
                c = ',';
            }
        }
        var rsp = M.api.getJSONCb('ciniki.toolbox.excelFindMatches', {'business_id':M.curBusinessID, 
            'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'columns':columns, 'match_blank':'no'}, function(rsp) { 
                alert(' Found ' + rsp.matches + ' matches ' + rsp.duplicates + ' duplicate matches'); 
                M.ciniki_toolbox_excel.showFile();
            });
    }

    this.reviewMatches = function(advance) {
        if( advance == 'yes' ) {
            this.review.autoAdvance = 'yes';
            this.review.last_row = 0;
            if( this.review.leftbuttons.prev != null ) {
                delete this.review.leftbuttons.prev;
                delete this.review.leftbuttons.rewind;
            }
            M.ciniki_toolbox_excel.reviewMatchesFinish();
        } else if( advance == 'rewind' ) {
            // Reset the position back to the beginning, if this is a no auto advance review
            var rsp = M.api.getJSONCb('ciniki.toolbox.excelPositionSet', 
                {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'row':0}, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    M.ciniki_toolbox_excel.review.last_row = 0;
                    M.ciniki_toolbox_excel.reviewMatchesFinish();
                });
        } else {
            this.review.addLeftButton('rewind', 'Rewind', 'M.ciniki_toolbox_excel.reviewMatches(\'rewind\');');
            this.review.addButton('prev', 'Prev', 'M.ciniki_toolbox_excel.nextMatch(\'rev\');');

            this.review.autoAdvance = 'no';
            // Get the last position
            var rsp = M.api.getJSONCb('ciniki.toolbox.excelPositionGet', 
                {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id}, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    M.ciniki_toolbox_excel.review.last_row = Number(rsp.cur_review_row) - 1;
                    M.ciniki_toolbox_excel.reviewMatchesFinish();
                });
        }
    }

    this.reviewMatchesFinish = function() {
        // Get the header row
        var rsp = M.api.getJSONCb('ciniki.toolbox.excelGetRows', 
            {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'rows':'1'}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_toolbox_excel.review;
                p.data = rsp.rows[0].row.cells;
                p.action_row = p.data.length;
                p.data[p.data.length] = {'cell':{'data':'Actions'}};

                M.ciniki_toolbox_excel.nextMatch('fwd');
            });
    }

    this.nextMatch = function(direction) {
        var args = {};
        if( this.review.autoAdvance == 'yes' ) {
            args = {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'last_row':M.ciniki_toolbox_excel.review.last_row, 'status':'noreview', 'direction':direction};
        } else {
            args = {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'last_row':M.ciniki_toolbox_excel.review.last_row, 'status':'any', 'direction':direction};
        }
        var rsp = M.api.getJSONCb('ciniki.toolbox.excelNextMatch', args, function(rsp) {
            if( rsp.stat != 'ok' && rsp.err.code == '96' ) {
                if( M.ciniki_toolbox_excel.review.autoAdvance == 'yes' ) {
                    alert('No more matches found');
                    M.ciniki_toolbox_excel.showFile();
                }
                return false;
            } else if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }

            //
            // Set the number of columns for these matches, and include 1 extra for header
            //
            M.ciniki_toolbox_excel.review.sections._.num_cols = rsp.rows.length + 1;
            M.ciniki_toolbox_excel.review.last_row = rsp.rows[0].row.id;

            M.ciniki_toolbox_excel.review.matches = rsp.matches;
            M.ciniki_toolbox_excel.review.rows = rsp.rows;

            // Set the last position
            var rsp = M.api.getJSONCb('ciniki.toolbox.excelPositionSet', {'business_id':M.curBusinessID, 
                'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'row':M.ciniki_toolbox_excel.review.last_row}, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    M.ciniki_toolbox_excel.review.refresh();
                    M.ciniki_toolbox_excel.review.show();
                });
            });
    }

    this.deleteRow = function(row) {
        if( this.review.autoAdvance == 'yes' ) {
            var rsp = M.api.getJSONCb('ciniki.toolbox.excelDeleteMatchRow', {'business_id':M.curBusinessID, 
                'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'row':row}, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    M.ciniki_toolbox_excel.review.last_row--;
                    M.ciniki_toolbox_excel.nextMatch('fwd');
                });
        } else {
            var rsp = M.api.getJSONCb('ciniki.toolbox.excelSetRowStatus', {'business_id':M.curBusinessID, 
                'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'row':row, 'status':'delete'}, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    M.ciniki_toolbox_excel.review.last_row--;
                    M.ciniki_toolbox_excel.nextMatch('fwd');
                });
        }
    }

    this.keepRow = function(row) {
        var rsp = M.api.getJSONCb('ciniki.toolbox.excelSetRowStatus', {'business_id':M.curBusinessID, 
            'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'row':row, 'status':'keep'}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_toolbox_excel.review.last_row--;
                M.ciniki_toolbox_excel.nextMatch('fwd');
            });
    }

    this.deleteMatchesOnRows = function(row1, row2) {
        var rsp = M.api.getJSONCb('ciniki.toolbox.excelDeleteMatchesOnRows', {'business_id':M.curBusinessID, 
            'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'row1':row1, 'row2':row2}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_toolbox_excel.review.last_row--;
                M.ciniki_toolbox_excel.nextMatch('fwd');
            });
    }

    this.updateCell = function(s, r, c, d) {
        var rsp = M.api.getJSONCb('ciniki.toolbox.excelUpdateCell', {'business_id':M.curBusinessID, 
            'excel_id':M.ciniki_toolbox_excel.file.excel_id, 'row':M.ciniki_toolbox_excel.review.rows[(c-1)].row.id, 
            'col':M.ciniki_toolbox_excel.review.rows[(c-1)].row.cells[r].cell.col, 'data':encodeURIComponent(d)}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_toolbox_excel.review.last_row--;
                M.ciniki_toolbox_excel.nextMatch('fwd');
            });
    }

    //
    // Remove the file from the database
    //
    this.resetFile = function() {
        if( confirm("Are you sure you want to reset this file?") == true ) {
            var rsp = M.api.getJSONCb('ciniki.toolbox.excelReset', 
                {'business_id':M.curBusinessID, 'excel_id':M.ciniki_toolbox_excel.file.excel_id}, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    M.ciniki_toolbox_excel.showFile();
                });
        } else {
            M.ciniki_toolbox_excel.showFile();
        }
    }

    //
    // Remove the file from the database
    //
    this.deleteFile = function() {
        if( confirm("Are you sure you want to delete this file?") == true ) {
            var rsp = M.api.getJSONCb('ciniki.toolbox.excelDelete', {'business_id':M.curBusinessID, 
                'excel_id':M.ciniki_toolbox_excel.file.excel_id}, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    M.ciniki_toolbox_excel.showFiles();
                });
        }
    }
}
