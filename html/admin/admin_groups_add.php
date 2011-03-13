<?php
# admin_groups_add.php
#
# Handles adding groups

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

if (empty($_POST))
{
	$title = "Add Group ";
	require("../header.inc.php");
?>

<div class="formdiv">
<form action="<?php echo $_CONF['url_admin'] ?>admin_groups_add.php" method="post">

<p>
<table border=0 cellspacing=1 cellpadding=1> 
<tr>
<td valign="top"><b>Group Permissions<br></b></td>
<td>
<table border=0>
<?php
 for ($i=0; $i<sizeof($sql_permissions); $i++) {
?>
<tr>
<td><b><?php  echo $sql_permissions_descrip[$i]; ?></b></td><td><input
type="checkbox" name="<?php echo $sql_permissions[$i]; ?>"></td>
</tr>
<?php
}
?>
</table>
</td>
</tr>
<tr>
<td><b>Group Name: </b></td><td> <input type="text" name="newname" value=""></td>
</tr>
<tr>
<td></td><td><p><input class="button" type=submit value="Add Group!"></p></td>
</tr>
</table>
</form>
</div>
<?php

}
else
{
    if (empty($_POST['newname'])) {
        die("Yes, new groups actually do require a name!");
    }

    if (!get_magic_quotes_gpc()) {
        $newname = addslashes($_POST['newname']);
    }
    else {
        $newname = $_POST['newname'];
    }

    $query = "SELECT * FROM groups WHERE group_name='" . $newname . "'";
    $grps = tunez_query($query);
    if (mysql_num_rows($grps) > 0) {
        die("A group with this name already exists");
    }

    mysql_free_result($grps);

    tunez_query("INSERT INTO groups (group_name) VALUES ('$newname');");
    $newid = mysql_insert_id();

    $setSQL = "INSERT permissions (";
    foreach ($sql_permissions as $permis) {
        $setSQL = $setSQL . ", $permis";
    }
    $pos = strpos($setSQL, ",");
    $setSQL = substr($setSQL, 0, $pos) . substr($setSQL, $pos + 1) . ", who, ug_id) VALUES (";

    foreach ($sql_permissions as $permis) {
        if ($_POST[$permis] == "on") 
        $sql_permis = 1;
        else
        $sql_permis = 0;
        $setSQL_2 = $setSQL_2 . ", " . $sql_permis;
    }
    $pos = strpos($setSQL_2, ",");
    $setSQL_2 = substr($setSQL_2, 0, $pos) . substr($setSQL_2, $pos + 1);

    $setSQL = $setSQL . $setSQL_2;

    $setSQL = $setSQL . ", 'group', " .  $newid . ");";

    tunez_query($setSQL);


    header("Location: " . $_CONF['url_admin'] . "admin_groups.php");
    require("../header.inc.php");

}

require("../footer.inc.php");
?>
