<?php
# recent.php
#
# Shows most recently added or modified songs songs

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

$nosort = TRUE;
require("tunez.inc.php");
$typical_song_select = $_CONF['typical_song_select'];
$title = "Latest added songs";
require("header.inc.php");

$kweerie = "SELECT $typical_song_select FROM songs s LEFT JOIN artists on
s.artist_id=artists.artist_id LEFT JOIN albums on s.album_id=albums.album_id
ORDER BY song_id DESC";
listSongs($kweerie, $nosort=TRUE);
require("footer.inc.php");
?>
