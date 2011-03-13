#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>

#define DEBUG
//#undef DEBUG
#include "dmtdebug.h"
#include "tunezd.h"


/* set this to the port you wish to listen on           */
/* leave this at the default for the best compatability */
#define LISTEN_PORT 6300

/* here you can modify the number of incoming modules   */
/* you wish to allow to connect. 10 should be plenty    */
/* for most people                                      */
#define MAX_CONNECT 10

// global data structures are typically a bad thing, but . . oh well
struct avail_clients *client_list;
struct group *master_grp;


// valid_client
// takes a socket number and determines if that socket is in the list
//   of authenticated sockets
int valid_client(int socket) {
    struct client_cap *aPtr;
    struct group *bPtr;
    
    aPtr = master_grp->begin;
    bPtr = master_grp;
    while (bPtr != NULL || aPtr->sock != socket) {
        while (aPtr != NULL || aPtr->sock != socket)
            aPtr = aPtr->next;
        
        if (aPtr == NULL) {
            bPtr=bPtr->next;
            aPtr=bPtr->begin;
        }
    }
    
    if (aPtr == NULL)
        return -1;

    return 1;
    
}

// parse_function
// takes a character array message from a socket read and parses it appropriatly
void parse_function(char *inc_msg) 
{
    //TODO finish this function :)
    struct data_function *mesg;
    
    mesg = (struct data_function *)inc_msg;

    if (strncmp(mesg->function_code, "HELO", sizeof(4)) == 0) {
        GDEBUG("HELO SENT, WOO");     
    }
}


// free_client
// takes the master group (remove soon) and a socket number, and removes
//   that client from the list of authorized clients and the group which 
//   it belongs to
void free_client(int sock) 
{
    struct group *aGPtr;
    struct client_cap *aPtr, *bPtr;
    
    aGPtr = master_grp;
    while (aGPtr != NULL) {
        aPtr = bPtr = aGPtr->begin;
        while (aPtr != NULL) {
            bPtr = aPtr;
            aPtr = aPtr->next;
            if (sock == bPtr->sock) {
                //delete
                aPtr->next = bPtr->next;
                free(bPtr->name);
                free(bPtr);
                return;
            }
        }
        aGPtr = aGPtr->next;
    }
}


// free_group
// takes the group id and removes this group from the list of groups
//   along with any clients belonging to it
void free_group(int gid) 
{
    struct client_cap *aPtr, *bPtr;
    struct group *aGPtr, *bGPtr;

    DEBUGDO(fprintf(stderr, "[+] freeing group %d", gid));
    
    aGPtr = master_grp;
    bGPtr = master_grp;
    while (aGPtr != NULL || aGPtr->group_id == gid) { 
        bGPtr = aGPtr;
        aGPtr = aGPtr->next;
    }

    if (aGPtr == NULL)
        return;

    bGPtr->next = aGPtr->next;
        
    aPtr = aGPtr->begin;
    while (aPtr != NULL) {
        bPtr = aPtr;
        aPtr = aPtr->next;
        free(bPtr->name);
        free(bPtr);
    }
    
    aGPtr->begin = NULL;
    free(aGPtr);
}

// fucking lack of overloading in C
/*
void init_group(struct group *master_grp) 
{
    master_grp=malloc(sizeof(struct group));
    master_grp->group_id = 0;
    master_grp->name = malloc(sizeof("default"));
    strcpy(master_grp->name, "default");
    master_grp->next = NULL;
    master_grp->begin = NULL;
}
*/


// init_group 
// takes a group id and the name of the group and returns a pointer
//   to the group which it creates
struct group* init_group(int group_id, char *name) 
{
    struct group *new_group;

    new_group = malloc(sizeof(struct group));
    new_group->group_id = group_id;
    new_group->name = malloc(sizeof(name)+1);
    strcpy(new_group->name, name);
    new_group->begin = NULL;
}

// find_group
// takes a group ID and returns a pointer to the object with that group ID
//   or NULL if none exists
struct group* find_group(int gid) 
{
    struct group *aPtr;

    aPtr = master_grp;
    while (aPtr != NULL || aPtr->group_id == gid)
        aPtr = aPtr->next;

    if (aPtr == NULL)
        return NULL;

    return aPtr;
}

// push_group
// adds the new group to the end of the linked list of groups
void push_group(struct group *new) 
{
    struct group *aPtr;

    aPtr = master_grp;
    while (aPtr->next != NULL)
        aPtr = aPtr->next;
    
    aPtr->next = new;
    new->next = NULL;
}

struct client_cap* init_client(char *name, unsigned short can_play, 
        unsigned short can_pause, unsigned short can_move, unsigned short can_volume, 
        unsigned short can_halt) 
{
    // whew, that's a mouth full .  . .

    struct client_cap *client;
    client = malloc(sizeof(struct client_cap));
    
    client->name = malloc(sizeof(name)+1);
    strcpy(client->name, name);
    client->can_play = can_play;
    client->can_pause = can_pause;
    client->can_move = can_move;
    client->can_volume = can_volume;
    client->can_halt = can_halt;
}

// push_client
// pushes a client into the linked list inside of a group
void push_client(struct client_cap *client, struct group *grp) 
{
    struct client_cap *current;

    client->owner = grp;
    
    current = grp->begin;
    while (current->next != NULL)
        current = current->next;
    
    current->next = client;
}

// list_clients
// this is mostly a debuging function that lists all clients in a certain group
char* list_clients(struct group *grp) 
{
    struct client_cap *aPtr;
    char *aBuf, *bBuf;
    
    aPtr = grp->begin;
    aBuf = malloc(1);
    while (aPtr != NULL) {
        bBuf = malloc(sizeof(aPtr->name) + sizeof(aPtr->version) + sizeof("  on socket \n"));
        sprintf(bBuf, "%s %d on socket %d\n", aPtr->name, aPtr->version, aPtr->sock); 
        
        aBuf = realloc(aBuf, sizeof(aBuf) + sizeof(bBuf));
        strcat(aBuf, bBuf);
        
        free(bBuf);
        bBuf = NULL;
        aPtr = aPtr->next;
    }
    
    return aBuf; // maybe the addr goes there, I forget
}

int main(int argv, char **argc)
{
    int listenSocket;
    int conHosts; // this will work for now
			       // make sure to invent something to 
			       // watch which are free sometime soon
    int curCon; // count for var above and below me, making one unified struct for this
                // would be a good idea . . .
    struct sockaddr_in conAddr; // same as above
    struct sockaddr_in listen_addr;
    int sin_size, rec_size;
    char rec_buf[256];
    char *data_buf;
    struct data_function *rec_ptr;
    struct data_function command;
    //struct group *master_grp;
    int group_count = 0 ;
    
    fd_set master;
    fd_set read_fd;
    fd_set write_fd;
    int fdmax; 
    char packetbuf[256];        //buffer holding data from/to network
    int i, j;        
#ifdef DEBUG
    char *pstr, *dbuffer;

    pstr = malloc(sizeof(LISTEN_PORT)+sizeof("[Port ] "));
    sprintf(pstr, "[Port %d] ", LISTEN_PORT);

    dbuffer = malloc(100); // 100 characters should be good enough
#endif

    master_grp = init_group(group_count, "Default");
    ++group_count;
    
    FD_ZERO(&master);
    FD_ZERO(&read_fd);
    FD_ZERO(&write_fd);
        
    listenSocket = socket(AF_INET, SOCK_STREAM, 0);
    
    if (listenSocket == -1) {
	BDEBUG("socket init failed");
    } else {
	GDEBUG("socket established");
    }

    listen_addr.sin_family = AF_INET;
    listen_addr.sin_port = htons(LISTEN_PORT);
    listen_addr.sin_addr.s_addr = INADDR_ANY; 
    memset(&(listen_addr.sin_zero), '\0', 8);
   

    DEBUGDO(strcpy(dbuffer, pstr)); // heh, what a hack, but it's only for debuging
    
    if (bind(listenSocket, (struct sockaddr *)&listen_addr, sizeof(struct sockaddr)) == -1) {
	DEBUGDO(strcat(dbuffer, "bind failed"));
	BDEBUG(dbuffer);
    } else {
	DEBUGDO(strcat(dbuffer, "bind successfull"));
	GDEBUG(dbuffer);
    }

    DEBUGDO(strcpy(dbuffer, pstr));

    if (listen(listenSocket, MAX_CONNECT) == -1) {
	BDEBUG("listen failed");
    } else {
	DEBUGDO(strcat(dbuffer, "listening for connections"));
	GDEBUG(dbuffer);
    }

    FD_SET(listenSocket, &master);
    fdmax = listenSocket;
    
    curCon = 0; // no one has connected yet . . . 

    // TODO remove this host from list of available hosts
    //      write the loop to handle multiple connections
   
    // main client listening loop
    while (1)
    {
        read_fd = master;
        if (select(fdmax+1, &read_fd, NULL, NULL, NULL) == -1)
        {
            BDEBUG("select failed");
        }
        GDEBUG("select triggered");
            
        for (i=0; i <= fdmax; i++)
        {
            if (FD_ISSET(i, &read_fd)) {
                if (i == listenSocket) {
                    sin_size = sizeof(conAddr);
                    conHosts = accept(listenSocket, (struct sockaddr *)&conAddr, &sin_size);
                    if (conHosts == -1) {
                        BDEBUG("accept failed");
                    } else {
                        // yay a new connection
                        
                        FD_SET(conHosts, &master);
                        if (conHosts > fdmax)
                            fdmax = conHosts;
                        DEBUGDO(fprintf(stderr, "[+] new connection from %s\n", inet_ntoa(conAddr.sin_addr)));
                    }
                    
                } else {
                    for (j=0; j<256; j++)
                        rec_buf[j] = '\0';
                   // replace 8 with sizeof(rec_buf) 
                    if ((rec_size = recv(i, rec_buf, 8, 0)) <= 0) {
                        if (rec_size == 0) {
                            GDEBUG("client disconnecting");
                        } else {
                            BDEBUG("recv fuckup");
                        }
                        close(i);
                        FD_CLR(i, &master);
                    } else {
                        (void *)rec_ptr = (struct data_format *)rec_buf;
                        
                        DEBUGDO(fprintf(stderr, "[!] function code: %s\n[!] size of data: %d\n", rec_ptr->function_code, rec_ptr->data_size));
                        
                        data_buf = malloc(rec_ptr->data_size);
                        
                        if ((rec_size = recv(i, data_buf, rec_ptr->data_size, 0)) <= 0) {
                            BDEBUG("failed receving data");
                        }
                        
                        DEBUGDO(fprintf(stderr, "[!] Data Received: %s\n", data_buf));
                        
                        //DEBUGDO(fprintf(stderr, "[!] size: %d got data: %s\n AND %d", sizeof(rec_buf), rec_ptr->function_code, rec_ptr->data_size));
                        
                        
                        //exit(1);
                        // client sent me data
                        //BDEBUG("I got data . . or something like that");
                        
                    }
                }
                
            } 
            DEBUGDO(sprintf(dbuffer, "%d ", curCon));
/*
            sin_size = sizeof(struct sockaddr_in);
            conHosts = accept(listenSocket, (struct sockaddr *)&conAddr, &sin_size);
            if (conHosts == -1) {
                DEBUGDO(strcat(dbuffer, "<- broken connection"));
                BDEBUG(dbuffer);
            } else {
                DEBUGDO(strcat(dbuffer, "<- connection established"));
                GDEBUG(dbuffer);
            }

*/    
        }
    }
    
    close(listenSocket);
	
    return 0;
}
