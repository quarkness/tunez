<?php
# browse_genre.php
#
# Lists genres in file.

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
$title = "Browse by genre";
require("header.inc.php");


if(isset($_GET["genre_id"]))
{
    $genre_id = (int) $_GET['genre_id'];

    showBox("Browsing the " . $_GET['genre_name'] . " genre", "");
    $kweerie = "SELECT $typical_song_select from songs LEFT JOIN albums on
        songs.album_id=albums.album_id LEFT JOIN artists on
        songs.artist_id=artists.artist_id where songs.genre_id=$genre_id AND $show_active";
    $kweerie .= sql_order($_GET['order_by'], $_GET['sort_dir']);
    listSongs($kweerie);
}
else
{
    $kweerie = "select distinct songs.genre_id,genre_name from songs LEFT JOIN
    genre on songs.genre_id=genre.genre_id order by genre.genre_name";
    
    $r = mysql_query($kweerie) or die(mysql_error());
    $length = mysql_num_rows($r);
    $text .=  "<table width=300><tr><td valign=top>";

    for($i=0; $i<$length; $i++)
    {
        $gen = mysql_fetch_row($r);
        $genre_id = $gen[0];
        $genre_name = $gen[1];
        $text .= "<a
        href=\"browse_genre.php?genre_id=$genre_id&genre_name=$genre_name\"
        class=nav>$genre_name</a><BR>";
    }

    $text .=  "</td></tr></table>";
    showBox ("Browse the songs by genre", $text);
}
require("footer.inc.php");
?>
