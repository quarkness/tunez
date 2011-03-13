<?php
# admin_users.php
#
# Displays users

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

// this global variable thing is kind of a hack . . but it seems to work

$current_uid = -1;

function uid_filter($var) {
    global $current_uid;
    return ($var['user_id']==$current_uid);
}

$title = "User Admin";
include ("../header.inc.php");
?>
<script language="Javascript">
function checkDel() {
    return(confirm("Are you sure you wish to delete this user?\n\nNote that all associated records will also be deleted including user memberships and permissions."));
}
</script>

<?php
$sqlUsers = "SELECT users.*, groups.*  FROM users LEFT JOIN access ON users.user_id=access.user_id LEFT JOIN groups ON access.group_id=groups.group_id ORDER BY users.user ASC";

$resultUsers = mysql_query($sqlUsers);

echo "<table cellpadding=3 border=0 cellspacing=1>\n";

echo "<tr>\n";
echo "<th>User id</th>\n";
echo "<th>Username</th>\n";
echo "<th>Groups</th>\n";
echo "<th>Action</th>\n";
echo "</tr>\n";

$row = array();
while ($row[] = mysql_fetch_array($resultUsers));

mysql_free_result($resultUsers);


for ($i=0; $i < sizeof($row)-1; $i++)
{

    $current_uid = $row[$i]['user_id']; // hack! hack! :)
    $grp_list = array_filter($row, "uid_filter");
    echo "<tr>\n";
    echo "<td>" . $row[$i]['user_id'] . "</td>\n";
    echo "<td>" . $row[$i]['user'] . "</td>\n";
    echo "<td>" . $row[$i]['group_name'];
    for ($j=1; $j < sizeof($grp_list); $j++) {
        echo "/" . $row[++$i]['group_name'];
    }
    echo "</td>\n";
    echo "<td><a href=\"admin_users_edit.php?edit_user_id=" . $row[$i][user_id] . "\">edit</a> / <a href=\"admin_users_del.php?del_user_id=" . $row[$i]['user_id'] . "\" onClick=\"javascript:return(checkDel()) \">del</a> / <a href=\"admin_users_clear.php?clear_user_id=" . $row[$i]['user_id'] . "\">clear</a></td>\n";
    echo "</tr>\n";
}
echo "</table>";
require("../footer.inc.php");
?>
