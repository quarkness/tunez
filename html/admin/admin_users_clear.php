<?php
# admin_users_clear.php
#
# Allows users to clear their own votes (or admins)

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
require_once($_CONF['path_system'] . "classes/PQueue.class.php");

if ( !( ($_SESSION['perms']['p_change_perms']) || ($_SESSION['user_id'] == (int) $_GET['clear_user_id']) ) )
{
    header(access_denied());
    die;
}

tunez_query("DELETE FROM queue WHERE user_id=" . (int) $_GET['clear_user_id']);
$clear = new PQueue;
$clear->generate_from_votes();

header("Location: $_SERVER[HTTP_REFERER]");
?>
