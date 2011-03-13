use DBI;
use PQueue;

# Tunez (http://tunez.sourceforge.net) Ices/Icecast compatible script
# Originally created by Frédéric Jaume (soulfish@users.sourceforge.net) 2001/09/05
# 
# Updated by Chris Freeze (cfreeze@cfreeze.com) 2003/04/21
# Updated by Philip Lowman (lowman@uiuc.edu) 2003/06/29
#   it now works with the PQueue perl module provided with Tunez

# -----------------------------------------------------------
# Configuration Information:

# Mysql information (should be identical to config.inc.php)
my $mysql_dbhost = "localhost";
my $mysql_dbuser = "tunez";
my $mysql_dbpass = "";
my $mysql_dbname = "tunez";

# Assuming you're using Ices v0.3 the only thing you can play are MP3s
my $allowed_filetypes = {'mp3', 1,
                         'ogg', 0};

# Your government type (should be the same as in config.inc.php)
my $government_type = "democracy";  # or socialism

# Your random query type for determining what to play when no songs
# are in the queue.  The "weighted" selection takes into account more
# popular songs while "unweighted" is completely random.
my $random_query_type = "unweighted";

# End Configuration Information
# -----------------------------------------------------------

# Variables used by ices_get_metadata to describe the current song
my $artist_name=""; # Artist
my $songtitle=""; # Song title

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

# Function called to get the next filename to stream.
# Should return a string.
sub ices_get_next {
    print "Perl subsystem quering for new track:\n";

    $queue->read();
    my %var = $queue->dequeue();
    $songtitle = $var{'songtitle'};
    $artist_name = $var{'artist_name'};
    my $timesplayed = $var{'timesPlayed'};
    print "$songtitle by $artist_name played $timesplayed times.\n";

    return $var{'filename'};
}

# If defined, the return value is used for title streaming (metadata)
sub ices_get_metadata {
    return "$artist_name - $songtitle";
}

# Function used to put the current line number of
# the playlist in the cue file. If you don't care
# about cue files, just return any integer.
sub ices_get_lineno {
    return 1;
}

#if($DEBUG) {
    #$_ = &ices_get_next();
    #print "Found $_\n";
#}

sub ices_init {
    print "Perl playlist manager starting:\n";
    return 1;
}

# Function called to shutdown your python enviroment.
# Return 1 if ok, 0 if something went wrong.
sub ices_shutdown {
    print "Perl playlist manager shutting down:\n";
}


return 1; 
