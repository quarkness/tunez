<?php
# tunez.inc.php
#
# This is the include file called almost all the time, checking to ensure
# the user is logged in is handled here as are assigning permissions, etc.

/*
 * tunez
 *
 * Copyright (C) 2003, Ivo van Doesburg <idoesburg@outdare.nl>
 *  
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 */
 
# You must set the path to your confic.inc.php file here!  This should be the
# only thing you have to touch in this file
require_once("/path/to/config.inc.php");

if ($justconfig) { // this is a hack
    return;
}

// Enable error reporting for all errors except E_NOTICE
error_reporting(E_ALL ^ E_NOTICE);

require_once($_CONF['path_system'] . "classes/PQueue.class.php");
#require_once($_CONF['path_system'] . "classes/template.class.php");

mysql_pconnect($_CONF['mysql_dbhost'], $_CONF['mysql_dbuser'], $_CONF['mysql_dbpass']) or die(mysql_error());
mysql_select_db($_CONF['mysql_dbname']);

if ($justdb) { // this is a hack
    return;
}

$artists_left_join = "LEFT JOIN artists on songs.artist_id=artists.artist_id";
$albums_left_join = "LEFT JOIN albums on songs.album_id=albums.album_id";

# Yes I know this is an ugly hack and annoying to maintain FIXME
$sql_permissions = Array("p_upload", "p_modify_after_upload", "p_unvote",
        "p_see_hidden", "p_random_skip", "p_skip", "p_select_edit",
        "p_change_perms", "p_updateDb", "p_sync", "p_vote", "p_daemon", "p_volume", "p_download", "p_select_vote");
$sql_permissions_descrip = Array("Upload Song","Modify Song After Upload","Cancel Own Votes", "See Hidden Songs", "Skip Random Songs", "Skip Any Song", "Edit/Del/Hide Any Song", "Modify Users/Groups", "Update the Database", "Sync Songs Database to Songs", "Vote for Songs", "Start/Stop Daemon", "Adjust Volume", "Download Songs", "Mass Song Voting");
$sql_permission_names = "p_upload,p_modify_after_upload,p_unvote,p_see_hidden,p_random_skip,p_skip,p_select_edit,p_change_perms,p_updateDb,p_sync,p_vote,p_daemon,p_volume,p_download,p_select_vote";
$number_of_permissions = 15;
$sortable_fields = array("artist_name", "songtitle", "album_name", "track");


if (!($disable_session)) {
    if(!empty($_COOKIE['remember_session_id'])) { 
        require_once("login.inc.php");
       
        // TODO TEST this to make sure another user can't hijack a session_id if the person logs out properly and saved->0
        // if they have a keep_session_id cookie
        // check to make sure they actually wanted to save it and someone isn't hijacking their session
        $query = "SELECT * FROM sessions WHERE session_id=\"" . $_COOKIE['remember_session_id'] . "\" ORDER BY last_refresh_time DESC";
        $result = tunez_query($query);
        if (mysql_num_rows($result) < 1) {
            logout();
            die("No record of that cookie session id.  Please try logging in again");
        }
        
        $row = mysql_fetch_object($result);
        if ($row->saved) { // if they saved their last session (ie left a cookie)
            $saved_session_id = $row->session_id;
            $user_id = $row->user_id;
            session_id($saved_session_id);
            session_start();
            $_SESSION['unique_session_num'] = insert_session($user_id, $saved_session_id, 1);
        }
        else {
            // it's possible someone's trying to steal their session
            // therefore, remove their cookie...
            setCookie("remember_session_id", "");
        }
    }
    else {
        session_start();
    }

    if (!empty($_SESSION['user_id'])) {
        // if they have what seems to be a valid sessionID that wasn't logged out (and destroyed)

        $query = "SELECT UNIX_TIMESTAMP(last_refresh_time) as last_refresh_time FROM 
            sessions WHERE unique_session_num = \"$_SESSION[unique_session_num]\"";
        $result = mysql_query($query) or die($query . "<br>" . mysql_error());
        $row = mysql_fetch_object($result);
        if($row->last_refresh_time < (time() - TIMEOUT_SECONDS)) {
            // if it's been 10 minutes since they last refreshed a page
            include("login.inc.php");
            timeout(session_id());
            $_SESSION['messageTitle'] = "Timeout Error";
            $_SESSION['messageBody'] = "Our records show your session has been idle for 10 minutes or more.  Please reauthenticate.";
        }
        else {
            $user_id = $_SESSION['user_id'];
            $songsperpage = $_SESSION['songsperpage'];
            $user = $_SESSION['user'];

            // TODO
            // Add a pass through page so that when someone autorefreshes because of a song change this does not
            // reset their timeout timer.  Check here to see if the pass through page was called if so, skip the
            // update
            // end TODO
            $query = "UPDATE sessions SET last_refresh_time=NOW() WHERE unique_session_num=" . $_SESSION['unique_session_num'];
            tunez_query($query);
        }
    }

    if (!empty($_GET['searchFor']))	
        $_SESSION['searchFor'] = $_GET['searchFor'];

    if ($_SESSION['perms']['p_see_hidden']) {
        $show_active = 1;
    }
    else {
        $show_active = "(songs.status='normal')";
    }
        
#ob_end_flush();
}
// Begin functions....

function access_denied($refpage=NULL) {
    global $_CONF;
    $text = "Location: " . $_CONF['url'] . "access_denied.php?page=";
    if ($refpage!=NULL) {
        return $text . $refpage;
    }
    else {
        return $text . $_SERVER[REQUEST_URI];
    }
}

function tunez_query($ps_sql)
{
    GLOBAL $g_debug;
    if ($g_debug || $_GET['g_debug']) { #FIXME not secure
        $g_debug = true;
    }
    $lv_retval = false;
    if ($g_debug)
    {
        echo $ps_sql . "\n" . "<p>";
    }
    if ($ps_sql != "")
    {
        $lh_query_result = mysql_query($ps_sql);
        if ((mysql_errno() > 0) && ($g_debug == true))
        {
            echo "<BR>error: " . mysql_error() . "<BR>" . $ps_sql;
        }
        if ($lh_query_result != false)
        {
            $lv_retval = $lh_query_result;
        }
    }
    return ($lv_retval);
}

function tunez_debug($text, $debuglevel=NULL) {
    GLOBAL $g_debug;
    if(!empty($_GET['g_debug'])) {
        $g_debug = $_GET['g_debug'];
    }
    if ($g_debug) {
        print "$text<br>\n";
    }
}

function allowed_unvotes($user_id) {
    $allowed_unvotes = array();
    $unvote_query = "SELECT DISTINCT song_id from queue where user_id=$user_id";
    $unvote_result = tunez_query($unvote_query);
    while($unvote_row = mysql_fetch_object($unvote_result))
    {
        $allowed_unvotes[] = $unvote_row->song_id;
    }
    mysql_free_result($unvote_result);
    return $allowed_unvotes;
}

function translateFieldName($fieldname)
{
    global $_CONF;
    if (!empty($_CONF['field_descriptions'][$fieldname]))
        $fieldname = $_CONF['field_descriptions'][$fieldname];
    return $fieldname;
}

function displayMenuQueue()
{
    global $user_id, $_CONF;

    $totalQueueTime = 0;

    $queue = new PQueue();
    $queue->read();
    
    if ($queue->size() == 0)
    {
        echo "<p>Nothing in Queue!</p>";
        return;
    }
    else 
    {
        if($_SESSION['perms']['p_unvote']) {
            $allowed_unvotes = allowed_unvotes($_SESSION['user_id']);
        }
        
        $query = "SELECT TIME_TO_SEC(length) AS seconds, priority_queue.song_id,
            artist_name,songtitle FROM priority_queue LEFT JOIN songs ON
            priority_queue.song_id=songs.song_id LEFT JOIN artists ON
            songs.artist_id=artists.artist_id";
        $result = tunez_query($query);
        
        echo "<table border=0>";
        $i=1;
        while ($row = mysql_fetch_object($result))
        {
            $totalQueueTime += $row->seconds;
            $queueText = "$row->artist_name - $row->songtitle";
            echo "<tr>";
            if ($_SESSION['user'] && in_array($row->song_id, $allowed_unvotes)) {
                $voted = TRUE;
            }
            else {
                $voted = FALSE;
            }
            
            if(!$voted AND $_SESSION['perms']['p_vote']) {
                echo "<td><a href=\"" . $_CONF['url'] . "vote.php?action=vote&song_id=$row->song_id\">
                <img src=\"" . $_CONF['url_images'] . "vote.png\" border=\"0\"></a></td>";
            }
            else {
                echo "<td></td>";
            }
                
            if($voted AND $_SESSION['perms']['p_unvote']) {
                echo "<td><a href=\"" . $_CONF['url'] . "vote.php?song_id=$row->song_id&action=unvote\">
                <img border=0 src=\"" . $_CONF['url_images'] . "unvote.png\"></img></a></td>";
            }
            else {
                echo "<td></td>"; // to balance
            }
            
            echo "<td>" . $i . ".&nbsp;<a href=\"" . $_CONF['url'] . "who.php?song_id=$row->song_id\" class=\"menu\">$queueText</a> (";
            echo display_time($row->seconds);
            echo ")\n";
            echo "</td></tr>";
            $i++;
        }
        mysql_free_result($result);	
        echo "</table>";
        echo "Total Queue Time (" . display_time($totalQueueTime) . ")<br><br>";
    }
}

function display_time($seconds) {
    if ($seconds/3600 > 1) // we have hours
        $our_hours = (int) ($seconds/3600);
    $our_minutes = (int) (($seconds%3600)/60);
    $our_seconds = (int) ($seconds%60);
    if ($our_minutes < 10)
        $our_minutes = '0' . $our_minutes;
    if ($our_seconds < 10)
        $our_seconds = '0' . $our_seconds;
    if (isset($our_hours))
        $formatted_time .= $our_hours . ':';
    $formatted_time .= $our_minutes . ':' . $our_seconds;
    return $formatted_time;
}


function timeLeft()
{
    $kweerie        = "SELECT songs.song_id,artist_name,songtitle,started,TIME_TO_SEC(length) as length from np
                       LEFT JOIN songs using(song_id)
                       LEFT JOIN artists on songs.artist_id=artists.artist_id
                       LEFT JOIN albums on songs.album_id=albums.album_id";

    $result		= tunez_query($kweerie) or die(mysql_error());
    if (mysql_num_rows($result)==0) {
        return -5000; // denotes that no songs are being played
    }
    $row		= mysql_fetch_array($result);
    $started        = $row['started'];
    $length         = $row['length'];
    if ($length == 0) {
        return 60;  // Return 60 seconds to match "No duration available" in nowPlaying()
    }
    $end            = $started + $length;

    $timeLeft = $end - time();


    return $timeLeft;
}

function nowPlaying()
{
    global $_CONF;
    $kweerie        = "SELECT np.play_id,wasrandom,songs.song_id,artist_name,songtitle,started,TIME_TO_SEC(length) as length from np
                       LEFT JOIN songs using(song_id)
		       LEFT JOIN artists on songs.artist_id=artists.artist_id
		       LEFT JOIN albums on songs.album_id=albums.album_id";

    $result	    = tunez_query($kweerie) or die(mysql_error());
    $row	    = mysql_fetch_array($result);
    $song_id        = $row['song_id'];
    $artist         = $row['artist_name'];
    $songtitle      = $row['songtitle'];
    $started        = $row['started'];
    $length         = $row['length'];
    $play_id        = $row['play_id'];

    
    if ($length == 0) {     // if the song has no viable length, forget about the clock
        $secondsLeft = "1:00";
        $length_str = "No duration available, reload in 60 seconds";
    }
    else {
        $secondsLeft = (int)(($started + $length - time())/60) . ":" . ($started + $length - time())%60;
        $length_str = (int)($length/60) . ":" . ($length%60);
    }

    if ($song_id > 0)
    {
        if (($row['wasrandom']==1) AND ($_SESSION['perms']['p_random_skip'])) {
            echo "<a href=\"" . $_CONF['url'] . "skip_random.php\" title=\"This randomly chosen song really blows\">(<b><u>Skip Song</b></u>)</a><br>";
        }
        else if ($row['wasrandom']==1 AND empty($_SESSION['perms']['p_random_skip'])) {
            echo "(Random song)";
        }
        echo "<a href=\"". $_CONF['url'] . "who_play.php?play_id=$play_id\" title=\"Who Played That?\">$artist - $songtitle</a><br>Song Length: ($length_str)";
        echo "<br>Time Remaining: (<span id=\"secondsLeft\">$secondsLeft</span>)" ;

    }
    else
    {
        echo "Nothing playing.<BR>Is the Tunez Daemon running?";
    }
}

function listSongs($kweerie, $nosort=NULL)
{
    global $sortable_fields, $_CONF;
    //startAt
    //sortBy
    //beginningWith
    //searchFor
   
    if(!empty($_GET['startAt'])) {
        $startAt = $_GET['startAt'];
    }

    $PHP_SELF = $_SERVER[PHP_SELF];
    $songsperpage = $_SESSION['songsperpage'];

    $getvars = array ("beginningWith","genre_id","genre_name",
                    "sort_by", "searchFor", "album_id", "artist_id", 
                    "search_type", "selected_album");
    foreach ($getvars as $thevar) {
        if(!empty($_GET[$thevar]))
            $myArgs .= "&$thevar=" . $_GET[$thevar];
    }
    
    if (!eregi("LIMIT", $kweerie))
    {
        $result = tunez_query($kweerie);
        $num_song_results = mysql_num_rows($result);

        if (!isset($startAt)) $startAt = "0";
        if (!isset($songsperpage)) $songsperpage = 30;

        // determin previous page link
        if ($startAt >= $songsperpage)
        {
            $prevLink = "<a
                href=\"$PHP_SELF?startAt=" . ($startAt - $songsperpage) . "$myArgs\">Previous Page &lt;&lt;</a>&nbsp;&nbsp; ";
        }
    
        //----------------------------------
        //determine page jumps in the middle
        $diffPages = 5;  // the number of pages +- existing page to show in display...
        $numPages = ceil( (float) $num_song_results / $songsperpage);
        $atPage = ceil ( (float) $startAt / $songsperpage);
        if ($numPages > 1) {
            //
            // Set bottom and top page to display and make sure they are within boundaries
            $bottomPage = $atPage-$diffPages;
            if ($bottomPage < 0) $bottomPage = 0;
            $topPage = $atPage+$diffPages;
            if ($topPage > $numPages) $topPage = $numPages;
            //
            //$indexLink = "startAt = $startAt num_song_results=$num_song_results";
            //$indexLink .= "numPages=$numPages atPage=$atPage bottomPage=$bottomPage topPage=$topPage";
            //
            for ($i = $bottomPage; $i < $topPage; $i++) {
                $nice_page_num = $i+1;
                if ($i == $atPage) {
                    $indexLink .= "<b>$nice_page_num</b>&nbsp;";
                }
                else {
                    $indexLink .= "<a href=\"$PHP_SELF?startAt=" . ($i * $songsperpage) . "$myArgs\">$nice_page_num</a>&nbsp;";
                }
            }
        }
        //-----------------------------------
        
        
        // determine next page link
        if (($startAt + $songsperpage) < $num_song_results)
        {
            $nextLink = "&nbsp;&nbsp;<a
                href=\"$PHP_SELF?startAt=" . ($startAt + $songsperpage) ."$myArgs\">&gt;&gt; Next Page</a> ";
        }
        
        if (($startAt + $songsperpage) > $num_song_results)
        {
            $stopAt = $num_song_results;
        }
        else
        {
            $stopAt = ($startAt + $songsperpage);
        }
        
        $kweerie .= " LIMIT $startAt, $songsperpage";

        $grmbl = $startAt +1;
        $navText = "<p class=navText\">$prevLink $indexLink $nextLink</p>";

        $result = tunez_query($kweerie) or die(mysql_error());
        $num_song_results_page = mysql_num_rows($result);
    }
    else {
        // if there is a limit we assume it's random mode or whatever
        $grmbl=1;
        $result = tunez_query($kweerie) or die(mysql_error());
        $num_song_results = mysql_num_rows($result);
        $stopAt = $num_song_results;
    }

    echo $navText;

    if ($num_song_results == "0")
    {
        echo "<h2>Nothing found</h2>";
        return;
    }
    else
    {
		echo <<<ENDSCRIPT

		<script language="Javascript">
		function selectall() 
		{
			var trk=0;
			frm = document.forms['songs']
			for (var i=0;i<frm.elements.length;i++)
			{
				var e = frm.elements[i];
				if ((e.name != 'allsongs') && (e.type=='checkbox'))
				{
					trk++;
					e.checked = frm.allsongs.checked;
				}
			}
		}

		</script>

ENDSCRIPT;
        echo "<h2>Showing records $grmbl to $stopAt of 
            $num_song_results records found</h2>";
        echo "<table cellpadding=3 border=0 cellspacing=1>\n";

        echo "<tr>";
        if ($_SESSION['perms']['p_select_edit'] OR $_SESSION['perms']['p_select_vote']) { // for now but later we check all p_selects* TODO
            echo "<form name=\"songs\" action=\"admin/admin_edit_record.php\" method='get'>";
            echo "<th><input name=\"allsongs\" type=checkbox onClick=\"javascript:selectall()\"></th>";
        }
        $display_fields = array();
        $number_of_fields = mysql_num_fields($result) or die(mysql_error());
        $hidden_fields = array("album_id", "status", "update_id3", "genre_id", 
            "artist_id", "song_id", "play_id", "history_id", "mood_id", "unformatted_timestamp");


        for ($i=0; $i < $number_of_fields; $i++)
        {
            $sql_fieldname = mysql_field_name($result, $i);
            $nice_fieldname = translateFieldName($sql_fieldname);

            // Never display these...
            if (!in_array($sql_fieldname, $hidden_fields))
            {
                echo "<th><nobr>";
                if (in_array($sql_fieldname, $sortable_fields) && $nosort!=TRUE) {
                    if ($_GET['order_by'] == $sql_fieldname && $_GET['sort_dir']=="DESC") {
                        echo "<a href=\"$PHP_SELF?startAt=$_GET[startAt]&order_by=$sql_fieldname&sort_dir=ASC$myArgs\">$nice_fieldname DOWN";
                    }
                    elseif ($_GET['order_by'] == $sql_fieldname && $_GET['sort_dir']=="ASC") {
                        echo "<a href=\"$PHP_SELF?startAt=$_GET[startAt]&order_by=$sql_fieldname&sort_dir=DESC$myArgs\">$nice_fieldname UP";
                    }
                    else {
                        echo "<a href=\"$PHP_SELF?startAt=$_GET[startAT]&order_by=$sql_fieldname&sort_dir=ASC$myArgs\">$nice_fieldname";
                    }
                }
                else {
                    echo "$nice_fieldname";
                }
                echo "</nobr></th>";
                
                // add field name to an array we will use 
                // later to keep everything in proper order.
                array_push($display_fields, $sql_fieldname);
            }
        }
        if ($_SESSION[perms][p_select_edit] OR 
                $_SESSION[perms][p_vote] OR
                $_SESSION[perms][p_download]) {
            echo "<th>Action</th>\n";
            echo "</tr>\n";

        }

        if ($startAt > 0) $listedNr = $startAt;

        while ($song = mysql_fetch_object($result))
        {
            $listedNr++;

            //       changed to save position in search list
            echo "<tr><a name=\"song_$song->song_id\"/>\n";
            if ($_SESSION['perms']['p_select_edit'] OR $_SESSION['perms']['p_select_vote']) {
                echo "<td><input type=\"checkbox\"
                    name=\"edit_entry_$listedNr\"
                    value=\"$song->song_id\"></td>\n";
            }
            // TODO the rest of this should be dynamic.. only displaying
            // the fields if they were part of the select statement...
            for ($i=0; $i<sizeof($display_fields); $i++) {
                if ($display_fields[$i] == "songtitle")
                    echo "<td><a href=\"songinfo.php?song_id=" .  urlencode($song->song_id) . "\">" . 
                        $song->$display_fields[$i] . "</a></td>\n";
                elseif ($display_fields[$i] == "artist_name")
                    echo "<td><a href=\"search.php?search_type=artist_name&searchFor=" .
                    urlencode($song->$display_fields[$i]) .
                    "\">" . $song->$display_fields[$i] . "</a></td>\n";
                elseif ($display_fields[$i] == "album_name")
                    echo "<td><a href=\"search.php?search_type=album_name&searchFor=" .
                    urlencode($song->$display_fields[$i]) .
                    "\">" . $song->$display_fields[$i] . "</a></td>\n";
                else
                    echo "<td>" . $song->$display_fields[$i] . "</td>\n";
            }

            //if ($showNumbers)                               echo "<td>$listedNr</td>\n";
            if ($_SESSION['perms']['p_select_edit'] ||
                    $_SESSION['perms']['p_vote'] ||
                    $_SESSION['perms']['p_download']) {
                //       changed to save position in search list
                echo "<td><nobr>";
                echo "<a title=\"$song->filename\" href=\"vote.php?action=vote&song_id=$song->song_id\">
                    <img src=\"" . $_CONF['url_images'] . "vote.png\" border=\"0\"></a>";
                echo " [";
                //                      echo "<a href=\"lyrics.php?song_id=$song_id\" title=\"Lyrics\">L </a>";
                if ($_SESSION['perms']['p_select_edit']) {
                    echo "<a href=\"admin/admin_edit_record.php?checkbox_action=edit&song_id=$song->song_id\" title=\"Edit\">
                    <img src=\"" . $_CONF['url_images'] . "edit.png\" border=\"0\"></a> ";
                }
                if ($_SESSION['perms']['p_download']) {
                    echo "<a href=\"download.php?song_id=$song->song_id\" title=\"Download\">
                    <img src=\"" . $_CONF['url_images'] . "save.png\" border=\"0\"></a> ";
                }
                echo "<a href=\"history.php?song_id=$song->song_id\" title=\"Play History\">H </a>";
                echo "]</nobr>\n";

                if ($_SESSION['perms']['p_select_edit']) {
                    echo "[ ";
                    if($song->status=='delete')
                        echo "<a href=\"admin/admin_db_action.php?song_id=$song->song_id&action=normal\" title=\"Undelete\">UnDel </a>";
                    elseif($song->status=='normal')
                    {
                        echo "<a href=\"admin/admin_db_action.php?song_id=$song->song_id&action=delete\" title=\"Delete\">
                        <img src=\"" . $_CONF['url_images'] . "delete.png\" border=\"0\"></a>";
                        echo "<a href=\"admin/admin_db_action.php?song_id=$song->song_id&action=hide\" title=\"Hide\">H </a>";
                        echo "<a href=\"admin/admin_db_action.php?song_id=$song->song_id&action=readtag\" title=\"Read from ID3 tags on file\">R </a>";
                        if($song->update_id3==1) {
                            // This songs ID3 tag needs
                            // updating!
                            echo "<a href=\"admin/admin_db_action.php?song_id=$song->song_id&action=writetag\" title=\"Please forgive the blink tag, but this audio file's tag needs updating\">
                            <blink>*</blink>W<blink>*</blink> </a>";
                        }
                        else {
                            echo "<a href=\"admin/admin_db_action.php?song_id=$song->song_id&action=writetag\" title=\"Write ID3 tag back to file\">W </a>";
                        }
                    }
                    elseif($song->status=='offline')
                    {
                        echo "<a href=\"admin/admin_db_action.php?song_id=$song->song_id&action=delete\"
                            title=\"Delete\">Del </a>";
                    }
                    elseif($song->status=='hide')
                        echo "<a href=\"admin/admin_db_action.php?song_id=$song->song_id&action=normal\"
                        title=\"Unhide\">Unhide </a>";

                    echo "]</td>";

                }
            }
            echo "</tr>\n";
        }
        echo "</table>";
        if ($_SESSION['perms']['p_select_edit'] OR $_SESSION['perms']['p_select_vote']) {
            echo "<select class=\"dropdown\" name=\"checkbox_action\">";
            if ($_SESSION['perms']['p_select_vote']) {
                echo "<option value=\"vote\">Vote";
                echo "<option value=\"rovote\">Random order vote";
            }
            if ($_SESSION['perms']['p_select_edit']) {
                echo "<option value=\"edit\">Edit Info";
                echo "<option value=\"read\">Read ID3/Ogg Tags into Database";
                echo "<option value=\"write\">Write ID3/Ogg Tags to files";
                echo "<option value=\"delete\">Mark files for deletion";
                echo "<option value=\"hide\">Hide files";
                echo "<option value=\"nornd\">Remove from random play";
                echo "<option value=\"rnd\">Add to random play";
            }
            echo "</select><br>";
            echo "<input type=\"hidden\" name=\"num_entries\"
                value=\"$listedNr\">";
            // FIXME: Potential DOS attack if listedNr is set by user
            echo "<input class=\"button\" type=\"submit\" value=\"Perform action on all checked files\"><br>\n";
            echo "</form>\n";
        }

        mysql_free_result($result);
        echo "<br>";
        echo $navText;
    }
}

function nrOfSongs()
{
    return mysql_result( tunez_query("SELECT count(*) as aantal from songs"),0,aantal);
}

function showBox($title, $text)
{
    echo "<div id=\"message\"><h2>$title</h2>$text</div>";
}

function random_song_ids($number) {
    // Picks minimum song_id
    $query = "select min(song_id) as min from songs";
    $result = tunez_query($query);
    $row = mysql_fetch_object($result);
    $min = $row->min;
   
    // Picks maximum song_id
    $query = "select max(song_id) as max from songs";
    $result = tunez_query($query);
    $row = mysql_fetch_object($result);
    $max = $row->max;
    
    $random_song_ids = array();
    $mysong = new Song(NULL, NULL);
    
    $theoretical_limit = $max - $min;
    if ($number > $max - $min) {
        $number = $max - $min;
    }
    //print "number = $number<br>";
    while($number > 0) {
        $mysong->song_id = rand($min, $max);
        // Kind of a hack, potential DOS if the user creates a song_id of a
        // really high number to fake the system into checking lots of stuff
        // FIXME
        while(($mysong->valid_song_id()==FALSE) && ($count < $theoretical_limit)) {
            $mysong->song_id = rand($min, $max);
            $count++;
        }
        $random_song_ids[] = $mysong->song_id;
        $number--;
    }
    return $random_song_ids;
}

function dropDownNumbers ($name, $firstOption, $start, $end, $selected)
{
    echo "<select class=\"dropdown\" name=\"$name\">";
    echo "<option value=\"$firstOption\">$firstOption";
    for ($i=$start; $i <= $end; $i++)
    {
        if(strlen($i)==1) $i = '0'.$i;
        echo "\n<option value=\"$i\"";
        if ($i == $selected)
        {
            echo " selected" ;
        }
        echo ">$i";
    }
    echo "</select>";
}

/*
   function dropdown_mood($selected)
   {
   echo "<select class=\"dropdown\" name=mood_id>\n";
   $kweerie = "SELECT mood_id, mood_name FROM moods ORDER BY mood_name";
   $result = tunez_query($kweerie);
   echo "<option value=\"0\">Add to mood:";

   while($row = mysql_fetch_array($result))
   {
   echo "<option value=\"" . $row[0] . "\"";
   if ($row[0] == $selected) { echo " SELECTED"; }
   echo ">" . $row[1];
   }
   echo "\n</select>\n";
   }
 */

function findinarray($array, $searchvalue) 
{ 
    for ($i = 0; $i < count($array); $i++) 
    { 
        if (stristr($array[$i],$searchvalue)) return $i; 
    } 

    return 0; 
} 

function like_artist_select_html($artist_name, $artist_id) {
    $query = "SELECT * from artists where artist_name like '%" . addslashes($artist_name) . "%' order by artist_name";
    $result = tunez_query($query) or die(mysql_error());
    print "<OPTION value='' selected></OPTION><br>\n";
    while ( $row = mysql_fetch_object($result)) {
        print "<OPTION value='$row->artist_id' ";
#if ( $row->artist_id == $artist_id) 
#        print "selected ";
        print ">" . $row->artist_name . "</OPTION><br>\n";
    }
}

function like_album_select_html($album_name, $album_id) {
    $query = "SELECT * from albums where album_name like '%" . addslashes($album_name) . "%' order by album_name";
    $result = tunez_query($query) or die(mysql_error());
    print "<OPTION value='' selected></OPTION><br>\n";
    while ( $row = mysql_fetch_object($result)) {
        print "<OPTION value='$row->album_id' ";
#if ( $row->album_id == $album_id) 
#        print "selected ";
        print ">" . $row->album_name . "</OPTION><br>\n";
    }
}



#### genre_select_html($genre)
# Function:  Outputs required inner fields of a <SELECT> entry
# Inputs: $genre (optional)
#         If $genre is included the appropriate genre option will have a
#         selected flag added to it so that the appropriate genre is selected
function genre_select_html($genre) {
    $kweerie = "SELECT * from genre order by genre_name";
    $result = tunez_query($kweerie) or die(mysql_error());
    while ($row = mysql_fetch_array($result)) {
#list ($genre_no, $genre_title) = each ($row);
        if ($genre==$row['genre_id'])
            print "\t\t<OPTION value=\"" . $row['genre_id']. "\" selected>" . $row['genre_name'] . "</OPTION>\n";
        else 
            print "\t\t<OPTION value=\"" . $row['genre_id']. "\">" . $row['genre_name'] . "</OPTION>\n"; 
    }
}


function dropdown_users($selected)
{
    $kweerie = "SELECT * FROM users ORDER BY user";
    $result = tunez_query($kweerie);

    echo "<select class=\"dropdown\" name=\"selectedUser\"
        onChange=\"users.submit()\">\n";
    echo "<option value=\"0\">All Users";

    while($user = mysql_fetch_object($result))
    {
        echo "<option value=\"$user->user_id\"";
        if ($user->user_id == $selected) 
            echo " SELECTED"; 
        echo ">$user->user";
    }
    echo "\n</select>\n";
}

function twoColumnsDisplay($data)
{
    $text =  "<table width=\"100%\"><tr><td valign=top>";

    $breakpoint = ceil((count($data) / 2));
    $breakpoint++;
    $a = 0;

    foreach ($data as $key => $line)
    {
        $a++;
        if ($a == $breakpoint) { $text .= "</td><td valign=top>"; } 
        $text .= $line;
    } 
    $text .=  "</td></tr></table>";
    return $text;
}

function sql_order($order_by, $sort_dir) {
    global $sortable_fields;
    $sql = " ORDER BY ";
    if ($order_by) {
        if (in_array($order_by, $sortable_fields)) {
            $sql.= "$order_by";
        }
        else {
            $sql.= "artists.artist_name, albums.album_name, songs.songtitle";
        }
        if ($sort_dir=="ASC") {
            $sql.= " ASC";
        }
        else {
            $sql.= " DESC";
        }
    }
    else {
        $sql.= "artists.artist_name, albums.album_name, songs.songtitle";
    }
    return $sql;
}


// Thanks to garyb@fxt.com for this function (which I found at php.net)
// 
// http://www.php.net/manual/tw/function.print-r.php
// His note about it on php.net:
// 
// I have used a little recursive function to display arrays and objects for a
// long time, and I like its compact format better than print_r() especially for
// large complex variables.  I use it in a debugging context so it has the
// ability to print to a file as well.  I won't include the setup stuff - it's 
// available if anyone wants it.  Interestingly, if you try it on $GLOBALS it 
// recognizes functions as objects but there's nothing accessible inside them.
function pray ($data, $functions=0) {
    //    pray - short for print_array
    //    Traverse the argument array/object recursively and print an ordered list.
    //    Optionally show function names (in an object)
    //    NB: This is a *** HUGE SECURITY HOLE *** in the wrong hands!!
    //        It prints all the variables it can find
    //        If the argument is $GLOBALS this will include your database connection information, magic keys and session data!!

    if($functions!=0) { $sf=1; } else { $sf=0 ;}    // This kluge seemed necessary on one server.
    if (isset ($data)) {
        if (is_array($data) || is_object($data)) {
            if (count ($data)) {
                echo "<OL>\n";
                while (list ($key,$value) = each ($data)) {
                    $type=gettype($value);
                    if ($type=="array" || $type == "object") {
                        printf ("<li>(%s) <b>%s</b>:\n",$type, $key);
                        pray ($value,$sf);
                    } elseif (eregi ("function", $type)) {
                        if ($sf) {
                            printf ("<li>(%s) <b>%s</b> </LI>\n",$type, $key, $value);
                            //   There doesn't seem to be anything traversable inside functions.
                        }
                    } else {
                        if (!$value) { $value="(none)"; }
                        printf ("<li>(%s) <b>%s</b> = %s</LI>\n",$type, $key, $value);
                    } }
                    echo "</OL>end.\n";
            } else {
                echo "(empty)";
            } } }
}    // function

function selected($stringa, $stringb) {
    if ($stringa == $stringb)
        print "selected";
}


?>
