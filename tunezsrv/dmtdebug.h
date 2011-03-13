#ifdef DEBUG
#define GDEBUG(x) fprintf(stderr, "[+] %s\n", x);
#define BDEBUG(x) fprintf(stderr, "[-] %s\n", x); return -1;
#define DEBUGDO(x) x
#else
#define GDEBUG(x) "/* x */"
#define BDEBUG(x) "/* x */"
#define DEBUGDO(x) "/* x */"
#endif

