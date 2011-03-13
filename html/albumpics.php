<?php
# albumpics.php
# 
# Uses amazon.inc.php to get and assign album data
# 
# Disclaimer:
# I bear no responsibility whatsoever for your use of this code and the accompaning
# code in amazon.inc.php.  I provide this only for educational purposes only.

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
require_once($_CONF['path_system'] . "classes/song.class.php");
require("amazon.inc.php");

if (empty($_GET['song_id'])) {
    die("you must provide a songid");
}
$song_id = (int) $_GET['song_id'];

if (!empty($_GET['store'])) {
    if (empty($_GET['album_id'])) {
        die("critical form data missing");
    }

    $album_id = (int) $_GET['album_id'];
    if (!get_magic_quotes_gpc()) {
        $_GET['album_name'] = addslashes($_GET['album_name']);
        $_GET['small'] = addslashes($_GET['small']);
        $_GET['large'] = addslashes($_GET['large']);
        $_GET['amazon_url'] = addslashes($_GET['amazon_url']);
    }
    
    require($_CONF['path_system'] . "classes/album.class.php");
    
    $album = new album($album_id);
    if(!$album->load()) {
        die("invalid album_id");
    }
    if ($album->album_name == "Unknown") {
        require($_CONF['path_html'] . "database.inc.php");

        // First determine if we need a new ID or not and if so appropriate one
        $newid = fetch_album_id($_GET['album_name'], TRUE, FALSE);

        // If it's a new album or if it already has existed, we are going to
        // overwrite what it has with the Amazon URL and pics
        $newalbum = new album($newid);
        $newalbum->album_name = $_GET['album_name'];
        $newalbum->small_album_cover = $_GET['small'];
        $newalbum->large_album_cover = $_GET['large'];
        $newalbum->amazon_url = $_GET['amazon_url'];
        $newalbum->save();

        // Now update the song to point it to the new album
        $song_to_change = new song($song_id, NULL);
        $song_to_change->album_id = $newid;
        $song_to_change->write_data_to_db("album_id");
        
        // Determine if the old album_id is obsolete and if so delete it
        obsolete_album_id($album_id);
    }
    else {
        $album->small_album_cover = $_GET['small'];
        $album->large_album_cover = $_GET['large'];
        $album->amazon_url = $_GET['amazon_url'];
        $album->save();
    }

    header("Location: " . $_GET['referer']);
    return;
}
else {
    require("header.inc.php");
    
    $song = new song($song_id, NULL);
    $song->read_data_from_db();
    $album_name = &$song->album_name;

    if (empty($song->artist_name) OR empty($album_name)) {
        die("I'm not even going to bother with a blank album or artist name");
    }
    if ($album_name == "Unknown") {
        print "<p><h2>Notice: The song \"$song->songtitle\" currently has it's album name set to \"Unknown\".  If 
        you procede to associate any of the following album covers with that song the album name of the song
        will automatically change to the album name shown beside the album cover.</h2><br>";
        $album_name = "";
    }

    print "Attempting to lookup:<br>";
    print "Artist: $song->artist_name<br>";
    print "Album: $song->album_name<br>";
    print "<p>Please be patient, this may take a moment...</p>";
    flush();
    
    $amazon_info = get_album_covers(array(artist => $song->artist_name, album => $album_name));
    if (empty($amazon_info)) {
        print "No album art was found for this album.";
    }
    else {
        print "<table>";
        foreach ($amazon_info as $album) {
            display_album_row($album, $song);
        }
        print "</table>";
    }

    require("footer.inc.php");
}

function display_album_row($album, $song) {
    $album_name = $album['album_name'];
    ?>
    <tr>
    
    <td><p>
    Artist Name: <?= $album['artist_name'] ?><br>
    Album Name: <?= $album_name ?>
    </p><p>
    <img src=" <?= $album['small_cover_img'] ?>"</img>
    <?php
        print "Click <a href=\"" . $_CONF['url'] . "albumpics.php?store=1&song_id=$song->song_id&album_name=$album_name&album_id=$song->album_id&small=" . 
        htmlentities($album['small_cover_img']) .
        "&large=" . htmlentities($album['large_cover_img']) . "&amazon_url=" . htmlentities($album['amazon_url']) . 
        "&referer=" . htmlentities($_SERVER[HTTP_REFERER]) . "\"> here </a> to store these into the database.";
        print "</p>";
    ?>
    </td>
    
    <td>
    <img src=" <?= $album['large_cover_img'] ?>"</img>
    </td>

    </tr>
    <?php
    }

?>

