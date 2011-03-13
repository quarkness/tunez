<?php
# signup.php
#
# Signup page for new users

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

$NoRefresh = TRUE;
require("tunez.inc.php");
require("login.inc.php");

if(empty($_POST)) {
	$title = "Sign Up Here!";
	require("header.inc.php");

	?>
	<div id="message">
	<form action="signup.php" name="signup" method="post">
    <p>To signup for an account enter a username and confirm your password.</p>
    <?php
    if($_CONF['email_authentication']) {
        print "<p>Make sure the email address you enter is valid because you will be sent
            a confirmation email.  The email will have a link you must click on before
            your account can be activated.</p>";
    }
    ?>
	<p>User:<BR><input class="field" type=text name="user"></p>
	<p>Password:<BR><input class="field" type="password" name="pw">
    <p>Confirm Password:<BR><input class="field" type="password" name="pwconfirm"><p>
	<p>E-mail:<BR><input class="field" type="text" name="email"><BR></p>
	<p><input class="button" type="submit" value="submit!"></form></p>
	</div>
	<?php
	require("footer.inc.php");
}
else {
    if(empty($_POST['pw']) || empty($_POST['pwconfirm']) || empty($_POST['user'])
            || empty($_POST['email'])) {
        $_SESSION['messageTitle'] = "You did not fill out the form properly";
        $_SESSION['messageBody'] = "Enter your desired username, password,
        and your email address as indicated";
        $title = "Sign up failed!";
        require("header.inc.php");
        require("footer.inc.php");
        return FALSE;
    }
    if($_POST['pw'] != $_POST['pwconfirm']) {
        $_SESSION['messageTitle'] = "Your passwords do not match.  Please go back and try again.";
        $_SESSION['messageBody'] = "Account not created";
        $title = "Sign up failed!";
        require("header.inc.php");
        require("footer.inc.php");
        return FALSE;
    }

    if(!get_magic_quotes_gpc()) {
        $_POST['user'] = addslashes($_POST['user']);
        $_POST['pw'] = addslashes($_POST['pw']);
        $_POST['email'] = addslashes($_POST['email']);
    }
    $user = $_POST['user'];
    $pw = $_POST['pw'];
    $email = $_POST['email'];
    
    
    $result = tunez_query("SELECT * from users WHERE user='$user'");
    if(mysql_num_rows($result) > 0)
    {
        $_SESSION['messageTitle'] = "User already exists!";
        $_SESSION['messageBody'] = "Account not created";
        $title = "Sign up failed!";
        require("header.inc.php");
        require("footer.inc.php");
        return false;
    }
    else
    {
        // Create an entry for them in the users table
        $kweerie = "INSERT INTO users VALUES ('', '$user', PASSWORD('$pw'), '$email')";
        $result = tunez_query($kweerie);
        
        if($_CONF['email_authentication'])
        {
            // Add them to the default unsecured guest group...
            $bestowed_user_id = mysql_insert_id();
            $query = "INSERT INTO access (user_id, group_id) VALUES ($bestowed_user_id, 0)";
            $result = tunez_query($query);

            $time = time();
            $code='';
            for($x=1;$x<9;$x++)
            {
                $random=rand(65,90);
                $code=$code.chr($random);
            }
            $code=$code.rand(0,9);

            $content =  "Your confirmation code is $code";
            $content .= "Please go to " . $_CONF['url'] . 
                "/confirm.php?confirmcode=$code&user_id=$bestowed_user_id to activate your account";

            $query = "INSERT INTO pendingusers (pending_user_id, 
                confirmation_code, timestamp) VALUES (\"$bestowed_user_id\", \"$code\", \"$time\")";
            $result = tunez_query($query);

            mail($email, "confirmation email", $content);
            $text = "$user, You now have a Tunez account but you must activate it by clicking on the
                link in the email sent to you";
        }
        else {
            // Add them to the default group
            $bestowed_user_id = mysql_insert_id();
            $query = "INSERT INTO access (user_id, group_id) VALUES ($bestowed_user_id, " 
                . $_CONF['default_group_id'] . ")";
            $result = tunez_query($query);
            $_SESSION['unique_session_num'] = insert_session($bestowed_user_id, session_id(), 0, NULL);
            $text = "$user, You now have a Tunez account and have been logged in";
        }

        $title = "Sign up succeeded!";
        require("header.inc.php");
        showBox("Sign up succeeded!", "$text");
        require("footer.inc.php");
    }
}
?>
