<?php
# confirm.php
# 
# Handles confirmation of user email addresses if option is set

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
$user_id = (int) $_GET['user_id'];

if(!empty($_GET['confirmcode'])) 
{
    $confirmcode = $_GET['confirmcode'];
    $query = "SELECT * from pendingusers where pending_user_id=\"$user_id\"";
    $result = tunez_query($query);
    $row = mysql_fetch_object($result);
    if($confirmcode == $row->confirmation_code) {
        $query = "UPDATE access SET group_id=" . $_CONF['default_group_id'] ." where 
            user_id=$user_id";
        tunez_query($query);
        $title = "Success!  Your account has been activated.";
    }
    else {
        $title = "Failure!  Try your code one more time, it's wrong.";
    }
    require("header.inc.php");
}

require("footer.inc.php");
?>
