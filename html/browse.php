<?php
# browse.php
#
# Lists songs alphabetical and lets user vote.

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

if(!empty($_GET['browseall'])) {
    $browseall = TRUE;
}
else {
    if(isset($_GET['beginningWith']))
    {
        if(!get_magic_quotes_gpc()) {
            $beginningWith = addslashes($_GET['beginningWith']);
        }
        else {
            $beginningWith = $_GET['beginningWith'];
        }
        $_SESSION['beginningWith'] = $beginningWith;
    }
    elseif(isset($_SESSION['beginningWith']))
    {
        $beginningWith = $_SESSION['beginningWith'];
    }
    elseif(!empty($_GET['browseall']))
    {
        $browseall = TRUE;
    }
    else
    {
        $beginningWith = "A";
    }
}

require("tunez.inc.php");
$typical_song_select = $_CONF['typical_song_select'];
$title = "Browsing letter $beginningWith";

require("header.inc.php");

$text = "";

for ($i=65 ; $i<=90 ; $i++)
{
	$letter = chr ($i);
	$text .= "<a href=\"browse.php?beginningWith=$letter\">$letter</a> | ";
}

	$text .= "<BR>";

for ($i=48 ; $i<=57 ; $i++)
{
	$letter = chr ($i);
	$text .= "<a href=\"browse.php?beginningWith=$letter\">$letter</a> | ";
}

showBox ("Browse the songs by clicking on the first letter", $text);

if (!empty($beginningWith))
{
    $kweerie = "SELECT $typical_song_select from songs LEFT JOIN artists ON
        songs.artist_id=artists.artist_id LEFT JOIN albums ON
        songs.album_id=albums.album_id LEFT JOIN genre ON
        songs.genre_id=genre.genre_id WHERE songs.songtitle like '" .
        strtolower($beginningWith) . "%' OR songs.songtitle like '" .
        strtoupper($beginningWith) . "%' AND $show_active ";
    $kweerie .= sql_order($_GET['order_by'], $_GET['sort_dir']);

	listSongs($kweerie);
}
require("footer.inc.php");
?>
