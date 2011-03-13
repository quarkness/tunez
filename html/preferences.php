<?php
# preferences.php
#
# Set user preferences

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

if(!empty($_GET['new_songs_per_page'])) {
    
    $nspp = (int) $_GET['new_songs_per_page']; // New Songs Per Page
    $user_id = $_SESSION['user_id'];

    if ($nspp < 1) {
        $_SESSION['messageBody'] = "ERROR!";
        $_SESSION['messageTitle'] = "You must use a postive value for number of display results per page.";
    }
    elseif($nspp > 100) {
        $_SESSION['messageBody'] = "ERROR!";
        $_SESSION['messageTitle'] = "You are limited to a maximum of 100 songs per page";
    }
    else {
        $_SESSION['songsperpage'] = $nspp;
        $_SESSION['messageBody'] = "Success";
        $_SESSION['messageTitle'] = "You will now see $nspp songs per page";
        $query = "REPLACE into preferences VALUES('$user_id','$nspp')";
        tunez_query($query);
    }
    header("Location: " . $_SERVER[HTTP_REFERER]);
}
else {
    $title = "Your Preferences";
    require("header.inc.php");
    ?>
        <div class="formdiv">
        <p>
        <form action="<?php echo $_SERVER[PHP_SELF]; ?>">
        <h4>Number of songs per page:</h4>
        <input class="field" type=text name=new_songs_per_page 
        value="<?php echo $_SESSION['songsperpage']; ?>" size=4><br>
        <input class="button" type=submit name=action value=update>
        </form>
        </p>
        </div>
        <?php
        require("footer.inc.php");
}
?>
