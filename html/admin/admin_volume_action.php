<?php
# admin_volume_action.php
#
# Called from admin_volume.php to do stuff

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

if (!($_SESSION['perms']['p_volume'])) {
    header(access_denied());
	die;
}
$cmd = "echo \"";

// This should prevent arbitrary command line execution unless of course the attacker
// has control of config.inc.php
foreach ($_CONF['mixer_devices'] as $device) {
    $volume = $_GET[$device . "Input"];
    settype($volume, "integer");
    $cmd .= "vol $device $volume\n";
}
$cmd .= "\" |";
$cmd .= $_CONF['smixer_binary'] . " -s";
system($cmd);

header("Location: $_SERVER[HTTP_REFERER]");
?>
