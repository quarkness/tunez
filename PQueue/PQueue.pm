package PQueue;

use 5.006;
use strict;
use warnings;
use DBI;

require Exporter;
use AutoLoader qw(AUTOLOAD);

our @ISA = qw(Exporter);

# Items to export into callers namespace by default. Note: do not export
# names by default without a very good reason. Use EXPORT_OK instead.
# Do not simply export all your public functions/methods/constants.

# This allows declaration	use PQueue ':all';
# If you do not need this, moving things directly into @EXPORT or @EXPORT_OK
# will save memory.
our %EXPORT_TAGS = ( 'all' => [ qw(
	
) ] );

our @EXPORT_OK = ( @{ $EXPORT_TAGS{'all'} } );

our @EXPORT = qw();

our $VERSION = '0.21';

sub new {
    my $invocant = shift;
    my $class = ref($invocant) || $invocant;
    my $self = { 
        hostname => "localhost",
        username => "tunez",
        password => "",
        database => "tunez",
        allowed_filetypes => {'mp3', 1,
                              'ogg', 1},
        government => "",
        government_type => "democracy",
        random_query => "",
        random_query_type => "unweighted",
        queue => [],
        @_,
    };

    bless($self, $class);
    

    # Assigns limits on which filetypes are allowed into the random song
    # selection SQL
    my $filetype;
    my $filetype_limit = "";
    my $filetype_sql1 = "type = '";
    my $filetype_sql2 = "' OR ";
    foreach $filetype (keys %{($self->{'allowed_filetypes'})} ) {
        if ($self->{'allowed_filetypes'}{$filetype} == 1) {
            $filetype_limit .= $filetype_sql1 . $filetype . $filetype_sql2;
            # "type == 'mp3' OR " for example
        }
    }
    $filetype_limit .= "0";
    # ends "type == 'mp3' OR ... 0"

    #=====================================================
    # Government types
    #=====================================================
    my $democratic_government = 
        "SELECT songs.song_id, songtitle, length, artists.artist_name,
        albums.album_name, count(*) AS votes, MIN(timestamp) as timestamp FROM queue
        LEFT JOIN songs ON queue.song_id=songs.song_id
        LEFT JOIN artists on songs.artist_id=artists.artist_id 
        LEFT JOIN albums on songs.album_id=albums.album_id
        WHERE songs.status != 'offline' AND songs.status != 'delete' 
        GROUP BY filename 
        ORDER BY votes DESC, timestamp";
    my $socialistic_government =
        "SELECT songs.song_id, songtitle, length, artists.artist_name,
        albums.album_name, count(*) AS votes,
        count(*) + (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(timestamp))/(60*10) AS score FROM queue
        LEFT JOIN songs using(song_id)
        LEFT JOIN artists on songs.artist_id=artists.artist_id
        LEFT JOIN albums on songs.album_id=albums.album_id
        WHERE songs.status != 'offline' AND songs.status != 'delete'
        GROUP BY filename
        ORDER BY score DESC, timestamp";
 
    
    #=====================================================
    # Random queue selection types
    #=====================================================
    my $unweighted_random_query = "SELECT * FROM songs WHERE playInRandomMode=1
        AND status != 'offline' AND status != 'delete' AND ($filetype_limit)
        ORDER BY rand() LIMIT 1";
    my $weighted_random_query = "SELECT * FROM songs WHERE playInRandomMode=1 
        AND status != 'offline' AND status != 'delete' AND ($filetype_limit)
        ORDER BY sqrt(SQRT(timesPlayed+100))*RAND() DESC LIMIT 1";

    # Unless the government and random query are explicitly passed we
    # pick them based on the government_type and random_query_type
    # paramaters which have sane defaults
    if (length($self->{'government'})==0) {
        # They have not overriden the government variable thus we choose
        # from one of the preexisting options
        if ($self->{'government_type'} eq "socialism") {
            $self->{'government'} = $socialistic_government;
        }
        else {
            $self->{'government'} = $democratic_government;
        }
    }
    if (length($self->{'random_query'})==0) {
        # They have not overriden the random select SQL variable thus we
        # choose from one of the preexisting options
        if ($self->{'random_query_type'} eq "weighted") {
            $self->{'random_query'} = $weighted_random_query;
        }
        else {
            $self->{'random_query'} = $unweighted_random_query;
        }
    }
    return $self;
}

sub connect {
    my $self = shift;
    #print "connecting... to $self->{'hostname'} database $self->{'database'} with username $self->{'username'} & pw $self->{'password'}\n";
    my $dbh = DBI->connect("DBI:mysql:$self->{'database'}:$self->{'hostname'}","$self->{'username'}","$self->{'password'}") or die;
    $self->{'dbh'} = $dbh;   
}

sub delete_from_queue_table ($$) {
    my $self = shift;
    my $songid = shift;
    my $query = "DELETE from queue WHERE song_id = $songid";
    my $sth = $self->{'dbh'}->prepare($query) or die;
    $sth->execute();
}

sub clear_SQL {
    my $self = shift;
    my $sth = $self->{'dbh'}->prepare("DELETE from priority_queue");
    $sth->execute();
}

sub clear {
    my $self = shift;
    $self->{'queue'} = [ ];
}

sub insert {
    my $self = shift;
    my $song_id = shift;
    push( @{$self->{'queue'}}, $song_id);
}

sub lock ($$) {
    my $self = shift;
    my $write = shift;
    if ($write) {
        my $sth = ($self->{'dbh'})->prepare("LOCK TABLES priority_queue WRITE");
        $sth->execute();
    }
    else {
        my $sth = $self->{'dbh'}->prepare("LOCK TABLES priority_queue READ");
        $sth->execute();
    }
}

sub unlock {
    my $self = shift;
    my $sth = $self->{'dbh'}->prepare("UNLOCK TABLES");
    $sth->execute();
}

sub read {
    my $self = shift;
    my $query = "SELECT priority,song_id from priority_queue ORDER BY priority";
    my $sth = $self->{'dbh'}->prepare($query);
    my $results = $sth->execute();
    $self->clear();
    if ($sth->rows < 1) {
        return;
    }
    while (my $row = $sth->fetchrow_hashref()) {
        push(@{$self->{'queue'}}, $row->{'song_id'});
    }
}

sub dequeue {
    my $self = shift;
    # local variables
    my ($query, $sth, @results, $song_id);
    my ($is_random, $row, $type, $filename);
    my ($artist_name, $album_name, $songtitle, $timesPlayed);

    do {
        @results = $self->top();
        $song_id = $results[0][0];
        $is_random = $results[0][1];
        #print "song_id = $song_id : is_random = $is_random\n";

        # get rid of old top of queue
        $query = "DELETE from priority_queue WHERE priority=1";
        $sth = $self->{'dbh'}->prepare($query);
        $sth->execute();

        # shift everything up one position
        $query = "UPDATE priority_queue SET priority=priority-1";
        $sth = $self->{'dbh'}->prepare($query);
        $sth->execute();

        # pop from the local copy of the priority queue just in case this song
        # isn't playable and we repeat the while loop
        pop @{$self->{'queue'}};

        $query = "SELECT
        songtitle,filename,type,album_name,artist_name,timesPlayed FROM songs
        LEFT JOIN artists ON songs.artist_id=artists.artist_id
        LEFT JOIN albums ON songs.album_id=albums.album_id 
        WHERE song_id = $song_id";
        $sth = $self->{'dbh'}->prepare($query);
        $sth->execute();
        $row = $sth->fetchrow_hashref();

        $type = $row->{'type'};
        $filename = $row->{'filename'};
        #print "song_id = $song_id\n";
    
        if ($self->{'allowed_filetypes'}{$type} == 0) {
            # This shouldn't be possible once allowed_filetypes is enforced
            # server side
            print "ERROR: Not allowed to play $type files.  Song $filename skipped!\n";
            $self->delete_from_queue_table($song_id);
            $self->clear_SQL();
            $self->clear();
            $self->generate_from_votes();
            $self->read();
        }
    } while ($self->{'allowed_filetypes'}{$type} != 1);

    # We have presumably found a song id which is playable

    # The remainder of the local variable initialization
    $artist_name = $row->{'artist_name'};
    $album_name = $row->{'album_name'};
    $songtitle = $row->{'songtitle'};
    $timesPlayed = $row->{'timesPlayed'};

    # insert record into play to denote when we played song
    $query = "INSERT INTO play (song_id, timestamp) VALUES ('$song_id', NOW())";
    $sth = $self->{'dbh'}->prepare($query);
    $sth->execute();
    my $play_id = $self->{'dbh'}->{'mysql_insertid'};
    my $time = time();
    #FIXME possible problem with NOW() and time() not matching

    # clean out and update now playing table
    $query = "DELETE from np";
    $sth = $self->{'dbh'}->prepare($query);
    $sth->execute();
    $query = "INSERT INTO np (song_id, play_id, started, wasrandom) 
        VALUES('$song_id', '$play_id', '$time', '$is_random')";
    $sth = $self->{'dbh'}->prepare($query);
    $sth->execute();
    
    $query = "INSERT INTO caused (history_id, play_id) 
        SELECT history_id, '$play_id' FROM queue WHERE song_id='$song_id'";
    $sth = $self->{'dbh'}->prepare($query);
    $sth->execute();

    if (!($is_random)) {
        # only delete from actual queue and update counter when song isn't
        # random
        $query = "DELETE FROM queue WHERE song_id = '$song_id'";
        $sth = $self->{'dbh'}->prepare($query);
        $sth->execute();
        $query = "UPDATE songs SET timesPlayed=timesPlayed + 1 WHERE song_id = $song_id";
        $sth = $self->{'dbh'}->prepare($query);
        $sth->execute();
    }

    return ( 
        song_id => $song_id,
        filename => $filename,
        type => $type,
        artist_name => $artist_name,
        songtitle => $songtitle,
        album_name => $album_name,
        timesPlayed => $timesPlayed,
    );
}

sub size {
    my $self = shift;
    return scalar @{$self->{'queue'}};
}

sub top {
    my $self = shift;
    my $is_random;

    if (scalar @{$self->{'queue'}} == 0) {
        $is_random = 1;
        my $sth = $self->{'dbh'}->prepare($self->{'random_query'}) or die;
        my $results = $sth->execute();
        my $row = $sth->fetchrow_hashref();
        $self->insert($row->{song_id});
    }
    else {
        $is_random = 0;
    }
    return [$self->{'queue'}[0], $is_random];
}

sub printqueue {
    my $self = shift;
    for (my $i=0; $i < scalar @{$self->{'queue'}}; $i++) {
        print $self->{'queue'}[$i] . "\n";
    }
}

sub generate_from_votes() {
    my $self = shift;
    my $count = 1;
    my $sth = $self->{'dbh'}->prepare($self->{'government'}) or die;
    my $results = $sth->execute();
    $self->lock(1);
    $self->clear_SQL();
    while (my $row = $sth->fetchrow_hashref()) {
        #print "inserting $row->{'song_id'} into queue...\n";
        my $query = "INSERT into priority_queue VALUES ($count, $row->{'song_id'})";
        my $insert_sth = $self->{'dbh'}->prepare($query);
        $insert_sth->execute();
        $count++;
    }
    $self->unlock();
}

sub DESTROY {
    my $self = shift;
    $self->{'dbh'}->disconnect();
}

# Preloaded methods go here.

# Autoload methods go after =cut, and are processed by the autosplit program.

1;
__END__
# Below is stub documentation for your module. You'd better edit it!

=head1 NAME

PQueue - Perl class to handle access to a Tunez priority queue

=head1 SYNOPSIS

use PQueue;

my $mysql_dbhost = "localhost";
my $mysql_dbuser = "tunez";
my $mysql_dbpass = "";
my $mysql_dbname = "tunez";
my $allowed_filetypes = {'mp3', 1,
                         'ogg', 1};

my $queue = PQueue->new(
    username => $mysql_dbuser,
    password => $mysql_dbpass,
    hostname => $mysql_dbhost,
    database => $mysql_dbname,
    allowed_filetypes => $allowed_filetypes,
    government_type => "democracy",
    random_query_type => "unweighted",
)
$queue->connect();
$queue->generate_from_votes();
while (1) {
    $queue->read();
    my %hash = $queue->dequeue();
    #  Do stuff with whatever dequeue() returns
}

=head1 ABSTRACT

  This should be the abstract for PQueue.
  The abstract is used when making PPD (Perl Package Description) files.
  If you don't want an ABSTRACT you should also edit Makefile.PL to
  remove the ABSTRACT_FROM option.

=head1 DESCRIPTION

PQueue is a perl module for interfacing with the open source project
Tunez.  It's use is fairly simple (for a better example see tunezd.pl)

There are several optional paramaters you can pass to the PQueue class when
creating it, otherwise sane defaults will be used.  See below for options.

$queue->dequeue() returns a hash with the following values, along with updating
Tunez to display the new song (assuming you're going to end up playing it at some
point)
    song_id, filename, type, songtitle, artist_name, album_name, timesPlayed

Required PQueue paramaters when instantiating class:
* username, password, hostname, database: For connecting to mySQL database

Optional PQueue paramaters when instantiating class:
* allowed_filetype: A hash which describes which filetypes are OK to play.  By
    default OGG's and MP3's are marked playable.  Here is an example of how to
    disallow ogg files, for example:

    allowed_filetypes => {'mp3', 1,
                          'ogg', 0,},  # MP3's ok, OGG's not ok

* government_type: Determines the order Tunez plays back songs.  You must be
using the same type as in the config.inc.php file otherwise your website will
not match what is played!
    government_type => "democracy", # the default
    government_type => "socialism", # for a more "progressive" Tunez queue

* random_query_type: Determines whether Tunez will use voting history to try
to play more popular songs when nobody is voting or if it will be completely
random.
    random_query_type => "unweighted", # the default
    random_query_type => "weighted", # takes into account play history
    
* government: Override the government SQL explicitly
* random_query: Override the random song-selection SQL explicitly
    
=head2 EXPORT

None by default.

=head1 SEE ALSO

http://tunez.sourceforge.net/
http://www.sourceforge.net/projects/tunez
http://tunez.yhbt.com

=head1 AUTHOR

Philip Lowman, E<lt>philip@yhbt.comE<gt>

=head1 COPYRIGHT AND LICENSE

PQueue (for tunez)

Copyright (C) 2003, Philip Lowman <philip@yhbt.com>
   
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

=cut
