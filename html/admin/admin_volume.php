<?php
# admin_volume.php
# 
# The volume page

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

if (!($_SESSION['perms']['p_volume'])) {
    header(access_denied());
	die;
}

if (empty($_CONF['mixer_devices']))  {
    $_SESSION['messageTitle'] = "Error!";
    $_SESSION['messageBody'] = "There are no authorized mixing 
        channels enabled in config.inc.php";
    $_SESSION['messageError'] = TRUE;
    require("../header.inc.php");
    require("../footer.inc.php");
    return;
}

$title = "Turn up the volume!";
require("../header.inc.php");

$smixer_cmd = $_CONF['smixer_binary'] . " -p";
$smixer_output = `$smixer_cmd`;
$smixer_results = explode("\n", $smixer_output);

$device = array();
$volume = array();

// skip first two lines
for($i=2; $i < count($smixer_results); $i++)
{
    $thisdevice = trim(substr($smixer_results[$i],4,8));
    $thisvolume = (int) substr($smixer_results[$i],33);
    if (in_array($thisdevice, $_CONF['mixer_devices'] )) {
        $device[] = $thisdevice;
        $volume[] = $thisvolume;
    }
}

if(count($device) == 0) {
    echo "The smixer utility hasn't been compiled, the webserver doesn't have access to 
    the mixing device or none of the mixing channels in config.inc.php are valid";
}
else {

    ?>
    <SCRIPT src="<?=$_CONF['url']?>js/slider3.js"></SCRIPT>
    <SCRIPT>

    function writeSliders() {
        <?php
            for($i=0;$i < count($device);$i++)
            {
                echo "window." . $device[$i] . "Slider = new Slider(\"" . $device[$i] . "Slider\");\n";
                echo $device[$i] . "Slider.imgPath = \"" . $_CONF['url_images'] . "\";\n";
                echo $device[$i] . "Slider.onchange = \"document.frictionForm." . $device[$i] . "Input.value=toDecimals(this.getValue(),0);\";\n";
                echo $device[$i] . "Slider.displaySize = 3;\n";
                echo $device[$i] . "Slider.leftValue = 100;\n";
                echo $device[$i] . "Slider.rightValue = 0;\n";
                echo $device[$i] . "Slider.defaultValue = " . $volume[$i] . ";\n";
                echo $device[$i] . "Slider.orientation = \"v\";\n\n";
                echo $device[$i] . "Slider.writeSlider();\n\n";
                //echo "<tr><td>$device</td><td><input type=\"text\" name=\"device[$device]\" value=\"$volume\"></td></tr>";
            }
            ?>
    }

    function onLoad() {
        <?php
            for($i=0;$i < count($device);$i++)
            {
                echo $device[$i] . "Slider.placeSlider(\"" . $device[$i] . "Rail\");\n";
            }
            ?>
    }
    </SCRIPT>
    <form name=frictionForm method="get" action="<?=$_CONF['url_admin'] . "admin_volume_action.php"?>">

    <table>
    <tr>
    <?php
        for($i=0;$i < count($device);$i++)
        {
            echo "<td>" . $device[$i] . "<br>";
            echo "<img src=\"" . $_CONF['url_images'] . "/sliderbg.gif\" name=\"" . $device[$i] . "Rail\" ALIGN=\"middle\">";
            echo "</td>\n";
        }
    ?>


    </tr>
    <tr>
    <?php
        for($i=0;$i < count($device);$i++)
        {
            echo "<td><INPUT onchange=" . $device[$i] . "Slider.setValue(this.value) size=5 value=$volume[$i] name=" . $device[$i] . "Input></td>\n";
        }
    ?>
    </tr>
    </table>
    <input value="Submit" class="button" type="submit"><br>
    </form>

    <SCRIPT>
    writeSliders();
    onLoad();
    </SCRIPT>

    <?php
}

require("../footer.inc.php");
?>
