#include <arpa/inet.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <netdb.h>
#include <sys/types.h>
#include <netinet/in.h>
#include <sys/socket.h>
#include <iostream>
#include <string>

#define DEBUG
#include "../dmtdebug.h"
#include "module.h"

#define MAXDATASIZE 1000 // max number of bytes we can get at a time
#define MAX_APPLICATION_NAME = 20

using namespace std;

Module::Module(string appname, string version, int group, string hostname, int port, string password) {
    id = -1;
    this->hostname = hostname;
    this->appname = appname;
    this->version = version;
    this->port = port;
    this->group = group;
    this->password = password;
    cout << "ok.. connecting to port " << port << " on " << hostname << endl;
}

Module::~Module() {
}

int Module::do_connect() {

    int sockfd, numbytes;
    fd_set readfds;
    string buf[MAXDATASIZE];
    string protocol;
    string temp;
    struct hostent *he;
    struct sockaddr_in their_addr; // where the connector is coming from

    if((he = gethostbyname( hostname.c_str() )) == NULL) {
        BDEBUG("Unable to get hostname");
        exit(1);
    }
    
    if((sockfd = socket(AF_INET, SOCK_STREAM, 0)) == -1) {
        BDEBUG("Socket error");
        exit(1);
    }

    FD_ZERO(&readfds);

    their_addr.sin_family = AF_INET;  // host byte order
    their_addr.sin_port = htons(port);  // network byte order...
    their_addr.sin_addr = *((struct in_addr *) he->h_addr);
    memset(&(their_addr.sin_zero), '\0', 8);  // zeroing rest of the struct
    
    //if ( connect(sockfd, (struct sockaddr *) &their_addr, sizeof(struct sockaddr)) == -1) {
    //    BDEBUG("Connection error");
    //    exit(1);
    //}
   
    // send initial data...
    
    temp = appname + '\0' + version + '\0' + password;
    cout << "temp is this big: " << temp.size() << endl;
    printf("here goes: %s.20", temp.c_str());

    exit(1); 
    printf("%s", temp.c_str());
    cout << "new is this big: " << strlen(temp.c_str()) << endl;
    printf("\n\n\n\n");
    char *blah;
    (void *) blah = malloc(500);
    sprintf(blah, "%s%c%s%c%s", appname.c_str(), '\0', version.c_str(), '\0', password.c_str());
    cout << temp << endl;
    printf("%s", blah);
    cout << "hi there... yay";
    exit(0);
    
    
    //if ( send(sockfd, data, len, 0) == -1) {
    //    BDEBUG("Sending data error")
    //    exit(1);
    //}
    
    while(1) {
        if ( select(sockfd+1, &readfds, NULL, NULL, NULL) == -1) {
            BDEBUG("Select error!");
        }

        for (int i = 0; i <= sockfd+1; i++) {
            if (FD_ISSET(i, &readfds)) { // we heard input on the socket
                if ((numbytes = recv(i, buf, sizeof(buf), 0)) == -1) {
                    BDEBUG("Receive error!");
                    exit(1);
                }

                //protocol = strncpy(protocol, &buf, 4);
                //printf("protocol = %s\n",protocol);
            }
        }
                    
        buf[numbytes] = '\0';
        //printf("Received: %s",buf);
        close(sockfd);

        return 0;
    }
}
