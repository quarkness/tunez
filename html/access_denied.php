<?php
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
$title = "Access Denied!";

require("header.inc.php");

echo "<h2>$title</h2>";
echo "<p>The page you were trying to access ( <a
href=\"$_GET[page]\">" . htmlentities($_GET[page]) . "</a> ) requires additional privileges, sorry....</p>";
//echo "<p>click <a href=\"$prevPage\">here</a> to go to the page you came from.</p>";

require("footer.inc.php");
?>
