<?php
# admin_skip.php
#
# Handles skipping of songs

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

require("../tunez.inc.php");
require("../shell.inc.php");

if(!($_SESSION['perms']['p_skip'])) {
    header(access_denied());
    die;
}

if(!skip_current_song()) {
    $_SESSION['messageTitle'] = "Error!";
    $_SESSION['messageBody'] = "Invalid mode!";
    header("Location: $_SERVER[HTTP_REFERER]");
}

sleep($_CONF['skip_sleeptime']); // give mysql and mpg123 some time before refreshing page with new info
$_SESSION['messageTitle'] = "Skipped";
$_SESSION['messageBody'] = "The song was successfully skipped";

header("Location:$_SERVER[HTTP_REFERER]");
?>
