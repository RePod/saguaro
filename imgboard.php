<?php
session_start();
/*
=================================
===Saguaro Imageboard Software===
=================================
>>1.0
http://saguaroimgboard.tk/download/
the above link will have the latest version.

This is a branch off of futallaby and is currently in development because 
I felt like doing this and have always prefered imageboards to phpbb clones.

Special thanks to !KNs1o0VDv6, Glas, Anonymous from vchan, RePod, and anyone who actually uses this.
If you need help you can reach me at spoot@saguaroimgboard.tk
or http://saguaroimgboard.tk/sug/
or if you would like to help development and have php experience.
If you need help setting saguaro up, check http://saguaroimgboard.tk/suprt/
Remember to look through older threads and see if your problem wasn't solved already!

*/
include "config.php";
include "lang/language.php"; //Language file.

if ( BOARD_DIR == 'test' ) {
    ini_set( 'display_errors', 1 );
} else {
    ini_set( 'display_errors', 0 );
}

$host = $_SERVER['REMOTE_ADDR'];

extract( $_POST );
extract( $_GET );
extract( $_COOKIE );

$upfile_name = $_FILES["upfile"]["name"];
$upfile      = $_FILES["upfile"]["tmp_name"];

$path = realpath( "./" ) . '/' . IMG_DIR;
ignore_user_abort( TRUE );
$badstring = array(
     "nimp.org" 
); // Refused text
$badfile   = array(
     "dummy",
    "dummy2" 
); //Refused files (md5 hashes)


function mysql_call( $query ) {
    $ret = mysql_query( $query );
    if ( !$ret ) {
	if ( DEBUG_MODE ) {
	        echo "Error on query: " . $query . "<br />";
	        echo mysql_error() . "<br />";
    	} else {
	        echo "MySQL error!<br />";
    	}
    }
    return $ret;
}

//check for SQL table existance


$con  = mysql_connect( SQLHOST, SQLUSER, SQLPASS );

if ( !$con ) {
    echo S_SQLCONF; //unable to connect to DB (wrong user/pass?)
    exit;
}

$db_id = mysql_select_db( SQLDB, $con );
if ( !$db_id ) {
    echo S_SQLDBSF;
}

function rebuildqueue_create_table() {
    $sql = <<<EOSQL
CREATE TABLE `rebuildqueue` (
  `board` char(4) NOT NULL,
  `no` int(11) NOT NULL,
  `ownedby` int(11) NOT NULL default '0',
  `ts` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`board`,`no`,`ownedby`)
)
EOSQL;
    mysql_call( $sql );
}

function rebuildqueue_add( $no ) {
    $board = BOARD_DIR;
    $no    = (int) $no;
    for ( $i = 0; $i < 2; $i++ )
        if ( !mysql_call( "INSERT IGNORE INTO rebuildqueue (board,no) VALUES ('$board','$no')" ) )
            rebuildqueue_create_table();
        else
            break;
}

function rebuildqueue_remove( $no ) {
    $board = BOARD_DIR;
    $no    = (int) $no;
    for ( $i = 0; $i < 2; $i++ )
        if ( !mysql_call( "DELETE FROM rebuildqueue WHERE board='$board' AND no='$no'" ) )
            rebuildqueue_create_table();
        else
            break;
}

function rebuildqueue_take_all() {
    $board = BOARD_DIR;
    $uid   = mt_rand( 1, mt_getrandmax() );
    for ( $i = 0; $i < 2; $i++ )
        if ( !mysql_call( "UPDATE rebuildqueue SET ownedby=$uid,ts=ts WHERE board='$board' AND ownedby=0" ) )
            rebuildqueue_create_table();
        else
            break;
    $q     = mysql_call( "SELECT no FROM rebuildqueue WHERE board='$board' AND ownedby=$uid" );
    $posts = array();
    while ( $post = mysql_fetch_assoc( $q ) )
        $posts[] = $post['no'];
    return $posts;
}

//Log
require_once(CORE_DIR . "/log/log.php");
$my_log = new Log;
function updatelog($resno = 0, $rebuild = 0) {
    global $my_log;

    $my_log->update($resno, $rebuild);
}
function log_cache($invalidate = 0) {
    global $my_log;

    $my_log->update_cache();
    $log = $my_log->cache;
}

// truncate $str to $max_lines lines and return $str and $abbr
// where $abbr = whether or not $str was actually truncated
function abbreviate( $str, $max_lines ) {
    if ( !defined( 'MAX_LINES_SHOWN' ) ) {
        if ( defined( 'BR_CHECK' ) ) {
            define( 'MAX_LINES_SHOWN', BR_CHECK );
        } else {
            define( 'MAX_LINES_SHOWN', 20 );
        }
        $max_lines = MAX_LINES_SHOWN;
    }
    $lines = explode( "<br />", $str );
    if ( count( $lines ) > $max_lines ) {
        $abbr  = 1;
        $lines = array_slice( $lines, 0, $max_lines );
        $str   = implode( "<br />", $lines );
    } else {
        $abbr = 0;
    }
    
    //close spans after abbreviating
    //XXX will not work with more html - use abbreviate_html from shiichan
    $str .= str_repeat( "</span>", substr_count( $str, "<span" ) - substr_count( $str, "</span" ) );
    
    return array(
         $str,
        $abbr 
    );
}

// print $contents to $filename by using a temporary file and renaming it 
// (makes *.html and *.gz if USE_GZIP is on)
function print_page( $filename, $contents, $force_nogzip = 0 ) {
    $gzip     = ( USE_GZIP == 1 && !$force_nogzip );
    $tempfile = tempnam( realpath( RES_DIR ), "tmp" ); //note: THIS actually creates the file
    file_put_contents( $tempfile, $contents, FILE_APPEND );
    rename( $tempfile, $filename );
    chmod( $filename, 0664 ); //it was created 0600
    
    if ( $gzip ) {
        $tempgz = tempnam( realpath( RES_DIR ), "tmp" ); //note: THIS actually creates the file
        $gzfp   = gzopen( $tempgz, "w" );
        gzwrite( $gzfp, $contents );
        gzclose( $gzfp );
        rename( $tempgz, $filename . '.gz' );
        chmod( $filename . '.gz', 0664 ); //it was created 0600
    }
}



// check whether the current user can perform $action (on $no, for some actions)
// board-level access is cached in $valid_cache.
function valid( $action = 'moderator', $no = 0 ) {
	require_once("_core/admin/validate.php");
	
	$validate = new Validation;
	$allowed = $validate->verify( $action );
	return $allowed;
}

/* head */
function head( &$dat ) {
    $titlepart = '';
    if ( SHOWTITLEIMG == 1 ) {
        $titlepart .= '<img src="' . TITLEIMG . '" alt="' . TITLE . '" />';
        if ( SHOWTITLETXT == 1 ) {
            $titlepart .= '<br />';
        }
    } else if ( SHOWTITLEIMG == 2 ) {
        $titlepart .= '<img src="' . TITLEIMG . '" onclick="this.src=this.src;" alt="' . TITLE . '" />';
        if ( SHOWTITLETXT == 1 ) {
            $titlepart .= '<br />';
        }
    }
    if ( SHOWTITLETXT == 1 ) {
        $titlepart .= '' . TITLE . '';
    } elseif ( SHOWTITLETXT == 2 ) {
        $titlepart .= '/' . BOARD_DIR . '/ - ' . TITLE . '';
    }
    /* begin page content */
    $dat .= "
<!DOCTYPE html><head>
<meta name='description' content='" . S_DESCR . "'/></meta>
<meta http-equiv='content-type'  content='text/html;charset=utf-8' /></meta>
<meta name='viewport' content='width=device-width, initial-scale=1'></meta>
<meta http-equiv='pragma' content='no-cache'></meta>
<link href='" . CSS_PATH . "favicon.ico'>
<title>" . $titlepart . "</title>
<link rel='stylesheet' type='text/css' href='" . CSS_PATH . CSS1 . "' title='Saguaba' />
<link rel='alternate stylesheet' type='text/css' media='screen'  href='" . CSS_PATH . CSS2 . "' title='Sagurichan'/>
<link rel='alternate stylesheet' type='text/css' media='screen'  href='" . CSS_PATH . CSS3 . "' title='Tomorrow' />
<link rel='alternate stylesheet' type='text/css' media='screen'  href='" . CSS_PATH . CSS4 . "' title='Burichan'/>
<script src='" . JS_PATH . "/jquery.min.js' type='text/javascript'></script>
<script src='" . JS_PATH . "/styleswitch.js' type='text/javascript'></script>
<script src='" . JS_PATH . "/main.js' type='text/javascript'></script>";
    
    if ( USE_JS_SETTINGS )
        $dat .= '<script src="' . JS_PATH . '/suite_settings.js" type="text/javascript"></script>';
    if ( USE_IMG_HOVER )
        $dat .= '<script src="' . JS_PATH . '/image_hover.js" type="text/javascript"></script>';
    if ( USE_IMG_TOOLBAR )
        $dat .= '<script src="' . JS_PATH . '/image_toolbar.js" type="text/javascript"></script>';
    if ( USE_IMG_EXP )
        $dat .= '<script src="' . JS_PATH . '/image_expansion.js" type="text/javascript"></script>';
    if ( USE_UTIL_QUOTE )
        $dat .= '<script src="' . JS_PATH . '/utility_quotes.js" type="text/javascript"></script>';
    if ( USE_INF_SCROLL )
        $dat .= '<script src="' . JS_PATH . '/infinite_scroll.js" type="text/javascript"></script>';
    if ( USE_FORCE_WRAP )
        $dat .= '<script src="' . JS_PATH . '/force_post_wrap.js" type="text/javascript"></script>';
    if ( USE_UPDATER )
        $dat .= '<script src="' . JS_PATH . '/thread_updater.js" type="text/javascript"></script>';
    if ( USE_THREAD_STATS )
        $dat .= '<script src="' . JS_PATH . '/thread_stats.js" type="text/javascript"></script>';
    if ( REPOD_EXTRA )
        $dat .= '<script src="' . JS_PATH . '/extra/bgmod.js" type="text/javascript"></script>';
    if ( USE_EXTRAS ) {
        foreach ( glob( JS_PATH . "/extra/*.js" ) as $path ) {
            $dat .= '<script src="' . $path . '" type="text/javascript"></script>';
        }
        unset( $path );
    }
    
    $dat .= '
' . EXTRA_SHIT . '
</head>
<body>
 ' . $titlebar . '
<span class="boardlist">' . ((file_exists(BOARDLIST)) ? file_get_contents(BOARDLIST) : ''). ' </span>
<span class="adminbar">
[<a href="' . HOME . '" target="_top">' . S_HOME . '</a>]
[<a href="' . PHP_ASELF_ABS . '">' . S_ADMIN . '</a>]
</span>
<div class="logo">' . $titlepart . '</div>
<a href="#top" /></a>
<div class="headsub">' . S_HEADSUB . '</div><hr />';
    if ( USE_ADS1 ) {
        $dat .= '' . ADS1 . '<hr />';
    }
    
    return $dat;
}

/* Contribution form */
function form( &$dat, $resno, $admin = "" ) {
    require_once(CORE_DIR . "/postform.php");
    
    $postform = new PostForm;
    $dat .= $postform->format($resno, $admin);
}

/* Footer */
function foot( &$dat ) {
    if (file_exists(BOARDLIST))
        $dat .= '<span class="boardlist">' . file_get_contents( BOARDLIST ) . '</span>';

    $dat .= '<div class="footer">' . S_FOOT . '</div><a href="#bottom" /></a></body></html>';
}


function error( $mes, $dest = '' ) {
    global $upfile_name, $path;
    if ( is_file( $dest ) )
        unlink( $dest );
    $dat .= head();
    echo $dat;
    if ( $mes == S_BADHOST ) {
        die( "<html><head><meta http-equiv=\"refresh\" content=\"0; url=banned.php\"></head></html>" );
    } else {
        echo "<br /><br /><hr size=1><br /><br />
		   <center><font color=blue size=5>$mes<br /><br /><a href=" . PHP_SELF2_ABS . ">" . S_RELOAD . "</a></b></font></center>
		   <br /><br /><hr size=1>";
        die( "</body></html>" );
    }
}
/* Auto Linker */
function normalize_link_cb( $m ) {
    $subdomain = $m[1];
    $original  = $m[0];
    $board     = strtolower( $m[2] );
    $m[0]      = $m[1] = $m[2] = '';
    for ( $i = count( $m ) - 1; $i > 2; $i-- ) {
        if ( $m[$i] ) {
            $no = $m[$i];
            break;
        }
    }
    if ( $subdomain == 'www' || $subdomain == 'static' || $subdomain == 'content' )
        return $original;
    if ( $board == BOARD_DIR )
        return "&gt;&gt;$no";
    else
        return "&gt;&gt;&gt;/$board/$no";
}
function normalize_links( $proto ) {
    // change http://xxx.[[site]/board/res/no links into plaintext >># or >>>/board/#
    if ( strpos( $proto, SITE_ROOT ) === FALSE )
        return $proto;
    
    $proto = preg_replace_callback( '@http://([A-za-z]*)[.]' . SITE_ROOT . '[.]' . SITE_SUFFIX . '/(\w+)/(?:res/(\d+)[.]html(?:#q?(\d+))?|\w+.php[?]res=(\d+)(?:#(\d+))?|)(?=[\s.<!?,]|$)@i', 'normalize_link_cb', $proto );
    // rs.[site].info to >>>rs/query+string
    $proto = preg_replace( '@http://rs[.]' . SITE_ROOT . '[.]' . SITE_SUFFIX . '/\?s=([a-zA-Z0-9$_.+-]+)@i', '&gt;&gt;&gt;/rs/$1', $proto );
    return $proto;
}

function intraboard_link_cb( $m ) {
    global $intraboard_cb_resno, $log;
    $no    = $m[1];
    $resno = $intraboard_cb_resno;
    if ( isset( $log[$no] ) ) {
        $resto  = $log[$no]['resto'];
        $resdir = ( $resno ? '' : RES_DIR );
        $ext    = PHP_EXT;
        if ( $resno && $resno == $resto ) // linking to a reply in the same thread
            return "<a href=\"#$no\" class=\"quotelink\" onClick=\"replyhl('$no');\">&gt;&gt;$no</a>";
        elseif ( $resto == 0 ) // linking to a thread
            return "<a href=\"$resdir$no$ext#$no\" class=\"quotelink\">&gt;&gt;$no</a>";
        else // linking to a reply in another thread
            return "<a href=\"$resdir$resto$ext#$no\" class=\"quotelink\">&gt;&gt;$no</a>";
    }
    return $m[0];
}
function intraboard_links( $proto, $resno ) {
    global $intraboard_cb_resno;
    
    $intraboard_cb_resno = $resno;
    
    $proto = preg_replace_callback( '/&gt;&gt;([0-9]+)/', 'intraboard_link_cb', $proto );
    return $proto;
}

function interboard_link_cb( $m ) {
    // on one hand, we can link to imgboard.php, using any old subdomain, 
    // and let apache & imgboard.php handle it when they click on the link
    // on the other hand, we can use the database to fetch the proper subdomain
    // and even the resto to construct a proper link to the html file (and whether it exists or not)
    
    // for now, we'll assume there's more interboard links posted than interboard links visited.
    $url = DATA_SERVER . $m[1] . '/' . PHP_SELF . ( $m[2] ? ( '?res=' . $m[2] ) : "" );
    return "<a href=\"$url\" class=\"quotelink\">{$m[0]}</a>";
}
function interboard_rs_link_cb( $m ) {
    // $m[1] might be a url-encoded query string, or might be manual-typed text
    // so we'll normalize it to raw text first and then re-encode it
    $lsearchquery = urlencode( urldecode( $m[1] ) );
    return "<a href=\"http://rs." . SITE_ROOT . "./?s=$lsearchquery\" class=\"quotelink\">{$m[0]}</a>";
}

function interboard_links( $proto ) {
    $boards = "an?|cm?|fa|fit|gif|h[cr]?|[bdefgkmnoprstuvxy]|wg?|ic?|y|cgl|c[ko]|mu|po|t[gv]|toy|test2|trv|jp|r9k|sp";
    $proto  = preg_replace_callback( '@&gt;&gt;&gt;/(' . $boards . ')/([0-9]*)@i', 'interboard_link_cb', $proto );
    $proto  = preg_replace_callback( '@&gt;&gt;&gt;/rs/([^\s<>]+)@', 'interboard_rs_link_cb', $proto );
    return $proto;
}

function auto_link( $proto, $resno ) {
    $proto = normalize_links( $proto );
    
    // auto-link remaining URLs if they're not part of HTML
    if ( strpos( $proto, SITE_ROOT ) !== FALSE ) {
        $proto = preg_replace( '/(http:\/\/(?:[A-Za-z]*\.)?)(' . SITE_ROOT . ')(\'' . SITE_SUFFIX . ')(\/)([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?/i', "<a href=\"\\0\" target=\"_blank\">\\0</a>", $proto );
        $proto = preg_replace( '/([<][^>]*?)<a href="((http:\/\/(?:[A-Za-z]*\.)?)(' . SITE_ROOT . ')(\'' . SITE_SUFFIX . ')(\/)([\w\-\.,@?^=%&:\/~\+#]*[\w\-\@?^=%&\/~\+#])?)" target="_blank">\\2<\/a>([^<]*?[>])/i', '\\1\\3\\4\\5\\6\\7\\8', $proto );
    }
    
    $proto = intraboard_links( $proto, $resno );
    $proto = interboard_links( $proto );
    return $proto;
}

/* Regist */
function regist( $name, $email, $sub, $com, $url, $pwd, $upfile, $upfile_name, $resto ) {
    require_once(CORE_DIR . "/regist/regist.php");
}

function proxy_connect( $port ) { /*A copy of this exists in the function hell,
it's good to be straight up deleted when it is removed from regist*/
    $fp = @fsockopen( $_SERVER["REMOTE_ADDR"], $port, $a, $b, 2 );
    if ( !$fp ) {
        return 0;
    } else {
        return 1;
    }
}

// deletes a post from the database
// imgonly: whether to just delete the file or to delete from the database as well
// automatic: always delete regardless of password/admin (for self-pruning)
// children: whether to delete just the parent post of a thread or also delete the children
// die: whether to die on error
// careful, setting children to 0 could leave orphaned posts.
function delete_post( $resno, $pwd, $imgonly = 0, $automatic = 0, $children = 1, $die = 1 ) {
        require_once("_core/log/log.php");

    global $log, $path;
    log_cache();
    $resno = intval( $resno );
    
    // get post info
    if ( !isset( $log[$resno] ) ) {
        if ( $die )
            error( "Can't find the post $resno." );
    }
    $row = $log[$resno];
    
    // check password- if not ok, check admin status (and set $admindel if allowed)
    $delete_ok = ( $automatic || ( substr( md5( $pwd ), 2, 8 ) == $row['pwd'] ) || ( $row['host'] == $_SERVER['REMOTE_ADDR'] ) );
    if ( valid( 'janitor_board' ) ) {
        $delete_ok = $admindel = valid( 'delete', $resno );
    }
    if ( !$delete_ok )
        error( S_BADDELPASS );
    
    // check ghost bumping
    if ( !isset( $admindel ) || !$admindel ) {
        if ( BOARD_DIR == 'a' && (int) $row['time'] > ( time() - 25 ) && $row['email'] != 'sage' ) {
            $ghostdump = var_export( array(
                 'server' => $_SERVER,
                'post' => $_POST,
                'cookie' => $_COOKIE,
                'row' => $row 
            ), true );
            //file_put_contents('ghostbump.'.time(),$ghostdump);
        }
    }
    
    if ( isset( $admindel ) && $admindel ) { // extra actions for admin user
        $auser   = mysql_real_escape_string( $_COOKIE['saguaro_auser'] );
        $adfsize = ( $row['fsize'] > 0 ) ? 1 : 0;
        $adname  = str_replace( '</span> <span class="postertrip">!', '#', $row['name'] );
        if ( $imgonly ) {
            $imgonly = 1;
        } else {
            $imgonly = 0;
        }
        $row['sub']      = mysql_real_escape_string( $row['sub'] );
        $row['com']      = mysql_real_escape_string( $row['com'] );
        $row['filename'] = mysql_real_escape_string( $row['filename'] );
        mysql_call( "INSERT INTO " . SQLDELLOG . " (postno, imgonly, board,name,sub,com,img,filename,admin) values('$resno','$imgonly','" . SQLLOG . "','$adname','{$row['sub']}','{$row['com']}','$adfsize','{$row['filename']}','$auser')" );
    }
    
    if ( $row['resto'] == 0 && $children && !$imgonly ) // select thread and children
        $result = mysql_call( "select no,resto,tim,ext from " . SQLLOG . " where no=$resno or resto=$resno" );
    else // just select the post
        $result = mysql_call( "select no,resto,tim,ext from " . SQLLOG . " where no=$resno" );
    
    while ( $delrow = mysql_fetch_array( $result ) ) {
        // delete
        $delfile  = $path . $delrow['tim'] . $delrow['ext']; //path to delete
        $delthumb = THUMB_DIR . $delrow['tim'] . 's.jpg';
        if ( is_file( $delfile ) )
            unlink( $delfile ); // delete image
        if ( is_file( $delthumb ) )
            unlink( $delthumb ); // delete thumb
        if ( OEKAKI_BOARD == 1 && is_file( $path . $delrow['tim'] . '.pch' ) )
            unlink( $path . $delrow['tim'] . '.pch' ); // delete oe animation
        if ( !$imgonly ) { // delete thread page & log_cache row
            if ( $delrow['resto'] )
                unset( $log[$delrow['resto']]['children'][$delrow['no']] );
            unset( $log[$delrow['no']] );
            $log['THREADS'] = array_diff( $log['THREADS'], array(
                 $delrow['no'] 
            ) ); // remove from THREADS
            mysql_call( "DELETE FROM reports WHERE no=" . $delrow['no'] ); // clear reports
            if ( USE_GZIP == 1 ) {
                @unlink( RES_DIR . $delrow['no'] . PHP_EXT );
                @unlink( RES_DIR . $delrow['no'] . PHP_EXT . '.gz' );
            } else {
                @unlink( RES_DIR . $delrow['no'] . PHP_EXT );
            }
        }
    }
    
    //delete from DB
    if ( $row['resto'] == 0 && $children && !$imgonly ) // delete thread and children
        $result = mysql_call( "delete from " . SQLLOG . " where no=$resno or resto=$resno" );
    elseif ( !$imgonly ) // just delete the post
        $result = mysql_call( "delete from " . SQLLOG . " where no=$resno" );
    
    return $row['resto']; // so the caller can know what pages need to be rebuilt
}

/* user image deletion */
function usrdel( $no, $pwd ) {
    global $path, $pwdc, $onlyimgdel;
    $host         = $_SERVER["REMOTE_ADDR"];
    $delno        = array();
    $rebuildindex = !( defined( "STATIC_REBUILD" ) && STATIC_REBUILD );
    $delflag      = FALSE;
    reset( $_POST );
    while ( $item = each( $_POST ) ) {
        if ( $item[1] == 'delete' ) {
            array_push( $delno, $item[0] );
            $delflag = TRUE;
        }
    }
    if ( $pwd == "" && $pwdc != "" )
        $pwd = $pwdc;
    $countdel = count( $delno );
    
    $flag    = FALSE;
    $rebuild = array(); // keys are pages that need to be rebuilt (0 is index, of course)
    for ( $i = 0; $i < $countdel; $i++ ) {
        $resto = delete_post( $delno[$i], $pwd, $onlyimgdel, 0, 1, $countdel == 1 ); // only show error for user deletion, not multi
        if ( $resto )
            $rebuild[$resto] = 1;
    }
    /*if ( !$flag )
    error( S_BADDELPASS );*/
    log_cache();
    //mysql_board_call("UNLOCK TABLES");  
    foreach ( $rebuild as $key => $val ) {
        updatelog( $key, 1 ); // leaving the second parameter as 0 rebuilds the index each time!
    }
    if ( $rebuildindex )
        updatelog(); // update the index page last
}

//Called when someone tries to visit imgboard.php?res=[[[postnumber]]]
function resredir( $res ) {
    $res = (int) $res;
    
    if ( !$redir = mysql_call( "select no,resto from " . SQLLOG . " where no=" . $res ) ) {
        echo S_SQLFAIL;
    }
    list( $no, $resto ) = mysql_fetch_row( $redir );
    if ( !$no ) {
        $maxq = mysql_call( "select max(no) from " . SQLLOG . "" );
        list( $max ) = mysql_fetch_row( $maxq );
        if ( !$max || ( $res > $max ) )
            header( "HTTP/1.0 404 Not Found" );
        else // res < max, so it must be deleted!
            header( "HTTP/1.0 410 Gone" );
        error( S_NOTHREADERR, $dest );
    }
    
    if ( $resto == "0" ) // thread
        $redirect = DATA_SERVER . BOARD_DIR . "/res/" . $no . PHP_EXT . '#' . $no;
    else
        $redirect = DATA_SERVER . BOARD_DIR . "/res/" . $resto . PHP_EXT . '#' . $no;
    
    
    echo "<META HTTP-EQUIV=\"refresh\" content=\"0;URL=$redirect\">";
    if ( $resto == "0" )
        log_cache();
    
    
    if ( $resto == "0" ) { // thread
        updatelog( $res );
    }
}

function rebuild( $all = 0 ) {
    if ( !valid( 'moderator' ) )
        die( 'Update failed...' );
    
    header( "Pragma: no-cache" );
    echo "Rebuilding ";
    if ( $all ) {
        echo "all";
    } else {
        echo "missing";
    }
    echo " replies and pages... <a href=\"" . PHP_SELF2_ABS . "\">Go back</a><br><br>\n";
    ob_end_flush();
    $starttime = microtime( true );
    if ( !$treeline = mysql_call( "select no,resto from " . SQLLOG . " where root>0 order by root desc" ) ) {
        echo S_SQLFAIL;
    }
    log_cache();
    echo "Writing...\n";
    if ( $all || !defined( 'CACHE_TTL' ) ) {
        while ( list( $no, $resto ) = mysql_fetch_row( $treeline ) ) {
            if ( !$resto ) {
                updatelog( $no, 1 );
                echo "No.$no created.<br>\n";
            }
        }
        updatelog();
        echo "Index pages created.<br>\n";
    } else {
        $posts = rebuildqueue_take_all();
        foreach ( $posts as $no ) {
            $deferred = ( updatelog( $no, 1 ) ? ' (deferred)' : '' );
            if ( $no )
                echo "No.$no created.$deferred<br>\n";
            else
                echo "Index pages created.$deferred<br>\n";
        }
    }
    $totaltime = microtime( true ) - $starttime;
    echo "<br>Time elapsed (lock excluded): $totaltime seconds", "<br>Pages created.<br><br>\nRedirecting back to board.\n<META HTTP-EQUIV=\"refresh\" content=\"10;URL=" . PHP_SELF2 . "\">";
}

/*-----------Main-------------*/
switch ( $mode ) {
    case 'regist':
        regist( $name, $email, $sub, $com, '', $pwd, $upfile, $upfile_name, $resto );
        break;
    case 'rebuild':
        rebuild();
        break;
    case 'rebuildall':
        rebuild( 1 );
        break;	
    case 'usrdel':
        usrdel( $no, $pwd );
        break;
    default:
        if ( $res ) {
            resredir( $res );
            echo "<META HTTP-EQUIV=\"refresh\" content=\"10;URL=" . PHP_SELF2_ABS . "\">";
        } else {
            echo "Updating index...\n";
            updatelog();
            echo "<META HTTP-EQUIV=\"refresh\" content=\"0;URL=" . PHP_SELF2_ABS . "\">";
        }
}
?>
