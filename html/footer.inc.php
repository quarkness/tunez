</div>

<?php
if (!empty($_SESSION['messageTitle']) || !empty($_SESSION['messageBody']))
{
	echo "<div class=\"content\">\n";
        echo "<h1>Notification</h1>\n";
        echo "<h2>" . $_SESSION['messageTitle'] . "<h2>\n";
        echo "<p>" . $_SESSION['messageBody'] . "</p>";
        echo "</div>";

        $_SESSION['messageTitle'] = NULL;
        $_SESSION['messageBody'] = NULL;
}

?>

<div class="content">
Tunez (c) Quarkness
</div>

<div id="leftnav">
	<h2>Your Wish?</h2>
    <p>
    <a href="<?=$_CONF['url']. "index.php"?>">:: Home</a><br>
<?php if (!empty($_SESSION['perms']['p_upload']) && $_CONF['enable_uploads']) { ?>
        <a href="<?=$_CONF['url'] . "upload.php"?>">:: Upload a Song</a><br>
<?php } ?>
        <a href="<?=$_CONF['url'] . "playlist.php"?>">:: Playlist</a><br>
        <a href="<?=$_CONF['url'] . "browse.php"?>">:: Browse by Title</a><br>
        <a href="<?=$_CONF['url'] . "browse_artist.php"?>">:: Browse by Artist</a><br>
        <a href="<?=$_CONF['url'] . "browse_album.php"?>">:: Browse by Album</a><br>
        <a href="<?=$_CONF['url'] . "browse_genre.php"?>">:: Browse by Genre</a><br>
        <a href="<?=$_CONF['url'] . "history.php"?>">:: Play History</a><br>
        <a href="<?=$_CONF['url'] . "charts.php"?>">:: Charts</a><br>
        <a href="<?=$_CONF['url'] . "recent.php"?>">:: Recently added</a><br>
        <a href="<?=$_CONF['url'] . "blocked.php"?>">:: Blocked Songs</a><br>
        <br>
    <a href="<?=$_CONF['url'] . "preferences.php"?>">:: Preferences</a><br>
<?php
if(!empty($_SESSION['user_id'])) {
    echo "<a href=\"" . $_CONF['url'] . "login.php?action=logout\">:: Log Out</a>";
}
if(!empty($_SESSION['searchFor'])) {
    if(get_magic_quotes_gpc()) {
        $user_searchFor = stripslashes($_SESSION['searchFor']);
    }
    else {
        $user_searchFor = $_SESSION['searchFor'];
    }
}
else {
    $user_searchFor = "";
}

// search_type doesn't need to be checked for SQL injection
if(!empty($_SESSION['search_type'])) {
    $search_type = $_SESSION['search_type'];
}
else {
    $search_type = "";
}


?>
    </p>
    <h2>Search</h2>
        <form action="<?=$_CONF['url'] . "search.php"?>" method="get">
        <p>
            <input type="hidden" name="action" value="doSearch">
		    <input class="field" type="text" name="searchFor" size="13" 
            value="<?php echo $user_searchFor; ?>">
            <select class="dropdown" name="search_type">
                <option <?php selected("all", $search_type); ?> value="all">All
                <?php
                foreach($_CONF['allowed_search_fields'] as $field => $value) {
                    print "<option ";
                    print selected($field, $search_type);
                    print " value=\"$field\">";
                    print $_CONF['field_descriptions'][$field];
                    print "\n";
                }
                ?>
            </select>
            <input type="submit" value="Search">
        </p>
        </form>
    <br><br>
<?php
if (!empty($_SESSION['perms']['p_daemon']) OR 
        !empty($_SESSION['perms']['p_volume']) OR 
        !empty($_SESSION['perms']['p_skip']) OR 
        !empty($_SESSION['perms']['p_change_perms']) OR 
        !empty($_SESSION['perms']['p_updateDb']) OR 
        !empty($_SESSION['perms']['p_sync'])) {
    
	print "<h2>Your Command!</h2>\n<p>";
    if (!empty($_SESSION['perms']['p_daemon'])) {
        print "<a href=\"" . $_CONF['url_admin'] . "admin_daemon.php?action=stop\">:: Stop daemon</a><br>";
        print "<a href=\"" . $_CONF['url_admin'] . "admin_daemon.php?action=start\">:: Start daemon</a><br>";
    }
    if ($_SESSION['perms']['p_volume']) {
        print "<a href=\"" . $_CONF['url_admin'] . "admin_volume.php\">:: Volume</a><br>";
    }
    if ($_SESSION['perms']['p_skip']) {
        echo "  <a href=\"" . $_CONF['url_admin'] . "admin_skip.php\">:: Skip Song</a><br>";
    }
    if ($_SESSION['perms']['p_change_perms']) {
        echo "  <a href=\"" . $_CONF['url_admin'] . "admin_users.php\">:: User Admin</a><br>";
    }
    if ($_SESSION['perms']['p_change_perms']) {
        echo "  <a href=\"" . $_CONF['url_admin'] . "admin_groups.php\">:: Group Admin</a><br>";
    }
    if ($_SESSION['perms']['p_updateDb']) {
        echo "  <a href=\"" . $_CONF['url_admin'] . "admin_updateDb.php\">:: Update Database</a><br>";
    }
    if ($_SESSION['perms']['p_sync']) {
        echo "  <a href=\"" . $_CONF['url_admin'] . "admin_sync.php\">:: Sync entries back to mp3's</a></p>";
    }
}
?>
</div>

<div id="rightnav">
	<h2>Now Playing</h2>
<p>
<?php nowPlaying(); ?>
</p>
	<h2>PlayList</h2>
<?php displayMenuQueue();
?>
<h2>About You</h2>
<?php
if (empty($_SESSION['user_id'])) {
?>
	<p>You are not logged in.</p>
	<div class="formdiv">
	<form action="<?=$_CONF['url'] . "login.php"?>" method="post">
	<h4>Username</h4>
	<p><input type="hidden" name="action" value="login">
	<input class="field" type="text" name="user" size="16" value=""></p>
	<h4>Password</h4>
	<p><input class="field" type="password" name="pw" size="16" value=""></p>
	<p><input type="checkbox" name="remember">remember me</p>
	<p><input class="button" type=submit value="Log In"></p>
	</form>
	</div>
<p style="text-align: center">
<a href="<?=$_CONF['url'] . "signup.php"?>">Sign Up Here!</a>
</p>
<?php

}
else
{
    print "<p>Hello " . $_SESSION['user'] . "</p>";
    echo "<p>Click <a href=\"" . $_CONF['url'] . "login.php?action=logout\">here</a> to logout</p>";
    echo "<p>Click <a href=\"" . $_CONF['url_admin'] . "admin_users_clear.php?clear_user_id=" . 
        $_SESSION['user_id'] . "\">here</a> to clear your votes</p>";
    echo "<h4>Random song for your voting pleasure</h4>";

    // This is a better way to select for a random song and much faster
    // when dealing with large numbers of song ids
    require_once($_CONF['path_system'] . "classes/song.class.php");
    $randoms = random_song_ids(1);
    foreach ($randoms as $song_id) {
        $mysong = new Song($song_id, NULL);
        $mysong->read_data_from_db(NULL);
        $mysong->print_info(TRUE);
    }
}
?>

</div>
<script LANGUAGE="JavaScript" src="js/clock.js"></script>

</body>
</html>

