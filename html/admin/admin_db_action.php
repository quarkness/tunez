<?php
# admin_db_action.php
#
# This is a wrapper page for database requests on a given song_id

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

require("../tunez.inc.php");
require($_CONF['path_system'] . "classes/song.class.php");

if(!($_SESSION['perms']['p_select_edit'])) {
    header(access_denied());
    die;
}

if(empty($_GET['song_id'])) {
    die("no song id passed");
}

$song_id = (int) $_GET['song_id'];

if($_GET['action']=="delete") {
    tunez_query("UPDATE songs set status=\"delete\" where song_id=$song_id");
    $_SESSION['messageTitle'] = "Success";
    $_SESSION['messageBody'] = "Song_id $song_id has been marked for deletion";
}
elseif($_GET['action']=="hide") {
   tunez_query("UPDATE songs set status=\"hide\" where song_id=$song_id");
   $_SESSION['messageTitle'] = "Success";
   $_SESSION['messageBody'] = "Song_id $song_id was successfully hidden";
}
elseif($_GET['action']=="normal") {
   tunez_query("UPDATE songs set status=\"normal\" where song_id=$song_id");
   $_SESSION['messageTitle'] = "Success";
   $_SESSION['messageTitle'] = "Song_id $song_id was returned to normal status";
}
elseif($_GET['action']=="readtag") {
    $song = new Song($song_id, NULL);
    $song->read_data_from_db("filename,type");
    $error = $song->read_data_from_file(TRUE);
    if($error) {
        $_SESSION['messageTitle'] = "Error!";
        $_SESSION['messageBody'] = "There was an error reading the file: $error";
    }
    else {
        $_SESSION['messageTitle'] = "Success";
        $_SESSION['messageBody'] = "The tag was successfully read into the database";
    }
    $song->write_data_to_db($song->sql_song_fields_for_id3s);
}
elseif($_GET['action']=="writetag") {
    $song = new Song($song_id, NULL);
    $song->read_data_from_db();
    if(!$song->write_data_to_file()) {
        $_SESSION['messageTitle'] = "Error!";
        $_SESSION['messageBody'] = "There was an error updating the ID3 tag on the song";
    }
    else {
        $_SESSION['messageTitle'] = "Success";
        $_SESSION['messageBody'] = "The database information on the song was successfully written out to the audio file";
    }
    $song->update_ID3=0;
    $song->write_data_to_db("update_ID3");
}
else {
    die("no valid action, you suck");
}


header("Location: $_SERVER[HTTP_REFERER]");
?>
