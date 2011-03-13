<?php
# admin_setup.php
#
# Tunez Setup...

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
 
$justdb = TRUE;
require_once("../../tunez.inc.php");

$test_table = "tunez_test";
$table_names = Array("access", "albums", "artists", "banked", 
    "caused", "genre", "groups", "history", "lyrics", "np",
    "pendingusers", "permissions", "play", "preferences",
    "priority_queue", "queue", "sessions", "songs",
    "string_groups", "string_map", "strings", "users",
    "voting_rights");

$perl_binary_explanation = "You must have perl installed if you're using a perl mode!";
$php_binary_explanation = "You must have a php binary installed if you're using a php mode!";
$shoutcast_binary_explanation = "You must have shoutcast installed if you want to use shoutcast v1";
$mpg123_binary_explanation = "If you want to play MP3's you should really install mpg123 or mpg321";
$ogg123_binary_explanation = "If you want to play OGG's you should really install ogg123";
$ices_binary_explanation = "You must have the ices script installed to use ices mode";
$detach_binary_explanation = "You should compile the detach binary if you want to be able to start/stop
    the daemon from the webpage or modify the volume.  If you don't care about this, ignore this message";
$smixer_binary_explanation = "You should compile the smixer application if you want to be able to change
    the volume from the admin_volume page";

$required_files = Array(
    "all" => Array(),
    "local-perl" => Array(
        "perl_binary" => $perl_binary_explanation),
    "shout-perl" => Array(
        "perl_binary" => $perl_binary_explanation,
        "shoutcast_binary" => $shoutcast_binary_explanation),
    "local-php" => Array(
        "php_binary" => $php_binary_explanation),
    "shout-php" => Array(
        "php_binary" => $php_binary_explanation,
        "shoutcast_binary" => $shoutcast_binary_explanation),
    "ices" => Array(
        "ices_binary" => $ices_binary_explanation)
);
$recommended_files = Array(
    "all" => Array (
        "detach_binary" => $detach_binary_explanation,
        "smixer_binary" => $smixer_binary_explanation),
    "local-perl" => Array(
        "mpg123_binary" => $mpg123_binary_explanation,
        "ogg123_binary" => $ogg123_binary_explanation),
    "shout-perl" => Array(),
    "local-php" => Array(
        "mpg123_binary" => $mpg123_binary_explanation,
        "ogg123_binary" => $ogg123_binary_explanation),
    "shout-php" => Array(),
    "ices" => Array()
);

if (empty($_POST['page'])) {
    $page=1;
}
else {
    $page = $_POST['page'];
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
    <head>
    <title>Tunez Setup - Step <?php echo $page; ?></title>
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
    <META http-equiv="Content-Script-Type" content="text/javascript" />

<?php


#
# PAGE 1 -
#   Check MySQL settings
# 

if(empty($_POST) || $_POST['page']==1) {
    print "<h1>Tunez Setup</h1><br>";
    print "Welcome to the Tunez setup wizard.<br>";
    print "Continuing with this wizard will wipe your tunez database.<br>";
    print "<ul>";
    
// #1.1 connection to mysql server
    print "<li>Connecting to MySQL server: ";
    if(!(mysql_connect($_CONF['mysql_dbhost'], $_CONF['mysql_dbuser'], $_CONF['mysql_dbpass']))) {
        // Login to server failed
        $message = "Check settings in config.inc.php.  Are you sure that the
            username and password are valid and the user has access?";
        failure($message);
        return 0;
    }
    success();
    print "</li>";
    
// #1.2 checking database exists
    print "<li>Checking to ensure database named $_CONF[mysql_dbname] doesn't already exist: ";
    if(!(mysql_select_db($_CONF['mysql_dbname']))) {
        // Database doesn't exist
        print "Trying to create database: ";
        if(!(mysql_create_db($_CONF['mysql_dbname']))) {
            // We were unable to create the database
            $message = "The given mysql username did not have permissions to create the database";
            failure($message);
            return 0;
        }
        success();
    }
    else {
        warning();
        print "Database already exists.. continuing onto the next step will wipe this database!";
    }
    print "</li>";
    
// #1.3 ensureing test table doesn't already exist
    print "<li>Ensuring $test_table table doesn't already exist: ";
    $result = mysql_list_tables($_CONF['mysql_dbname']);
    if (!$result) {
        failure("Could not list tables");
        return 0;
    }
    while ($row = mysql_fetch_array($result)) {
        if ($row[0] == $test_table) {
            warning();
            print "Test table $test_table already exists, trying to delete it: ";
            if (!(mysql_query("DROP TABLE $test_table"))) {
                $message = "The table $test_table must be cleared from our database before continuing.
                The mysql user you have entered in config.inc.php does not have permission to drop this
                table";
                failure($message);
                return 0;
            }
        }
    }
    success();
    print "</li>";

// #1.4 creating test table
    print "<li>Creating test table: ";
    if (!mysql_query("CREATE TABLE $test_table (test int)")) {
        // Creation failed
        $message = "Could not create test table.  Ensure that the database user has create 
        permissions.";
        failure($message);
        return 0;
    }
    success();
    print "</li>";

// #1.5 testing insert
    print "<li>Testing insert: ";
    if (!mysql_query("INSERT INTO $test_table VALUES (1)")) {
        // Insert failed
        $message = "Could not perform an insert operation on table";
        failure($message, TRUE);
        return 0;
    }
    success();
    print "</li>";

// #1.6 testing select
    print "<li>Testing select: ";
    $result = mysql_query("SELECT * from $test_table");
    if (!$result) {
        // Select failed
        $message = "Select operation on table failed";
        failure($message, TRUE);
        return 0;
    }
    $row = mysql_fetch_array($result);
    if ($row['test'] != 1) {
        $message = "Select operation doesn't match insert!! WTF??";
        failure($message, TRUE);
        return 0;
    }
    success();
    print "</li>";

// #1.7 testing update
    print "<li>Testing update: ";
    $result = mysql_query("UPDATE $test_table SET test=2 where test=1");
    if (!$result) {
        // UPDATE failed
        $message = "Update failed.  Make sure that the database user has update permissions";
        failure($message, TRUE);
        return 0;
    }
    success();
    print "</li>";

// #1.8 testing delete
    print "<li>Testing delete: ";
    $result = mysql_query("DELETE FROM $test_table WHERE test=2");
    if (!$result) {
        // DELETE failed
        $message = "Delete failed.  Make sure that the database user has delete permissions";
        failure($message, TRUE);
        return 0;
    }
    success();
    print "</li>";
    cleanup();
    print "</ul><p>All mysql tests pass.</p>";
    
    // #1.9 File checks...
    
    print "<p>You have selected mode " . $_CONF['mode'] . "</p>";
    print "<p>Running tests...</p>";
    print "<ul>";
    foreach ($required_files['all'] as $file => $desc) {
        if(!check_file($file, $desc, TRUE, TRUE)) {
            die("Missing required file");
        }
    }
    foreach ($required_files[$_CONF['mode']] as $file => $desc) {
        if(!check_file($file, $desc, TRUE, TRUE)) {
            die("Missing required file");
        }
    }
    foreach ($recommended_files['all'] as $file => $desc) {
        check_file($file, $desc, TRUE, FALSE);
    }
    foreach ($recommended_files[$_CONF['mode']] as $file => $desc) {
        check_file($file, $desc, TRUE, FALSE);
    }

    print "</ul>";    

    // #1.10 Permission checks
   
    $err = FALSE;
    if($_CONF['mode']!="ices" && $_CONF['mode']!="shout-php" &&
        $_CONF['mode']!="shout-perl") {
            print "Checking sound device permissions...<br>";
            print "/dev/dsp&nbsp;";
            if(!is_writeable("/dev/dsp")) {
                $err = TRUE;
                warning("The device is NOT writeable by the webserver<br>");
            }
            else {
                success();
                print "<br>";
            }
            print "/dev/mixer&nbsp;";
            if(!is_writeable("/dev/mixer")) {
                $err = TRUE;
                warning("The device is NOT writeable by the webserver<br>");
            }
            else {
                success();
                print "<br>";
            }
            if($err) {
                ?>
            <font color="red" size="+1">
            If you are planning on starting the daemon from the webpage this is
            a huge problem.  You need to give write permissions to the
            webserver on your server.  Typically this is done by adding the
            webserver to the group which has write permissions to /dev/dsp and
            /dev/mixer (sometimes called the "audio" group).  You can give all
            users access with the command "chmod o+rw /dev/dsp /dev/mixer"
            however this is insecure if you're using a multiuser system as
            anyone will be able to play sounds.
            </font>
            <?php
        }
    }
    
    print "<p>To continue with setup wizard and installation of database
        schema, click continue.  Only SELECT, INSERT, UPDATE, and DELETE permissions are necessary
        after the schema has been installed<br>";
    print "<form action=\"$_SERVER[PHP_SELF]\" method=\"POST\">";
    print "<input type=\"hidden\" name=\"page\" value=2>";
    print "<input type=\"submit\" name=\"step\" value=\"Next\">";
    print "</form><br>";
}

//##########
//
// Page 2
//
//##########

elseif($_POST['page']==2) {
    print "<p><h1>Tunez Setup</h1></p>\n";
    print "<p><h3>Step 2: Additional checks, confirmation</h3></p>\n";

// Step 2.1: User information

    print "<p>";
    print "Preparing to install database schema...<br>";
    print "<ul><li>Database: $_CONF[mysql_dbname]</li>";
    print "<li>Hostname: $_CONF[mysql_dbhost]</li>";
    print "</ul>";
   
// Step 2.2: Tell user of any tables that we are going to delete before continuing on
   print "<b>To continue with installation of the Tunez schema click next</b>";
    
    print "<form action=\"$_SERVER[PHP_SELF]\" method=\"POST\">";
    print "<input type=\"hidden\" name=\"page\" value=3>";
    print "<input type=\"submit\" name=\"step\" value=\"Next\">";
    print "</form><br>";
}

//##########
//
// Page 3
//
//##########

elseif($_POST['page']==3) {
    print "<p><h1>Tunez Setup</h1></p>\n";
    print "<p><h3>Step 3: Schema installation</h3></p>\n";

    // Step 3.1: Delete duplicate tables
    $duplicate_tables = array();
    $duplicate_tables = determine_duplicate_tables(FALSE);
    //print_r($duplicate_tables);
    foreach ($duplicate_tables as $table) {
        $query = "DROP TABLE $_CONF[mysql_dbname].$table";
        //print $query;
        mysql_query($query) or die(mysql_error());
    }
    
    // Step 3.2: Install schema
    if (install_sql($_CONF['path'] . "system/sql/tunez.sql")) {
        print "Tables created successfully<br>";
    }

    // Step 3.3: Installing default values
    if (install_sql($_CONF['path'] . "system/sql/setup.sql")) {
        print "Default values inserted...<br>";
    }
    
    print "<form action=\"$_SERVER[PHP_SELF]\" method=\"POST\">";
    print "Enter a username, password, and email address for the admin account<br>";
    print "<table border=0>";
    print "<tr><td>Admin Username</td><td><input type=\"text\" name=\"username\"></td></tr>";
    print "<tr><td>Admin Password</td><td><input type=\"password\" name=\"password\"></td></tr>";
    print "<tr><td>Admin Email</td><td><input type=\"text\" name=\"email\"></td></tr>";
    print "</table>";
    print "<input type=\"hidden\" name=\"page\" value=4>";
    print "<input type=\"submit\" name=\"step\" value=\"Next\">";
    print "</form><br>";
}

elseif($_POST['page']==4) {
    if (empty($_POST['username'])) {
        die("Username variable is empty!");
    }
    if (empty($_POST['password'])) {
        die("Password was not entered!");
    }
    if (empty($_POST['email'])) {
        die("Email address was not entered!");
    }

    if (!get_magic_quotes_gpc()) {
        $_POST['username'] = addslashes($_POST['username']);
        $_POST['password'] = addslashes($_POST['password']);
        $_POST['email'] = addslashes($_POST['email']);
    }
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];

    $query = "SELECT count(*) from users";
    $result = mysql_query($query);
    $row = mysql_fetch_array($result);
    $number = $row[0];
    
    print "Creating admin account...";

    $query = "INSERT INTO users VALUES 
        ('', '$username', PASSWORD('$password'), '$email')";
        
    mysql_query($query) or die(mysql_error());
    
    $bestowed_user_id = mysql_insert_id();
    $query = "INSERT INTO access (user_id, group_id) 
        VALUES ($bestowed_user_id, 3)";
    mysql_query($query) or die(mysql_error());
    
    print "User account successfully created and granted admin access...<br>";
    print "Please delete this file now using the rm command!  Execute:<br>";
    print "<tt>rm " . $_CONF['path_html'] . "admin/setup/admin_setup.php</tt>";
}
        

function check_file($file, $desc, $binary, $critical=FALSE) {
    global $_CONF;

    print "<li>";
    print $_CONF[$file] . "&nbsp;";
    if(is_file($_CONF[$file])) {
        if ($binary) {
            if (is_executable($_CONF[$file])) {
                success();
                print "</li>";
                return TRUE;
            }
            else {
                if ($critical)
                    failure("<br>" . $desc . "<br>File is not executable!");
                else
                    warning("<br>" . $desc);
                print "</li>";
                return FALSE;
            }
        }
        else {
            success();
            print "</li>";
            return TRUE;
        }
    }
    else {
        if ($critical)
            failure("<br>" . $desc);
        else
            warning("<br>" . $desc);
        print "</li>";
        return FALSE;
    }
}


function install_sql($file) {
    $sql = "";
    $fp = fopen($file, 'r');
    while (!feof($fp)) {
        $sql .= fgets($fp, 4096);
    }
    fclose($fp);
    $sqlentries = preg_split('/;/', $sql);
    for ($i=0; $i < sizeof($sqlentries)-1; $i++) {
        // subtract one so that whatever is after last ; is ignored
        //print "<p>" . $sqlentries[$i] . "<br>";
        mysql_query($sqlentries[$i] . ';') or die(mysql_error());
    }
    return TRUE;
}


function determine_duplicate_tables($warning_msg=FALSE) {
    global $table_names, $_CONF;
    $duplicate_tables = array();
    $actual_tables = array();

    $result = mysql_query("SHOW TABLES FROM $_CONF[mysql_dbname]") or die("query failed");
    while($row = mysql_fetch_array($result)) {
        $actual_tables[] = $row[0];
    }
    if (sizeof($actual_tables) > 0) {
        $duplicate_tables = array_intersect($actual_tables, $table_names);
        if (sizeof($duplicate_tables) > 0) {
            foreach ($duplicate_tables as $table) {
                if ($warning_msg) {
                    print "A table named $table already exists in your database!!!<br>";
                }
            }
        }
    }
    return $duplicate_tables;
}
    
function cleanup() {
    global $test_table;
    print "<li>Trying to drop test table: ";
    if(!(mysql_query("DROP TABLE $test_table"))) {
        failure("Could not delete test table $test_table", FALSE);
        return;
    }
    else {
        success();
    }
    print "</li>";
}

function success($message=NULL) {
    print "<font color=\"green\">success</font>&nbsp;$message\n";
}

function warning($message=NULL) {
    print "<font color=\"yellow\">warning</font>&nbsp;$message\n";
}

function failure($message, $cleanup=FALSE) {
    print "<font size=+1 color=\"red\">failure</font>\n";
    print $message . "<br>";
    if ($cleanup) {
        cleanup();
    }
}

?>
