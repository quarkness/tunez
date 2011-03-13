#ifndef _TUNEZD_H
#define _TUNEZD_H

struct helo_data_function {
    char app_name[32];
    char app_ver[32];
    int app_grp;
    char passwd[256];
};

union data_func_data {
    struct helo_data_function;
    char data[512];
};

struct data_function {
    char function_code[4];
    int data_size;
    char *data;
};

struct client_cap {
    struct client_cap *next;
    struct group *owner;
    
    char *name;
    char *version;
    int sock;
    
    unsigned short can_play;
    unsigned short can_pause;
    unsigned short can_move;
    unsigned short can_volume;
    unsigned short can_halt;
};

// wraper for the linked list of client capabilities
// which belong to each group
struct client_cap_wrp {
    struct client_cap *current;
    struct client_cap *next;
};

struct group {
    int group_id;
    char *name;
    struct group *next;
    struct client_cap *begin;
};
#endif
