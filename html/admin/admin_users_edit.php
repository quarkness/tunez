<?php
# admin_users_edit.php
#
# Handles editing of user data

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

$edit_user_id = (int) $_GET['edit_user_id'];

if (empty($_POST))
{
        $query = "SELECT * FROM users LEFT JOIN access ON 
            users.user_id=access.user_id LEFT JOIN groups ON
            access.group_id=groups.group_id WHERE users.user_id=
            $edit_user_id";
        $result=tunez_query($query);
        $query = "SELECT * FROM users LEFT JOIN permissions ON
            users.user_id=permissions.ug_id WHERE users.user_id=
            $edit_user_id AND permissions.who='user'";
        $permis=tunez_query($query);
        $query="SELECT * FROM groups";
        $grps=tunez_query($query);

        if (mysql_num_rows($result)==0)
        {
            die("user id is not unique or doesn't exist");
        }

        $u_lst[] = array();

        while ($u_lst[] = mysql_fetch_array($result));

        $title = "Edit User " . $u_lst[1]['user'];

        require("../header.inc.php");
?>

<div class="formdiv">
<form action="<?php echo $_CONF['url_admin']; ?>admin_users_edit.php" method="post">

<p>
<table border=0 cellspacing=1 cellpadding=1> 
<tr>
<td><b>Group Memberships<br> 
<i><input type="checkbox" name="modmem">Modify Memberships</i></b>
</td>
<td>
<?php
while ($row = mysql_fetch_array($grps)) {
?>
<input type="checkbox" name="grp<?php echo $row['group_id'] ?>" <?php
foreach ($u_lst as $elem) {
    if ($elem['group_id'] == $row['group_id'])
        echo "CHECKED ";
}
?>value="<?php echo $row['group_id'] ?>"><?php echo
$row['group_name'] ?><br>
<?php
}
?>
</tr>
<tr>
<td><b>Username: </b></td><td> <input type="text" name="newname" value="<?php echo $u_lst[1]['user'] ?>"></td>
</tr>
<tr>
<td><b>E-Mail Address: </b></td><td> <input type="text" name="newemail" value="<?php echo $u_lst[1]['email'] ?>"> </td>
</tr>
<tr>
<td><b>New Password:</b></td><td><input type="password" name="newpw"><br></td>
</tr>
<tr>
<input type="hidden" name="edit_user_id" value="<?php echo $edit_user_id; ?>">
<td></td><td><p><input class="button" type=submit value="Update!"></p></td>
</tr>
</table>
</form>
</div>
<?php

}
else
{
    //if(/*empty($_POST[new_status]) OR */empty($_POST['edit_user_id']))
    if (empty($_POST['edit_user_id'])) {
        die("DID NOT SPECIFY NEW STATUS or USER_ID!!");
    }

	$edit_user_id = (int) $_POST['edit_user_id'];

    if(!get_magic_quotes_gpc()) {
        $newpw = addslashes($_POST['newpw']);
        $newemail = addslashes($_POST['newemail']);
        $newname = addslashes($_POST['newname']);
    }
    else {
        $newpw = $_POST['newpw'];
        $newemail = $_POST['newemail'];
        $newname = $_POST['newname'];
    }

    //$new_status = $_POST['new_status'];

    if((!empty($newpw)) || !empty($newemail) || !empty($newname))
    {
        if (!empty($newpw))
        $setSQL = ", pw = PASSWORD('$newpw')";
        // FIXME this PASSWORD() call is EVIL!  We need MD5 here at least next release
        if (!empty($newemail))
        $setSQL = $setSQL . ", email='$newemail'";
        if (!empty($newname))
        $setSQL = $setSQL . ", user='$newname'";

        $pos = strpos($setSQL, ",");           
        $setSQL = substr($setSQL, 0, $pos) . substr($setSQL, $pos + 1);

        tunez_query("UPDATE users SET $setSQL WHERE user_id=$edit_user_id");
    }
    if (!empty($_POST['modmem'])) {
        tunez_query("DELETE FROM access WHERE user_id=$edit_user_id;");

        $query="SELECT * FROM groups";
        $grps=tunez_query($query);

        while ($row = mysql_fetch_array($grps)) {
            $html_grp = "grp" . $row['group_id'];
            if (!empty($_POST[$html_grp]))
            tunez_query("INSERT INTO access (user_id, group_id) VALUES 
            ($edit_user_id, " . $row['group_id'] . ");");
        }
    }

    header("Location: " . $_CONF['url_admin'] . "admin_users.php");
    require("../header.inc.php");

}

require("../footer.inc.php");
?>
