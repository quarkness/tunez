<?php
# admin_groups_edit.php
#
# Handles editing groups

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
    $edit_group_id = (int) $_GET['edit_group_id'];

    $query = "SELECT * FROM groups LEFT JOIN permissions ON groups.group_id 
        = permissions.ug_id WHERE permissions.who = 'group' AND 
        groups.group_id=$edit_group_id";
    $result=tunez_query($query);

    $query = "SELECT * FROM users LEFT JOIN access ON 
        users.user_id=access.user_id WHERE access.group_id=$edit_group_id";
    $member_query = tunez_query($query);

    if (mysql_num_rows($result)==0)
    {
        die("group id is not unique or doesn't exist");
	}

    // left in for possible multi-group capability later
    //$g_lst[] = array();

    //while ($g_lst[] = mysql_fetch_array($result));

    $row = mysql_fetch_array($result);

    mysql_free_result($result);

    $title = "Edit Group " . $row['group_name'];
	require("../header.inc.php");
?>

<div class="formdiv">
<form action="<?php echo $_CONF['url_admin']; ?>admin_groups_edit.php" method="post">

<p>
<table border=0 cellspacing=1 cellpadding=1> 
<tr>
<td valign="top">
    <b>Group Permissions<br>
    <input type="checkbox" name="modpermis"> <i>Modify Permissions</i></b>
</td>
<td>
<table border=0>
<?php
   for ($i=0; $i<sizeof($sql_permissions); $i++) {
?>
<tr>
<td><b><?php echo $sql_permissions_descrip[$i]; ?></b></td><td><input type="checkbox" name="<?php echo $sql_permissions[$i]; ?>" <?php
if ($row[$sql_permissions[$i]]) { echo "CHECKED"; }?>></td>
</tr>
<?php
}
?>
</table>
</td>
</tr>
<tr>
<td><b>Group Name: </b></td><td> <input type="text" name="newname" value="<?php echo $row['group_name'] ?>"></td>
</tr>
<tr>
<td valign="top" align="left"><b>Members: </b></td>
<td>
<?php
$member = array();
while ($member[] = mysql_fetch_array($member_query));
mysql_free_result($member_query);

foreach ($member as $currentmember) {
        if (!empty($currentmember['user'])) {
            echo "<a href=\"" . $_CONF['url_admin'] . "admin_users_edit.php?edit_user_id=" . $currentmember['user_id'] . " \" >" . $currentmember['user'] . "</a>";
            echo "&nbsp;&nbsp;[<a href=\"" . $_CONF['url_admin'] . "admin_ug_rem.php?rm_group_id=$edit_group_id&rm_user_id=" . $currentmember['user_id'] . "\">del</a>]<br>";
        }
}
?>
</table>
</td>
</tr>
<tr>
<input type="hidden" name="edit_group_id" value="<?php echo $_GET['edit_group_id']; ?>">
<td></td><td><p><input class="button" type=submit value="Update!"></p></td>
</tr>
</table>
</form>
</div>
<?php

}
else
{

	if(empty($_POST['edit_group_id']))
		die("DID NOT SPECIFY NEW STATUS or USER_ID!!");

	$edit_group_id = (int) $_POST['edit_group_id'];
    
    if (!get_magic_quotes_gpc()) {
        $newname = addslashes($_POST['newname']);
    }
    else {
        $newname = $_POST['newname'];
    }
    
	if(!empty($newname))
    {
        tunez_query("UPDATE groups SET group_name='$newname' WHERE group_id=$edit_group_id;");
    }

    if (!empty($_POST['modpermis'])) {
        $setSQL = "UPDATE permissions SET ";
        foreach ($sql_permissions as $permis) {
            if ($_POST[$permis] == "on") 
            $sql_permis = 1;
            else
            $sql_permis = 0;
            $setSQL = $setSQL . ", $permis=" . $sql_permis;
        }

        $pos = strpos($setSQL, ",");
        $setSQL = substr($setSQL, 0, $pos) . substr($setSQL, $pos + 1);

        $setSQL = $setSQL . " WHERE who='group' AND ug_id="  . $edit_group_id;
        tunez_query($setSQL);
    }


    header("Location: " . $_CONF['url_admin'] . "admin_groups.php");
    require("../header.inc.php");

}

require("../footer.inc.php");
?>
