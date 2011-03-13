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

$justconfig = TRUE;
require("tunez.inc.php");

if (preg_match("/ices/i", $_CONF['mode']) || preg_match("/shout/i", $_CONF['mode'])) {
    header("Content-type: audio/x-scpls");
    header("Content-Disposition: attachment; filename=\"tunez.pls\"");
    print "[playlist]\n";
    print "numberofentries=1\n";
    print "File1=" . $_CONF['icecast_URL'] . "\n";
    print "Title1=Tunez\n";
    print "Length1=-1\n";
}
else {
    die("You are not in icecast mode");
}
