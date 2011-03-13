<?php
# edit_artistalbum.php
#
# Designed to allow manipulation to artist / album names

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

require("tunez.inc.php");
if (!($_SESSION['perms']['p_select_edit'])) {
    header(access_denied());
    die;
}
$typical_song_select = $_CONF['typical_song_select'];

if(!empty($_POST['new_artist_name'])) {
    $_POST['artist_id'] = (int) $_POST['artist_id'];
    if (!get_magic_quotes_gpc()) {
        $_POST['new_artist_name'] = addslashes($_POST['new_artist_name']);
    }
    
    require($_CONF['path_system'] . "classes/artist.class.php");
    $artist = new artist($_POST['artist_id']);
    if (!($artist->load())) {
        // if the artist_id is invalid
        $_SESSION['messageTitle'] = "Error";
        $_SESSION['messageBody'] = "Invalid Artist ID!  Change failed!";
        header("Location: {$_SERVER['HTTP_REFERER']}");
        return FALSE;
    }
    
    $artist->artist_name = $_POST['new_artist_name'];
    // check to make sure artist name doesn't already exist in db
    if ($changeto = $artist->lookup_name($artist->artist_name)) {
        $query = "UPDATE songs set artist_id=$changeto WHERE artist_id=$artist->artist_id";
        tunez_query($query);
        $artist->delete_if_obsolete();

        $_SESSION['messageTitle'] = "Success";
        $_SESSION['messageBody'] = "The artist name you chose already existed so all the records have been merged";
        $newurl = $_SERVER['PHP_SELF'] . "?artist_id=$changeto";
        header("Location: {$newurl}");
        return TRUE;
    }
    elseif (!($artist->save())) {
        // if the save failed for some reason
        $_SESSION['messageTitle'] = "Error";
        $_SESSION['messageBody'] = "The save on the new artist_name failed!";
        header("Location: {$_SERVER['HTTP_REFERER']}");
        return FALSE;
    }
    else {
        $_SESSION['messageTitle'] = "Success";
        $_SESSION['messageBody'] = "The artist name has been successfully changed";
        header("Location: {$_SERVER['HTTP_REFERER']}");
    }
    return TRUE;
}
elseif(!empty($_GET['artist_id'])) {
    require($_CONF['path_system'] . "classes/artist.class.php");
    $artist_id = (int) $_GET['artist_id'];
    
    $artist = new artist($artist_id);
    if (!($artist->load())) {
        // if the artist_id is invalid
        $_SESSION['messageTitle'] = "Error";
        $_SESSION['messageBody'] = "Invalid Artist ID";
    }
    $title = "Editing artist $artist->artist_name (artist_id $artist->artist_id)";
    require("header.inc.php");
      // present the editing stuff
    ?>
        <p><b>Changing the artist name below will modify it for each of the songs below</b></p>
        <form action="<?= $_SERVER[PHP_SELF] ?>" method="post">
        <p>
            Artist Name:
            <input type="text" class="field" name="new_artist_name" size="40" value="<?php 
                echo htmlentities($artist->artist_name); ?>">
            <input type="hidden" name="artist_id" value="<?= $artist->artist_id?>">
            <input type="submit" value="Modify">
        </p>
        </form>
        <?php

       $query = "SELECT $typical_song_select FROM songs LEFT JOIN
            artists ON songs.artist_id=artists.artist_id LEFT JOIN
            albums ON songs.album_id=albums.album_id WHERE
            artists.artist_id=$artist->artist_id";
        $query .= sql_order($_GET['order_by'],$_GET['sort_dir']);
        listSongs($query);

        require("footer.inc.php");
}

?>
