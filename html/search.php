<?php
# search.php
#
# Searches the database

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

if(!get_magic_quotes_gpc()) {
    $searchFor = addslashes($_SESSION['searchFor']);
}
else {
    $searchFor = $_SESSION['searchFor'];
}

$title = "Search Results for " . stripslashes($_SESSION['searchFor']);
require("header.inc.php");

$_SESSION['search_type'] = $_GET['search_type'];
echo "<BR><b>Following Tunez found...</b><BR><BR>";

$allowed_fields = $_CONF['allowed_search_fields'];

if ($_GET['search_type'] == "all") {
    // if "all" is chosen, set $fields equal to the allowed fields
    $fields = array_keys($allowed_fields);
}
else {
    $fields = split(",", $_GET['search_type']);
}

$kweerie="SELECT $typical_song_select FROM songs LEFT JOIN artists ON songs.artist_id=artists.artist_id " .
"LEFT JOIN albums ON songs.album_id=albums.album_id WHERE ( ";

foreach ($fields as $searchfield) {
    if (in_array($searchfield, array_keys($allowed_fields))) {
        $kweerie .= $allowed_fields[$searchfield] . " like '%$searchFor%' or ";
    }
}
$kweerie .= " 0 ) AND $show_active";
$kweerie .= sql_order($_GET['order_by'], $_GET['sort_dir']);

listSongs($kweerie);
require("footer.inc.php");

?>
