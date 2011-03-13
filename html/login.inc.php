<?php
# login.inc.php
#
# Contains login functions

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

function login($username, $password) {
    $query = "SELECT user_id FROM users WHERE user='$username' AND pw=PASSWORD('$password')";
    $result = tunez_query($query);
    $row = mysql_fetch_object($result);

    $user_id = $row->user_id;

    if(!empty($row->user_id)) {
        $user = $_POST['user'];
        if(!empty($_POST['remember']) AND $_POST['remember']=='on') {
            setCookie("remember_session_id", session_id(), time()+999999999);
            $remember = 1;
        }
        else {
            $remember = 0;
        }

        // record time of login and ip
        // also set important session variables...
        $_SESSION['unique_session_num'] = insert_session($user_id, session_id(), $remember, $user);
        $_SESSION['search_type'] = "all";
        
        $_SESSION['messageTitle'] = "Logging you in";
        $_SESSION['messageBody'] = "Welcome $user to Tunez";
    }
    else {
        $_SESSION['messageTitle'] = "Authentication Error";
        $_SESSION['messageBody'] = "Your login or password was incorrect, please try again.";
    }
    return $_SERVER['HTTP_REFERER'];
}

function logout() {
    if(!empty($_SESSION['unique_session_num'])) {
        $unique_session_num = $_SESSION['unique_session_num'];
    }
    else {
        $unique_session_num = 0;
    }

    setCookie("remember_session_id", ""); // just in case they have a session id remembered, delete it
    $query = "UPDATE sessions SET status='logged_out',
        logout_time=NOW(),saved=0 
        WHERE unique_session_num=\"$unique_session_num\"";
    mysql_query($query) or die(mysql_error());
    session_destroy();
    return $_CONF['url'] . "index.php";
}

function timeout($session_id) {
    setCookie("remember_session_id","");
#setCookie("PHPSESSID", "");
    $query = "UPDATE sessions SET status='timed_out',
        logout_time=NOW(),saved=0 
        WHERE session_id=\"$session_id\"";
    mysql_query($query) or die(mysql_error());
    session_unset();
    return $_SERVER[HTTP_REFERER];
}

function insert_session($user_id, $session_id, $saved=0, $user=NULL) {
    $query = "INSERT INTO sessions VALUES ('', $user_id, \"$session_id\",\"$_SERVER[REMOTE_ADDR]\", NOW(), NOW(), 'logged_in', NULL, $saved)";
    $result = tunez_query($query);
    $unique_session_num = mysql_insert_id();
    $_SESSION['songsperpage'] = determine_songs_per_page($user_id);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['perms'] = determine_permissions($user_id);
    if (empty($user)) {
        $query = "SELECT user FROM users WHERE user_id=$user_id";
        $result = tunez_query($query);
        $row = mysql_fetch_object($result);
        $_SESSION['user'] = $row->user;
    }
    else {
        $_SESSION['user'] = $user;
    }

    return $unique_session_num;
}

function determine_idle($user_id=NULL) {
    $timeout_seconds = TIMEOUT_SECONDS;
    $query = "SELECT * FROM sessions WHERE status='logged_in' AND (NOW()-last_refresh_time > $timeout_seconds)";
    if (!empty($user_id)) {
        $query .= " AND user_id=$user_id";
    }
    $result = tunez_query($query);
    while ($row = mysql_fetch_object($result)) {
        $query = "DELETE FROM sessions WHERE unique_session_num=$row->unique_session_num";
        tunez_query($query);
    }
}

function print_active_users() {
    $query = "SELECT * FROM sessions LEFT JOIN users on sessions.user_id=users.user_id WHERE status='logged_in'";
    $result = tunez_query($query);
    print "Active Users:<p>";
    print "<ul>";
    while ($row = mysql_fetch_object($result)) {
        print "<li>$row->user</li>";
    }
}

function determine_permissions($user_id) {
    global $sql_permissions;
    global $sql_permission_names;
    global $number_of_permissions;
    // by default ALLOW's take precedence over DENY's

    // first let's get user permissions
    $query = "SELECT $sql_permission_names FROM permissions where who='user' AND ug_id=$user_id";
    $result = tunez_query($query);
    $num_rows = mysql_num_rows($result);
    if($num_rows == 1) {
        $user_perms = mysql_fetch_array($result);
    }
    elseif($num_rows < 1) {
        $user_perms = Array();
    }
    else //if($num_rows > 1) {
        die("Error, duplicate entry for uid=$user_id");

    // now let's check group permissions
    $groups = Array();
    $temp_perms = Array();
    $group_perms = Array();
    $groups = groups_user_is_in($user_id);
    while($group_id = array_pop($groups)) {
        $query = "SELECT $sql_permission_names FROM permissions where who='group' AND ug_id=$group_id";
        $result = tunez_query($query);
        $temp_perms = mysql_fetch_array($result);
        for($i=0; $i < $number_of_permissions; $i++) {
            // logical OR on array...
            $group_perms[$i] = $temp_perms[$i] | $group_perms[$i];
        }
    }

    // we now possibly have $user_perms and maybe the bitwise OR'd $group_perms
    // Here we also recreate the hash which was probably lost above

    $perms = Array();
    if ((sizeof($user_perms) > 0) && (sizeof($group_perms) > 0)) {
        for($i=0; $i < $number_of_permissions; $i++) {
            $perms[$sql_permissions[$i]] = $user_perms[$i] | $group_perms[$i];
        }
    }
    elseif (sizeof($user_perms) > 0) {
        for($i=0; $i < $number_of_permissions; $i++) {
            $perms[$sql_permissions[$i]] = $user_perms[$i];
        }
    }
    elseif (sizeof($group_perms) > 0) {
        for($i=0; $i < $number_of_permissions; $i++) {
            $perms[$sql_permissions[$i]] = $group_perms[$i];
        }
    }
    return $perms;
}

function determine_songs_per_page($user_id) {
    $query = "SELECT songsperpage FROM preferences WHERE user_id='$user_id'";
    $result = tunez_query($query);
    $row = mysql_fetch_row($result);
    if (empty($row[0])) {
        return DEFAULT_SONGS_PER_PAGE;
    }
    return $row[0];
}

function groups_user_is_in($uid) {
    $groups = array();
    $query = "SELECT group_id from access where user_id=$uid";
    $result = tunez_query($query);
    while($row = mysql_fetch_object($result)) {
        $groups[] = $row->group_id;
    }
    return $groups;
}

?>
