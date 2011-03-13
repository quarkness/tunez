<?php
# admin_groups_del.php
#
# Handles deleting groups

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

if (!($_SESSION['perms']['p_change_perms']))
{
    header(access_denied());
    die;
}

$del_group_id = (int) $_GET['del_group_id'];

tunez_query("DELETE FROM permissions WHERE ug_id=$del_group_id AND who='group'");
tunez_query("DELETE FROM access WHERE group_id=$del_group_id");
tunez_query("DELETE FROM groups WHERE group_id=$del_group_id");

header("Location: $_SERVER[HTTP_REFERER]");

?>
