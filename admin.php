<?php

require('config.php');

require_once(CORE_DIR . "/mysql/mysql.php");
$mysql = new SaguaroMySQL;
$mysql->init();
$con = $mysql->connection;

if (!function_exists(mysql_call)) {
    function mysql_call($query) {
        global $mysql;
        return $mysql->query($query);
    }
}

//Load and initialize Log.
require_once(CORE_DIR . "/log/log.php");
$my_log = new Log;

extract($_POST);

function head()
{
    require_once(CORE_DIR . "/general/head.php");

    $head = new Head;
    return $head->generate();
}

function postinfo($no)
{


    if (!$result = mysql_query("SELECT * FROM " . SQLLOG . " WHERE no='" . $no . "'"))
        echo S_SQLFAIL;
    $row = mysql_fetch_row($result);

    list($no, $now, $name, $email, $sub, $com, $host, $pwd, $ext, $w, $h, $tn_w, $tn_h, $tim, $time, $md5, $fsize, $fname, $sticky, $permasage, $locked, $root, $resto, $board, ) = $row;

    $dat .= head();

    $dat .= "<table border='0' cellpadding='0' cellspacing='0'  />";
    $dat .= "<tr>[<a href='" . PHP_ASELF . "' />Return</a>]</tr><br><hr><br>";
    if ($sticky || $locked || $permasage) {
        if ($sticky)
            $special .= "<b><font color=\"FF101A\"> [Stickied]</font></b>";
        if ($locked)
            $special .= "<b><font color=\"770099\">[Locked]</font></b>";
        if ($permasage)
            $special .= "<b><font color=\"2E2EFE\">[Permasaged]</font></b>";
        $dat .= "<tr><td class='postblock'>Special:</td><td class='row2'>This thread is $special</td></tr>"; //lmoa
    }
    if (!valid('moderator')) //Hide IPs from janitors
        $host = '###.###.###.###';       
    if ($host == '')
        $host = "No IP in database";
    $dat .= "<tr><td class='postblock'>Name:</td><td class='row1'>$name</td></tr>
  <tr><td class='postblock'>Date:</td><td class='row2' />$now</td></tr>
  <tr><td class='postblock'>IP:</td><td class='row1' /><b>$host</b></td></tr><br>
  <tr><td class='postblock'>Comment:</td><td class='row2' />$com</td></tr>
  <tr><td class='postblock'>MD5:</td><td class='row1' />$md5</td></tr>
  <tr><td class='postblock'>File</td>";

    if ($w && $h) {
        $hasimg = 1;
        $dat .= "<td><img width='" . MAX_W . "' height='" . MAX_H . "' src='" . DATA_SERVER . BOARD_DIR . "/" . IMG_DIR . $tim . $ext . "'/></td></tr>
		<tr><td class='postblock'>Thumbnail:</td><td><img width='" . $tn_w . "' height='" . $tn_h . "' src='" . DATA_SERVER . BOARD_DIR . "/" . THUMB_DIR . $tim . "s.jpg" . "'/></td></tr>
		<tr><td class='postblock'>Links:</td><td>[<a href='" . DATA_SERVER . BOARD_DIR . "/" . IMG_DIR . $tim . $ext . "' target='_blank' />Image src</a>][<a href='" . DATA_SERVER . BOARD_DIR . "/" . THUMB_DIR . $tim . "s.jpg' target='_blank' />Thumb src</a>]
		[<a href='" . DATA_SERVER . BOARD_DIR . "/" . RES_DIR . $no . PHP_EXT . "#" . $no . "' target='_blank' /><b>View in thread</b></a>]</td></tr>";
    } else
        $dat .= "<td>No file</td></tr>";
    if (!$resto) {
        $dat .= "<form action='admin.php' />
        <tr><td class='postblock'>Action</td><td><input type='hidden' name='mode' value='modipost' /><select name='action' />
        <option value='sticky' />Sticky</option>
        <option value='unsticky' />Unsticky</option>
        <option value='lock' />Lock</option>
        <option value='unlock' />Unlock</option>
        <option value='permasage' />Autosage</option>
        <option value='nopermasage' />De-autosage</option>
        </select></td><td><input type='hidden' name='no' value='$no' /><input type='submit' value='Submit'></td></tr></table></form>";
    } else
        $dat .= "</table></form>";

    $result = mysql_call("SELECT COUNT(*) FROM " . SQLBANLOG . " WHERE ip='" . $host . "'");
    $wew    = mysql_result($result, 0);

    if ($wew > 0)
        $alert = "<b><font color=\"FF101A\"> $wew ban(s) on record for $host!</font></b>";
    else
        $alert = "No bans on record for IP $host";

    $dat .= "<br><table border='0' cellpadding='0' cellspacing='0' /><form action='admin.php?mode=ban' method='POST' />
	<input type='hidden' name='no' value='$no' />
	<input type='hidden' name='ip' value='$host' />

	<center><th class='postblock'><b>Ban panel</b></th></center>
	<tr><td class='postblock'>IP History: </td><td>$alert</td></tr>
	<tr><td class='postblock'>Unban in:</td><td><input type='number' min='0' size='4' name='banlength'  /> days</td></tr>
	<center><tr><td class='postblock'>Ban type:</td><td></center>
		<select name='banType' />
        <option value='warn' />Warning only</option>
        <option value='thisboard' />This board - /" . BOARD_DIR . "/ </option>
        <option value='global' />All boards</option>
		<option value='perma' />Permanent - All boards</option>
        </select>
	</td></tr>
	<tr><td class='postblock'>Public reason:</td><td><textarea rows='2' cols='25' name='pubreason' /></textarea></td></tr>
	<tr><td class='postblock'>Staff notes:</td><td><input type='text' name='staffnote' /></td></tr>
	<tr><td class='postblock'>Append user's comment:</td><td><input type='text' name='custmess' placeholder='Leave blank for USER WAS BAN etc.' /> [ Show message<input type='checkbox' name='showbanmess' /> ] </td></tr>
	<tr><td class='postblock'>After-ban options:</td><td>
		<select name='afterban' />
        <option value='none' />None</option>
        <option value='delpost' />Delete this post</option>
        <option value='delallbyip' />Delete all by this IP</option>
        <option value='delimgonly' />Delete image only</option>
        </select>
	</td></tr>";
    if (valid('admin'))
        $dat .= "
		<tr><td class='postblock'>Add to Blacklist:</td><td>[ Comment<input type='checkbox' name='blacklistcom' /> ] [ Image MD5<input type='checkbox' name='blacklistimage' /> ] </td></tr>";

    $dat .= "<center><tr><td><input type='submit' value='Ban'/></td></tr></center></table></form><br><hr>";

    $dat .= "<tr>[<a href='" . PHP_ASELF . "' />Return</a>]</tr><br>";

    echo $dat;

}

function aform(&$post, $resno, $admin = "")
{
    require_once(CORE_DIR . "/postform.php");

    $postform = new PostForm;
    $post .= "<div id='adminForm' style='display:none; align:center;' />" . $postform->format($resno, $admin) . "</div>";
}

function login($usernm, $passwd)
{
    $ip     = $_SERVER['REMOTE_ADDR'];
    $usernm = mysql_real_escape_string($usernm);
    $passwd = mysql_real_escape_string($passwd);

    if (!$query = mysql_call("SELECT user,password FROM " . SQLMODSLOG . " WHERE user='$usernm' and password='$passwd'")) {
        mysql_call("INSERT INTO loginattempts (userattempt,passattempt,board,ip,attemptno) values('$usernm','$passwd','" . BOARD_DIR . "','$ip','1')");
        error(S_WRONGPASS);
    }

    $hacky  = mysql_fetch_array($query);
    $usernm = $hacky[0];
    $passwd = $hacky[1];

    setcookie('saguaro_auser', $usernm, 0);
    setcookie('saguaro_apass', $passwd, 0);

    echo "<META HTTP-EQUIV=\"refresh\" content=\"0;URL=" . PHP_ASELF_ABS . " \">";
}

/*password validation */
function oldvalid($pass)
{

    /*    if ($pass && $pass != PANEL_PASS)
    error(S_WRONGPASS);*/

    require_once(CORE_DIR . "/admin/report.php");

    $getReport = new Report;
    $active = $getReport->get_all_reports_board();

    if (valid('janitor_board')) {
        echo head();
        echo "<div class='pannelOps' />[<a href=\"" . PHP_SELF2 . "\">" . S_RETURNS . "</a>]";
        echo "[<a href=\"" . PHP_SELF . "\">" . S_LOGUPD . "</a>]";
        if (valid('moderator')) {
            echo "[<a href='" . PHP_ASELF_ABS . "?mode=rebuild' >Rebuild</a>]";
            echo "[<a href='" . PHP_ASELF_ABS . "?mode=rebuildall' >Rebuild all</a>]";
            echo "[<a href='" . PHP_ASELF_ABS . "?mode=reports' >" . $active . "</a>]";
        }
        echo "[<a href='" . PHP_ASELF . "?mode=logout'>" . S_LOGOUT . "</a>]";
        echo "<div class='managerBanner' >" . S_MANAMODE . "</div><br>
        [ <a href='#' onclick=\"toggle_visibility('adminForm');\" style='text-align:center;' >Toggle postform</a> ]</div><br>";
        //echo "<form action='" . PHP_SELF . "' method='post' id='contrib' >";
    } else { // Admin.php login
        echo "<form action='" . PHP_ASELF . "' method='post'>";
        echo "<div align='center' vertical-align=\"middle\" >";
        echo "<input type='hidden' name=mode value=admin>";
        echo "<input type='text' name=usernm size=20><br />";
        echo "<input type='password' name=passwd size=20><br />";
        echo "<input type=submit value=\"" . S_MANASUB . "\"></form></div>";
        if (isset($_POST['usernm']) && isset($_POST['passwd']))
            login($_POST['usernm'], $_POST['passwd']);
        die("</body></html>");
    }
}

/* Admin deletion */
function admindel($pass)
{
    global $path, $onlyimgdel;
    $delno   = array(
         dummy
   );
    $delflag = FALSE;
    reset($_POST);
    while ($item = each($_POST)) {
        if ($item[1] == 'delete') {
            array_push($delno, $item[0]);
            $delflag = TRUE;
        }
    }
    if ($delflag) {
        if (!$result = mysql_call("select * from " . SQLLOG . "")) {
            echo S_SQLFAIL;
        }
        $find = FALSE;

        while ($row = mysql_fetch_row($result)) {
        list($no, $now, $name, $email, $sub, $com, $host, $pwd, $ext, $w, $h, $tn_w, $tn_h, $tim, $time, $md5, $fsize, $fname, $sticky, $permasage, $locked, $root, $resto) = $row;
            if ($onlyimgdel == on) {
                delete_post($no, $pwd, 1, 1, 1, 0);
            } else {
                if (array_search($no, $delno)) { //It is empty when deleting
                    delete_post($no, $pwd, 0, 1, 1, 0);
                }
            }
        }
    }

    function calculate_age($timestamp, $comparison = '')
    {
        $units = array(
             'second' => 60,
            'minute' => 60,
            'hour' => 24,
            'day' => 7,
            'week' => 4.25,
            'month' => 12
       );

        if (empty($comparison)) {
            $comparison = $_SERVER['REQUEST_TIME'];
        }
        $age_current_unit = abs($comparison - $timestamp);
        foreach ($units as $unit => $max_current_unit) {
            $age_next_unit = $age_current_unit / $max_current_unit;
            if ($age_next_unit < 1) {
                // are there enough of the current unit to make one of the next unit?
                $age_current_unit = floor($age_current_unit);
                $formatted_age    = $age_current_unit . ' ' . $unit;
                return $formatted_age . ($age_current_unit == 1 ? '' : 's');
            }
            $age_current_unit = $age_next_unit;
        }

        $age_current_unit = round($age_current_unit, 1);
        $formatted_age    = $age_current_unit . ' year';
        return $formatted_age . (floor($age_current_unit) == 1 ? '' : 's');

    }


    // Deletion screen display
    echo "<input type=hidden name=mode value=admin>";
    echo "<input type=hidden name=admin value=del>";
    echo "<input type=hidden name=pass value=\"$pass\">";
    echo "<div class='managerBanner'>" . S_DELLIST . "</div>";
    echo "<div class='delbuttons'><input type=submit value='" . S_ITDELETES . "'>";
    echo "<input type=reset value='" . S_MDRESET . "'>";
    echo "[<input type=checkbox name=onlyimgdel value=on><!--checked-->" . S_MDONLYPIC . "]</div>";
    echo "<table class='postlists'>";
    echo "<tr class='postTable head'>" . S_MDTABLE1;
    echo S_MDTABLE2;
    echo "</tr>";

    if (!$result = mysql_call("select * from " . SQLLOG . " order by no desc")) {
        echo S_SQLFAIL;
    }
    $j = 0;
    while ($row = mysql_fetch_row($result)) {
        $j++;
		$path = realpath("./") . '/' . IMG_DIR;
        $img_flag = FALSE;
        list($no, $now, $name, $email, $sub, $com, $host, $pwd, $ext, $w, $h, $tn_w, $tn_h, $tim, $time, $md5, $fsize, $fname, $sticky, $permasage, $locked, $root, $resto) = $row;
        // Format
        /*$now = ereg_replace('.{2}/(.*)$', '\1', $now);
        $now = ereg_replace('\(.*\)', ' ', $now);*/
        if (strlen($name) > 10)
            $name = substr($name, 0, 9) . "...";
        if (strlen($sub) > 10)
            $sub = substr($sub, 0, 9) . "...";
        if ($email)
            $name = "<a href=\"mailto:$email\">$name</a>";
        $com = str_replace("<br />", " ", $com);
        $com = htmlspecialchars($com);
        if (strlen($com) > 20)
            $com = substr($com, 0, 18) . "...";
        if (strlen($fname) > 10)
            $fname = substr($fname, 0, 40) . "..." . $ext;
        // Link to the picture
        if ($ext && is_file($path . $tim . $ext)) {
            $img_flag = TRUE;
            $clip     = "<a class=\"thumbnail\" target=\"_blank\" href=\"" . IMG_DIR . $tim . $ext . "\">" . $tim . $ext . "<span><img class='postimg' src=\"" . THUMB_DIR . $tim . 's.jpg' . "\" width=\"100\" height=\"100\" /></span></a><br />";
            if ($fsize >= 1048576) {
                $size  = round(($fsize / 1048576), 2) . " M";
                $fsize = $asize;
            } else if ($fsize >= 1024) {
                $size  = round($fsize / 1024) . " K";
                $fsize = $asize;
            } else {
                $size  = $fsize . " ";
                $fsize = $asize;
            }
            $all += $asize; //total calculation
            $md5 = substr($md5, 0, 10);
        } else {
            $clip = "[No file]";
            $size = 0;
            $md5  = "";
        }
        $class = ($j % 2) ? "row1" : "row2"; //BG color

        if ($resto == '0')
            $resdo = '<b>OP(<a href="' . DATA_SERVER . BOARD_DIR . "/" . RES_DIR . $no . PHP_EXT . '#' . $no . '" target="_blank" />' . $no . '</a>)</b>';
        else
            $resdo = '<a href="' . DATA_SERVER . BOARD_DIR . "/" . RES_DIR . $resto . PHP_EXT . '#' . $no . '" target="_blank" />' . $resto . '</a>';
        $warnSticky = '';
        if ($sticky == '1')
            $warnSticky = "<b><font color=\"FF101A\">(Sticky)</font></b>";
        echo "<tr class=$class><td><input type=checkbox name=\"$no\" value=delete>$warnSticky</td>";
        echo "<td>$no</td><td>$resdo</td><td>$now</td><td>$sub</td>";
        echo "<td>$name</b></td><td>$com</td>";
        echo "<td class='postimg' >$clip</td><td>" . calculate_age($time) . "</td><td><input type=\"button\" text-align=\"center\" onclick=\"location.href='" . PHP_ASELF_ABS . "?mode=more&no=" . $no . "';\" value=\"Post Info\" /></td>\n";
        echo "</tr>";
    }
    mysql_free_result($result);

    echo "<link rel='stylesheet' type='text/css' href='" . CSS_PATH . "/stylesheets/img.css' />";
    //foot($dat);
    $all = (int) ($all / 1024);
    echo "<div align='center'/>[ " . S_IMGSPACEUSAGE . $all . "</b> KB ]</div>";
    die("</body></html>");
}

function valid($action = 'moderator', $no = 0)
{
    require_once(CORE_DIR . "/admin/validate.php");

    $validate = new Validation;
    $allowed  = $validate->verify($action);
    return $allowed;
}

// deletes a post from the database
// imgonly: whether to just delete the file or to delete from the database as well
// automatic: always delete regardless of password/admin (for self-pruning)
// children: whether to delete just the parent post of a thread or also delete the children
// die: whether to die on error
// careful, setting children to 0 could leave orphaned posts.
function delete_post($resno, $pwd, $imgonly = 0, $automatic = 0, $children = 1, $die = 1)
{
    require_once(CORE_DIR . "/log/log.php");
    require_once(CORE_DIR . "/admin/delpost.php");

    $remove = new DeletePost;
    $remove->targeted($resno, $pwd, $imgonly = 0, $automatic = 0, $children = 1, $die = 1);
}

function modify_post($no, $action = 'none')
{
    if (!valid('moderator'))
        die("\"PLEASE AUTOBAN ME FOREVER!!!\" - you");
    switch ($action) {
        case 'sticky':
            $sqlValue = "sticky";
            $rootnum  = "2027-07-07 00:00:00";
            $sqlBool  = "'1', root='" . $rootnum . "'";
            $verb     = "Stuck";
            break;
        case 'unsticky':
            $sqlValue = "sticky";
            $rootnum  = date('Y-m-d G:i:s');
            $sqlBool  = "'0', root='" . $rootnum . "'";
            $verb     = "Unstuck";
            break;
        case 'lock':
            $sqlValue = "locked";
            $sqlBool  = "'1'";
            $verb     = "Locked";
            break;
        case 'unlock':
            $sqlValue = "locked";
            $sqlBool  = "'0'";
            $verb     = "Unlocked";
            break;
        case 'permasage':
            $sqlValue = "permasage";
            $sqlBool  = "'1'";
            $verb     = "Autosaging";
            break;
        case 'nopermasage':
            $sqlValue = "permasage";
            $sqlBool  = "'0'";
            $verb     = "Normally bumping";
            break;
        case 'delete':
            delete_post($resno, $pwd, $imgonly = 0, $automatic = 1, $children = 1, $die = 1);
            break;
        case 'deleteallbyip':
            delete_post($resno, $pwd, $imgonly = 0, $automatic = 1, $children = 1, $die = 1, $allbyip = 1);
            break;
        case 'deleteimgonly':
            delete_post($resno, $pwd, $imgonly = 1, $automatic = 1, $children = 0, $die = 1);
            break;
        default:
            break;
    }

    mysql_call('UPDATE ' . SQLLOG . " SET  $sqlValue=$sqlBool WHERE no='" . mysql_real_escape_string($no) . "' LIMIT 1");
    echo head($dat);
    echo $verb . " thread $no. Redirecting...<META HTTP-EQUIV=\"refresh\" content=\"1;URL=" . PHP_ASELF_ABS . "\">";

}


/* Main switch */
switch ($_GET['mode']) {
    case 'admin':
        echo "<META HTTP-EQUIV=\"refresh\" content=\"0;URL=" . PHP_ASELF_ABS . "\">";
        break;
    case 'more':
        $no = mysql_real_escape_string($_GET['no']);
        postinfo($no);
        break;
    case 'logout':
        setcookie('saguaro_apass', '0', 1);
        setcookie('saguaro_auser', '0', 1);
        echo "<META HTTP-EQUIV=\"refresh\" content=\"0;URL=" . PHP_SELF2_ABS . "\">";
        break;
    case 'ban':
		echo head();
        require_once(CORE_DIR . "/admin/banish.php");

		$banish = new Banish;
		if ($banish->checkBan($_SERVER['REMOTE_ADDR'])) {
			$banish->postOptions($no, $_SERVER['REMOTE_ADDR'], $_POST['banlength'], $_POST['banType'], $_POST['perma'], $_POST['pubreason'], $_POST['staffnote'], $_POST['custmess'], $_POST['showbanmess'], $_POST['afterban']);
        //gee i hope nobody saw this
		}
		$banish->afterBan;
		break;
	case 'reports':
		require_once(CORE_DIR . "/admin/report.php");
		$getReport = new Report;
		$active = $getReport->get_all_reports_board();
		oldvalid($pass);
		$getReport->display_list();
		break;
    case 'zmdlog':
        login($_POST['usernm'], $_POST['passwd']);
        break;
    case 'rebuild':
		require_once(CORE_DIR . "/log/rebuild.php");
        rebuild();
        break;
    case 'rebuildall':
		require_once(CORE_DIR . "/log/rebuild.php");
        rebuild(1);
        break;
    case "modipost":
        modify_post($_GET['no'], $_GET['action']);
        break;
    default:
        oldvalid($pass);
        aform($post, $res, 1);
        echo $post;
        echo "<form action=\"" . PHP_ASELF . "\" method=\"post\">
            <input type=hidden name=admin value=del checked>";
        admindel($pass);
        die("</body></html>");
        break;
}
?>
