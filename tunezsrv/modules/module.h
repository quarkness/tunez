#ifndef MODULE_H
#define MODULE_H

#include <iostream>
#include <string>

using namespace std;

struct data_function {
    char function_code[5];
    int data_size;
    char *data;
};

class Module {
    public:
        Module(string appname, string version, int group, string hostname, int port, string password);
        virtual ~Module();
        virtual int play_file(string filename);
        virtual int pause();
        virtual int move(bool abs_or_rel, int seconds);
        virtual int volume(bool abs_or_rel, int modification);
        int do_connect();

    private:
        int id;         // unique id number assigned after we connect
        int group;      // the group this application is part of
        string hostname; // place we're connecting to
        string appname;     // the type of program we are
        string version;     // our version
        int port;       // port we're connecting to
        string password;
};

#endif
