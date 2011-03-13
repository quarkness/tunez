<?php
# skip_random.php
#
# Page for allowing users to skip random songs

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

require_once("tunez.inc.php");
require_once("shell.inc.php");

if(!($_SESSION['perms']['p_random_skip'])) {
    header(access_denied());
    die;
}

$query="SELECT wasrandom FROM np";
$result=mysql_query($query) or die(mysql_error());
$row=mysql_fetch_object($result);
if(mysql_num_rows($result) < 1) {
    $_SESSION['messageTitle'] = "Error!";
    $_SESSION['messageBody'] = "No song is currently playing so skipping is not possible";
}
elseif($row->wasrandom != 1) {
    $_SESSION['messageTitle'] = "Error!";
    $_SESSION['messageBody'] = "The song you're listening to wasn't random!";
}
elseif(!skip_current_song()) {
    $_SESSION['messageTitle'] = "Error!";
    $_SESSION['messageBody'] = "Invalid mode!";
}
else {
    sleep($_CONF['skip_sleeptime']); // give mysql and mpg123 some time before refreshing page with new info
    $_SESSION['messageTitle'] = "Skipped";
    $_SESSION['messageBody'] = "The song was successfully skipped";
}

if(empty($_SERVER[HTTP_REFERER])) {
    $page = $_CONF['url'] . "index.php";
}
else {
    $page = $_SERVER[HTTP_REFERER];
}

header("Location:$page");
?>
