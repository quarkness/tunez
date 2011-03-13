<?php
# admin_groups.php
#
# Displays group information

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

$title = "Group Admin";
require("../header.inc.php");

$sqlGroups = "SELECT * FROM groups;";

$resultGroups = mysql_query($sqlGroups);

?>
<script language="Javascript">
function checkDel() {
    return(confirm("Are you sure you wish to delete this group?\n\nNote that all associated records will also be deleted including user memberships and group permissions."));
}
</script>

<?php
echo "<table cellpadding=3 border=0 cellspacing=1>\n";

echo "<tr>\n";
echo "<th>Group id</th>\n";
echo "<th>Name</th>\n";
echo "<th>Action</th>\n";
echo "</tr>\n";

while ($row = mysql_fetch_array($resultGroups))
{
    echo "<tr>\n";
    echo "<td>" . $row['group_id'] . "</td>\n";
    echo "<td>" . $row['group_name'] . "</td>\n";
    echo "<td><a href=\"admin_groups_edit.php?edit_group_id=" . 
        $row['group_id'] . "\">edit</a> / <a href=\"admin_groups_del.php?del_group_id=" . 
        $row['group_id'] . "\" onClick=\"javascript:return(checkDel())\" >del</a></td>";
    echo "</tr>\n";
}

echo "</table>";

echo "<form method=\"get\" action=\"" . $_CONF['url_admin'] . "admin_groups_add.php\">";
echo "<input class=\"button\" type=submit value=\"Add Group\">";
echo "</form>";

require("../footer.inc.php");

?>
