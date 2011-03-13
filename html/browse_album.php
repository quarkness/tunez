<?php
# browse_album.php
#
# Lists albums and lets user vote.

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
$typical_song_select = $_CONF['typical_song_select'];

if(!empty($_GET['selected_album'])) {
    $selected_album = (int) $_GET['selected_album'];
    $order_by = $_GET['order_by'];
    $sort_dir = $_GET['sort_dir'];

    if (!get_magic_quotes_gpc()) {
        $order_by = addslashes($order_by);
        $sort_dir = addslashes($sort_dir);
    }
    
    require($_CONF['path_system'] . "classes/album.class.php");
    
    $album = new album($selected_album);
    $album->load();
    if (empty($order_by)) {
        $order_by="track";
    }
    if (empty($sort_dir)) {
        $sort_dir="ASC";
    }
    
    $kweerie="SELECT track,$typical_song_select from songs
        LEFT JOIN albums on songs.album_id=albums.album_id 
        LEFT JOIN artists on songs.artist_id=artists.artist_id 
        WHERE songs.album_id=albums.album_id AND
        songs.album_id=$album->album_id AND $show_active ";
    $kweerie .= sql_order($order_by, $sort_dir);

    $title = "Browse by Album: $album->album_name";
    require("header.inc.php");

    if (!empty($album->large_album_cover)) {
        if (!empty($album->amazon_url)) {
            echo "<a href=\"" . htmlentities($album->amazon_url) . "\">";
            echo "<img src=\"$album->large_album_cover\"></img>";
            echo "</a>";
        }
        else {
            echo "<img src=\"$album->large_album_cover\"></img>";
        }
    }

    listSongs($kweerie);
}
else {
    $title = "Browse by Album";
    require("header.inc.php");
    $kweerie = "select distinct  
        songs.album_id,album_name,songs.artist_id,artist_name,small_album_cover from songs LEFT
        JOIN albums on songs.album_id=albums.album_id LEFT JOIN artists on
        songs.artist_id=artists.artist_id
        WHERE album_name!='Unknown' ";

    $pixonly = (int) $_GET['pixonly'];
    $user_sort_order = $_GET['sort_order'];
    
    print"<h3>Order by: [ ";
    if ($user_sort_order=="artist") {
        $sort_order = "artist_name";
        print "<a href=\"" . $_CONF['url'] . 
            "browse_album.php?pixonly=$pixonly&sort_order=album\">album</a> | artist";
    }
    else {
        $sort_order = "album_name";
        print "album | <a href=\"" . $_CONF['url'] . 
            "browse_album.php?pixonly=$pixonly&sort_order=artist\">artist</a>";
    }
        
    print " ]</h3>";
    print "<h3>Show: [ ";

    if(!empty($_GET['pixonly'])) {
        $kweerie .= "AND small_album_cover!=''";
        print "<a href=\"" . $_CONF['url'] . "browse_album.php?pixonly=0&sort_order=$user_sort_order\">all albums</a> | only albums with images ]</h3>";
    }
    else print "all albums | <a href=\"" . $_CONF['url'] . "browse_album.php?pixonly=1&sort_order=$user_sort_order\">only albums with art</a> ]</h3>";

    $kweerie .= "ORDER by $sort_order";
    $result = tunez_query($kweerie);

    if($_GET['pixonly']) {
        print "<table>\n";
        $counter=0;
	    $columns=4;    // table width hardcoded... kinda lame.
        while($album_row = mysql_fetch_object($result)) {
            $albumurl= $_CONF['url'] .
                "browse_album.php?artist_id=" . $album_row->artist_id .
                "&order_by=track&sort_dir=ASC&selected_album=" . $album_row->album_id .
                "&artist_name=" . $album_row->artist_name .
                "&album_name=" . $album_row->album_name;
            if($counter%$columns==0) {
                print "<tr>\n";
            }
            print "<td style=\"text-align: center; vertical-align: top;\">\n";
            print "<div><a href=\"$albumurl\"><img src=\"" . $album_row->small_album_cover . "\"</img></a></div>\n";
            print "<div>" . $album_row->artist_name . "<br>\n";
            print "<a href=\"" . $albumurl . "\" class=\"nav\">" . $album_row->album_name . "</a>\n</div>\n";
            print "</td>\n";
            if($counter%$columns==($columns-1)) {
                print "</tr>\n";
            }
            $counter++;
        }
        print "</table>";
    }
    else {
        while($album_row = mysql_fetch_object($result))
        {
            $albumurl= $_CONF['url'] . 
                "browse_album.php?artist_id=" . $album_row->artist_id .
                "&order_by=track&sort_dir=ASC&selected_album=" . $album_row->album_id .
                "&artist_name=" . $album_row->artist_name .
                "&album_name=" . $album_row->album_name;

            if (!empty($album_row->small_album_cover)) {
                print "<a href=\"$albumurl\"><img src=\"" . $album_row->small_album_cover . "\"></img></a>";
            }
            print $album_row->artist_name . " - <a href=\"" . $albumurl . "\" class=\"nav\">" 
                . $album_row->album_name . "</a><br>";
        }
    }

    //$content = twoColumnsDisplay($data);
    //showBox ("Browse the songs by Album",'');
}
require("footer.inc.php");
?>
