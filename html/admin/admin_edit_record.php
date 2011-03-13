<?php
# admin_edit_record.php
#
# This allows group editing and manipulation of song records

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

$NoRefresh = true;
// when this is set, auto-refresh at end of song playback will not occur

require("../tunez.inc.php");
require("../database.inc.php");
require_once($_CONF['path_system'] . "classes/song.class.php");
require_once($_CONF['path_system'] . "classes/artist.class.php");
require_once($_CONF['path_system'] . "classes/album.class.php");

if(!empty($_POST)) {
    if (!($_SESSION['perms']['p_select_edit'])) {
        header(access_denied());
        die;
    }
    //$title = "Updating song records";
    //require("../header.inc.php");
    if(empty($_POST['num_entries']))
        die("Number of entries not specified");
    $_POST['num_entries'] = (int) $_POST['num_entries'];
    if($g_debug)
        print "There were " . $_POST['num_entries'] . " possible entries<br>";
    for ($i=1; $i <= $_POST['num_entries']; $i++) {
        $foo="edit_entry_$i";
        if (!isset($_POST[$foo])) {
            continue;
        }
        $song_id = (int) $_POST[$foo];
        $song = new Song($song_id, NULL);
       
        if (!get_magic_quotes_gpc()) {
            $song->songtitle = addslashes($_POST[$foo . "_songtitle"]);
        }
        else {
            $song->songtitle = $_POST[$foo . "_songtitle"];
        }

        // song characteristics which are safe to set here
        $song->genre_id = (int) $_POST[$foo . "_genre_id"];
        $song->year = (int) $_POST[$foo . "_year"];
        $song->track = (int) $_POST[$foo . "_track"];
        $song->playInRandomMode = (int) $_POST[$foo . "_playInRandomMode"];
        $song->update_id3 = 1;
        
        if(!empty($_POST[$foo . "_artist_id"])) {
            $song->artist_id = (int) $_POST[$foo . "_artist_id"];
        }
        else {
            if (!get_magic_quotes_gpc()) {
                $song->artist_name=addslashes($_POST[$foo . "_artist_name"]);
            }
            else {
                $song->artist_name=$_POST[$foo . "_artist_name"];
            }
        }
        if(!empty($_POST[$foo . "_album_id"])) {
            $song->album_id = (int) $_POST[$foo . "_album_id"];
        }
        else {
            if (!get_magic_quotes_gpc()) {
                $song->album_name=addslashes($_POST[$foo . "_album_name"]);
            }
            else {
                $song->album_name=$_POST[$foo . "_album_name"];
            }
        }

        if(empty($song->artist_name) && empty($song->artist_id)) {
            die("You must provide either an artist name or choose from the dropdown box");
        }
        if(empty($song->album_name) && empty($song->album_id)) {
            die("You must provide either an album name or choose from the dropdown box");
        }
        
        if (!empty($song->artist_name)) {
            // the artist name was changed (override dropdown box)
            // Holding off on new bugfixes here...
            // TODO: Obsolete the old artist_id

            $song->artist_id = fetch_artist_id($song->artist_name, TRUE, FALSE);
        }
        else {
            // they picked from the dropdown list
            // TODO: obsolete old artist_id...
        }
        
        if (!empty($song->album_name)) {
            // the artist name was changed (override dropdown box)
            //obsolete_album_id($song->album_id);
            // TODO: Obsolete old album_id
            
            $song->album_id = fetch_album_id($song->album_name, TRUE, FALSE);
        }
        else {
            // they picked from the dropdown list
            // TODO: obsolete old album_id...
        }

        $song->write_data_to_db(Array(
                    "artist_id", "album_id", "songtitle", "genre_id",
                    "year", "track", "playInRandomMode", "update_id3"));
    }
    $_SESSION['messageTitle']	= "All edits succeeded";
    $_SESSION['messageBody']	= "Your songs have been edited";
    if (empty($_POST['prev_page'])) {
        header("Location: " . $_CONF['url']);
    }
    else {
        header("Location: " . $_POST['prev_page']);
    }
}
elseif (!empty($_GET['checkbox_action'])) {
    list($get, $checked_song_ids, $foos) = determine_checked_song_ids($_GET);
    if (empty($checked_song_ids)) {
        $_SESSION['messageTitle']   = "Error!";
        $_SESSION['messageBody']    = "You didn't select any songs";
        require("../header.inc.php");
        require("../footer.inc.php");
    }
    else {
        if ($_GET['checkbox_action']=="edit") {
            if (!($_SESSION['perms']['p_select_edit'])) {
                header(access_denied());
                die;
            }
            $title = "Edit That Stuff!";
            require("../header.inc.php");
            display_top_edit_info($get);
            apply_action_to_songids($checked_song_ids, "display_edit_info", $foos);
            display_bottom_edit_info();
            require("../footer.inc.php");
        }
        elseif ($_GET['checkbox_action']=="vote" or
                $_GET['checkbox_action']=="rovote" ) {
            if (!($_SESSION['perms']['p_select_vote'])) {
                header(access_denied());
                die;
            }
            require_once("../vote.inc.php");
            require_once($_CONF['path_system'] . "classes/PQueue.class.php");
            if ($_GET['checkbox_action']=="rovote") {
                shuffle($checked_song_ids);
            }
            if ($_CONF['voting_mode'] == "classic") {
                dovote($checked_song_ids);
            }
            elseif($_CONF['voting_mode'] == "complex") {
                newvote($checked_song_ids);
            }
            header("Location: " . $_SERVER[HTTP_REFERER]);
        }
        elseif ($_GET['checkbox_action']=="read") {
            if (!($_SESSION['perms']['p_select_edit'])) {
                header(access_denied());
                die;
            }
            apply_action_to_songids($checked_song_ids, "readsong");
            $_SESSION['messageTitle'] = "Comment tags read into database";
            $_SESSION['messageBody'] = "";
            header("Location: $_SERVER[HTTP_REFERER]");
        }
        elseif ($_GET['checkbox_action']=="write") {
            if (!($_SESSION['perms']['p_select_edit'])) {
                header(access_denied());
                die;
            }
            $returntext = apply_action_to_songids($checked_song_ids, "writesong");
            if(!empty($returntext)) {
                $_SESSION['messageTitle'] = "Error writing song files!";
                $_SESSION['messageBody'] = $returntext;
            }
            else {
                $_SESSION['messageTitle'] = "Song files have been updated with the proper tag data";
                $_SESSION['messageBody'] = "";
            }
            header("Location: $_SERVER[HTTP_REFERER]");
        }
        elseif ($_GET['checkbox_action']=="delete") {
            if (!($_SESSION['perms']['p_select_edit'])) {
                header(access_denied());
                die;
            }
            apply_action_to_songids($checked_song_ids, "deletesong");
            $_SESSION['messageTitle'] = "Files have been marked for deletion";
            $_SESSION['messageBody'] = "";
            header("Location: $_SERVER[HTTP_REFERER]");
        }
        elseif ($_GET['checkbox_action']=="hide") {
            if (!($_SESSION['perms']['p_select_edit'])) {
                header(access_denied());
                die;
            }
            apply_action_to_songids($checked_song_ids, "hidesong");
            $_SESSION['messageTitle'] = "Files have been hidden";
            $_SESSION['messageBody'] = "";
            header("Location: $_SERVER[HTTP_REFERER]");
        }
	elseif ($_GET['checkbox_action']=="rnd") {
	    if (!($_SESSION['perms']['p_select_edit'])) {
		header(access_denied());
		die;
	    }
	    apply_action_to_songids($checked_song_ids, "onrnd");
	    $_SESSION['messageTitle'] = "Files have been added to random";
	    $_SESSION['messageBody'] = "";
	    header("Location: $_SERVER[HTTP_REFERER]");
	}
        elseif ($_GET['checkbox_action']=="nornd") {
            if (!($_SESSION['perms']['p_select_edit'])) {
                header(access_denied());
                die;
            }
            apply_action_to_songids($checked_song_ids, "offrnd");
            $_SESSION['messageTitle'] = "Files have been removed from random";
            $_SESSION['messageBody'] = "";
            header("Location: $_SERVER[HTTP_REFERER]");
        }
        else {
            die("invalid checkbox_action");
        }
    }
}


function determine_checked_song_ids($get) {
    if (!empty($get['song_id'])) {
        // they did a special edit one song only deal...
        $get['num_entries'] = 1;
        $get['edit_entry_1'] = (int) $_GET['song_id'];
    }
    if (empty($get['num_entries'])) {
        die("you didn't enter form data correctly fool!");
    }

    if ($get['num_entries'] > 1000) {
        die("Number of entries/page can't be more than 1000!!!");
    }
    for ($i=1; $i <= $get['num_entries']; $i++) {
        $foo = "edit_entry_$i";
        if (!isset($get[$foo])) {
#print "$i doesn't exist<br>";
            continue;
        }
        $song_id = (int) $get[$foo];
        $checked_song_ids[] = $song_id;
        $foos[] = $foo;
    }
    return Array($get, $checked_song_ids, $foos);
}

function apply_action_to_songids($checked_song_ids, $function_to_call, $opt_paramaters=NULL) {
    $returntext = NULL;
    if (function_exists($function_to_call)) {
        for($i=0; $i < sizeof($checked_song_ids); $i++) {
            $returntext .= $function_to_call($checked_song_ids[$i], $opt_paramaters[$i]);
        }
        return $returntext;
    }
    die("Fatal error, function doesn't exist");
}


function display_top_edit_info($get) {
    ?>
        <div class="formdiv">
        <h4>Please be carefull editing these characteristics</h4>
        <table cellpadding=3 border=0 cellspacing=1>
        <form action="<?=$_SERVER[PHP_SELF]?>" method="post">
        <input type="hidden" name="num_entries" value="<?=$get['num_entries']?>">
        <input type="hidden" name="prev_page" value="<?= $_SERVER[HTTP_REFERER] ?>">
        <?php
}

function display_bottom_edit_info() {
    print "</table>";
    print "<input class=\"button\" type=submit value=\"Change stuff!\"></form></div>\n<br>\n";
}


function readsong($song_id) {
    $song = new Song($song_id, NULL);
    $song->read_data_from_db("filename,type");
    $song->read_data_from_file(TRUE);
    $song->write_data_to_db($song->sql_song_fields_for_id3s);
}

function writesong($song_id) {
    $song = new Song($song_id, NULL);
    $song->read_data_from_db();
    if (!$song->write_data_to_file()) {
        return "Song: $song->songtitle could not be written";
    }
}

function deletesong($song_id) {
    $song = new Song($song_id, NULL);
    $song->status = "delete";
    $song->write_data_to_db(Array("status"));
}

function hidesong($song_id) {
    $song = new Song($song_id, NULL);
    $song->status = "hide";
    $song->write_data_to_db(Array("status"));
}

function onrnd($song_id) {
    $song = new Song($song_id, NULL);
    $song->playInRandomMode = "1";
    $song->write_data_to_db(Array("playInRandomMode"));
}

function offrnd($song_id) {
    $song = new Song($song_id, NULL);
    $song->playInRandomMode = "0";
    $song->write_data_to_db(Array("playInRandomMode"));
}

function display_edit_info($song_id, $foo) {
    $kweerie = "SELECT * from songs LEFT JOIN albums on songs.album_id=albums.album_id LEFT JOIN artists on songs.artist_id=artists.artist_id LEFT JOIN genre on songs.genre_id=genre.genre_id where songs.song_id='$song_id'";
    $result = tunez_query($kweerie);
    $row = mysql_fetch_object($result);
    ?>
        <input type="hidden" name="<?=$foo?>" value="<?=$song_id?>">
        <tr>
        <td>FileName:</td>
        <!--<td><input type="text" size="40" name="<?=$foo?>_filename" value="<?=$row->filename?>"></td>-->
        <td><?=$row->filename?></td>
        <?php
        ?>
        </tr>
        <tr>
        <td>Title:</td>
        <td><input type="text" name="<?=$foo?>_songtitle"
        size='40' maxlength='128' value="<?php echo htmlspecialchars($row->songtitle); ?>"></td>
        </tr>
        <tr>
        <td>Artist:</td>
        <td><input type="text" name="<?=$foo?>_artist_name" size='40'
        maxlength='128' value="<?php echo htmlspecialchars($row->artist_name); ?>">
        </td>
        </tr>
        <tr>
        <td>Artist Select:</td>
        <td><select name="<?=$foo?>_artist_id">
        <?php
        like_artist_select_html($row->artist_name, $row->artist_id);
    ?>
        </select></td>
        </tr>
        <tr>
        <td>Album:</td>
        <td><input type="text" name="<?=$foo?>_album_name" size='40'
        maxlength='255' value="<?php echo htmlspecialchars($row->album_name); ?>"</td>
        </tr>
        <tr>
        <td>Album Choose:</td>
        <td><select name="<?=$foo?>_album_id">
        <?php
        like_album_select_html($row->album_name, $row->album_id);
    ?>
        </select></td>
        </tr>
        <tr>
        <td>Genre:</td>
        <td><select name="<?=$foo?>_genre_id">
        <?php
        genre_select_html($row->genre_id);
    ?>
        </select></td>
        </tr>
        <tr>
        <td>Track:</td>
        <td><input type="text" name="<?=$foo?>_track" size="3" maxlength="3" value="<?=$row->track?>"></td>
        </tr>
        <tr>
        <td>Year:</td>
        <td><input type="text" name="<?=$foo?>_year" size='4' maxlength='4' value="<?=$row->year?>"></td>
        </tr>
        <tr>
        <td>Plays in Random Mode:</td>
        <td>
        <!-- were class="radiobutton" however . .this looked ghetto
        -->
        <input type="radio" name="<?=$foo?>_playInRandomMode" value="1"
        <?php
        if($row->playInRandomMode == 1) echo " CHECKED"; ?>
            >Yes&nbsp;&nbsp;

    <input type="radio" name="<?=$foo?>_playInRandomMode" value="0"
        <?php if($row->playInRandomMode == 0) echo " CHECKED"; ?>
        >No</p>
        </td>

        <?php

        print "</tr>";
} 






/*

   }

#<h4>:: In which mood should this song be played?</h4>
#<p>Note: This is most likely to be deprecated in the future... It is lame.
#<?php
#$kweerie = "SELECT g.mood_id, mood_name FROM goes_with_mood g LEFT JOIN moods m ON g.mood_id = m.mood_id WHERE song_id=$song_id";

#$result = mysql_db_query("tunez", $kweerie);

#echo "<table>";
#while ($row = mysql_fetch_array($result))
#{
#	$mood_id	= $row[mood_id];
#	$mood_name	= $row[mood_name];

#	echo "<tr>\n";
#	echo "<td><a href=\"admin_edit_record_remove_mood.php?mood_id=$mood_id&song_id=$song_id\"><b>X</b></a></td>\n";
#	echo "<td>$mood_name</td>\n";
#	echo "</tr>\n";
#}
#echo "</table>";

#mysql_free_result($result);	

#dropdown_mood('')
# (end huge else)
//javascript code:
//        <select name=year
//      onChange="populate(this.form,this.form.month.selectedIndex);">
 */

?>


