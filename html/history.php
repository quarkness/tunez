<?php
# history.php
#
# Displays play history of a song or all songs

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

$title = "Play history";
require("header.inc.php");

$kweerie = "select status,songtitle, play.play_id, songs.song_id,
artists.artist_name, album_name, DATE_FORMAT(play.timestamp,'%m-%e-%y %H:%i')
as timestamp, timestamp as unformatted_timestamp, timesPlayed from play LEFT
JOIN songs on play.song_id=songs.song_id LEFT JOIN artists on
songs.artist_id=artists.artist_id LEFT JOIN albums on
songs.album_id=albums.album_id WHERE $show_active ";

if(isset($_GET['song_id'])) {
    $kweerie .= "AND songs.song_id=" . (int) $_GET['song_id'];
}
$kweerie .= " ORDER BY unformatted_timestamp desc";

listSongs($kweerie);
require("footer.inc.php");
?>
