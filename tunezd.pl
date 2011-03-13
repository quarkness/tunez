#!/usr/bin/perl -w
# tunezd.pl
#
# This is the Tunez Perl Daemon

#
# tunez
#
# Copyright (C) 2003, Ivo van Doesburg <idoesburg@outdare.nl>
#  
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#

use PQueue;
use strict;

my %var;

# ---------------------
# Configuration Options
# -------------------------------------------------------

# Mysql information (should be identical to config.inc.php)
my $mysql_dbhost = "localhost";
my $mysql_dbuser = "tunez";
my $mysql_dbpass = "";
my $mysql_dbname = "tunez";

# If your system can't play one of these filetypes set 1->0
my $allowed_filetypes = {'mp3', 1,
                         'ogg', 1};

# Your desired mode (should be the same as in config.inc.php)
my $mode = "local-perl";
#my $mode = "shout-perl";

# Your government type (should be the same as in config.inc.php)
my $government_type = "democracy";  # or socialism

# Your random query type for determining what to play when no songs
# are in the queue.  The "weighted" selection takes into account more
# popular songs while "unweighted" is completely random.
my $random_query_type = "unweighted";

# The paths to your binaries for playing back in local mode
my $mpg123_binary = "/usr/bin/mpg123";
my $ogg123_binary = "/usr/bin/ogg123";

# If you are using icecast v1.x and shoutcast you must set the information
# here
my $shoutcast_binary = "/usr/bin/shout";
my $icecast_v1_host = "localhost";
my $icecast_v1_password = "hackme";

# End Configuration Options
# --------------------------------------------------------

sub playsong ($$);

my $queue = PQueue->new(
    username => $mysql_dbuser,
    password => $mysql_dbpass,
    hostname => $mysql_dbhost,
    database => $mysql_dbname,
    allowed_filetypes => $allowed_filetypes,
    government_type => $government_type,
    random_query_type => $random_query_type,
);

$queue->connect();
$queue->generate_from_votes();

while(1) {
    $queue->read();
    %var = $queue->dequeue();
    print "----------------------------\n";
    print "filename = $var{'filename'}\n";
    print "type = $var{'type'}\n\n";
    playsong($var{'filename'}, $var{'type'});
}


sub playsong ($$) {
    my $filename = shift;
    my $type = shift;
    my $cmd;
    
    if ($mode eq "shout-perl") {
        $cmd = "$shoutcast_binary -x -3 -t -P $icecast_v1_password $icecast_v1_host \"$filename\" > /dev/null 2> /dev/null";
    }
    elsif ($mode eq "local-perl") {
        if($type eq "ogg") {
            $cmd = "$ogg123_binary -b 1024 \"$filename\" > /dev/null 2> /dev/null";
        }
        else { #if($type eq "mp3" || $type eq "id3") {    (assuming mp3)
            $cmd = "$mpg123_binary -b 1024 \"$filename\" > /dev/null 2> /dev/null";
        }
    }
    else {
        die "Mode $mode is not compatable with tunezd.pl... please see the INSTALL file";
    }
    system($cmd);
}
