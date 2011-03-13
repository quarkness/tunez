#!/usr/bin/perl
use strict;
use warnings;
use LWP::UserAgent;
use IPC::Open2;

# User Configuration Values:

# $hostname
# This is the hostname to connect to (or IP address).  If you're running this
# on your own computer, simply use the default
my $hostname = "localhost";

# $control_password
# This MUST be the same thing as $control_password in config.inc.php.
# NOTE:  This is NOT the same thing as the database password
my $control_password = "gandalf";

# $server_path
# This is the path within the server.  If Tunez is running in the root
# directory of the webserver, just leave this alone.  Here's an example:
# my $server_path = "~user/"
my $server_path = "tunez/";
my $URL = "http://$hostname/$server_path" . "admin_control.php";

# $mpg123_executable
# This is the path to the mpg123 executable and the important command line
# options, -R for remote control via a pipe and the 'asdf' can very well be
# anything, you have to pass something though.  Don't ask me, read the mpg123
# manpage.. weird stuff.
my $mpg123_executable = "/usr/bin/mpg123-oss -b 1024 -R asdf";

my $ice_exec = "/usr/bin/shout";

# $mode
# blah blah blah . . need some comments
# options: mpg123, mplayer, icecast
my $mode = "mpg123";


# END user configuration variables.

my $ua = LWP::UserAgent->new();
my $pid;
my $line;
my $song;
my $flag=0;
my $debug=0;
local (*Reader, *Writer);

# Functions...
sub initialize($) {
    my $executable = shift;
    return open2(\*Reader, \*Writer, $executable);
}

sub pause {
    print Writer "PAUSE";
}

sub cleanup($) {
    my $pid = shift;
    print Writer "QUIT";
    close Writer;
    close Reader;
    waitpid($pid, 0);
}

sub queue() {
    my $req = new HTTP::Request POST => $URL;
    $req->content_type('application/x-www-form-urlencoded');
    $req->content("auth_pw=$control_password&request=queue");
    my $response = $ua->request($req);
    my $song = $response->content;
    print "got content: $song\n";
    return $song;
}
    
sub advance() {
    my $req = new HTTP::Request POST => $URL;
    $req->content_type('application/x-www-form-urlencoded');
    $req->content("auth_pw=$control_password&request=next");
    my $response = $ua->request($req);
    my $check_next = $response->content;
    print "response after add: $check_next\n";
    return $check_next;
}

sub set_random($) {
    my $song_id = shift;
    my $req = new HTTP::Request POST => $URL;
    $req->content_type('application/x-www-form-urlencoded');
    $req->content("auth_pw=$control_password&request=set_random&song_id=$song_id");
    my $response = $ua->request($req);
    my $check_next = $response->content;
    print "response after set_random: $check_next\n";
    return $check_next;
}

sub loop() {
    my $data = queue();
    $data =~ /random: (\d)\n/;
    
    if ($1) {   # song is random
        $data =~ /song_id: (\d+)\n/;
        my $song_id = $1;
        my $response = set_random($song_id);
        
    }
    else {      # song is normal
        my $response = advance();
    }

    if ($data =~ /filename: (.*?)\n/) {
        my $filename = $1;
        
        if ($mode eq "mpg123") {
            print Writer "LOAD $filename";
            while ($line = <Reader>) {
                if ($line =~ /No such file or directory/) {
                    return -1;
                }
                #elsif ($line =~ /ERROR/) {
                    #    return -1;
                    #}
                if ($line =~ /^\@P 0$/) {
                    last;
                }
            }
        } elsif ($mode eq "icecast") {
            `$ice_exec -P letmein "$filename"`;
        }
    
    }
    return 0;
}

# end of Functions...

$pid = initialize($mpg123_executable);
if ($debug) {
    my $req = new HTTP::Request POST => $URL;
    $req->content_type('application/x-www-form-urlencoded');
    $req->content("auth_pw=$control_password&request=print");
    my $response = $ua->request($req);
    my $song = $response->content;
    print $song;
    cleanup($pid);
    exit;
}
while (1) {
    $flag = loop();
    if($flag == -1) {
        # mpg123 died for some unknown reason
        cleanup($pid);
        if ($mode eq "mpg123") { 
            $pid = initialize($mpg123_executable);
        } 
    }
}
cleanup($pid);

# END
1;
