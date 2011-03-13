<?php
# who.php
#
# This tells the user who voted for a song that is in the queue

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
$title = "Who voted for THAT song?!";
require("header.inc.php");
require_once($_CONF['path_system'] . "classes/song.class.php");

if(!get_magic_quotes_gpc()) {
    $_GET['song_id'] = addslashes($_GET['song_id']);
}

if(empty($_GET['song_id'])) {
    die("no song id passed");
}

$song_id = $_GET['song_id'];
$song = new song($song_id, NULL);
$song->read_data_from_db();
$song->print_info();


$kweerie = "SELECT user, DATE_FORMAT(timestamp,'%m-%e-%y %H:%i') as timestamp
from queue LEFT JOIN users using(user_id) where song_id='$song_id' order by
timestamp";
$result = tunez_query($kweerie);

echo "<p>The following people want to hear <b>$song->songtitle:</b></p>";
echo "<ol>";
while ($who = mysql_fetch_object($result))
{
    echo "<li>$who->user ($who->timestamp)";
}
echo "</ol>";
mysql_free_result($result);	

echo "<br><b>Cool!</b> ";
echo "I want to <a href=\"" . $_CONF['url'] . "vote.php?action=vote&song_id=$song_id\" class=dik>vote</a> 
for that song too!"; 

require("footer.inc.php");
?>
