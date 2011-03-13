#include "module.h"
#include "stdio.h"

#define APPNAME "mpg123"
#define PORT 6300

int main(int argc, char* argv[]) {
    Module *mpg123 = new Module(APPNAME, "1.0", 0, "localhost", PORT, "TEST");
    mpg123->do_connect();
   
    delete mpg123;
    return 0;
}

int Module::play_file(string filename) {
    printf("playing filename %s\n",filename.c_str());
    return 0;
}

int Module::pause() {
    printf("pausing\n");
    return 0;
}

int Module::move(bool abs_or_rel, int seconds) {
    printf("move()\nabs_or_rel=%d\nseconds=%d\n\n",abs_or_rel,seconds);
    return 0;
}

int Module::volume(bool abs_or_rel, int modification) {
    return 0;
}
