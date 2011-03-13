/*
 * Don't search the path if a pathname is given
 *  (if a '/' appears in argv[1]);
 * exit if argc==1;
 * Don't trash the PATH variable (put back colons!)
 *
 * This is a grungy little program for executing programs in the
 * background, without use of a control terminal. (In the style
 * of most common daemon processes...) The intent was to create a
 * program one could start via rsh, to initiate xterm sessions,
 * without keeping extra local rsh & remote rshd and shell processes
 * alive.
 *
 * Could be a bit less cavalier about return codes...
 *
 * No warrantees. Use at your own risk. The authors are not responsible
 * in any way for anything at all.
 *
 */

#ifndef lint
static char rcsid[] = "$Id: detach.c,v 1.1 2003/01/28 06:12:56 plowman Exp $";
#endif

/*
 * Times before retrying fork if fork fails temporarily (in seconds)
 */
#define RETRY_INITIAL 1
#define RETRY_MAX 4

#ifdef WAITSTART
/*
 * Constants controlling wait for subprocess startup
 */
#define LOAD_LIMIT  1.0	/* Load limit, below this no wait */
#define SEC_QUIET 4	/* Seconds with quiet child before quitting */
#define Q_FAULTS 1	/* Number of pagefaults allowed when quiet */
#endif

#ifdef SYS_V
#include <fcntl.h>
#endif  /* SYS_V */

#include <stdio.h>
#include <signal.h>
#include <unistd.h>
#include <sys/fcntl.h>
#include <sys/ioctl.h>
#include <sys/types.h>
#include <sys/wait.h>

#ifdef SYS_V
#define index strchr
#endif /* SYS_V */

#ifdef WAITSTART
#include <kvm.h>
#include <sys/types.h>
#include <sys/param.h>
#include <sys/time.h>
#include <sys/proc.h>
#include <sys/user.h>
#include <sys/resource.h>
#if defined(sequent)
# include <i386/vmparam.h>
#endif
#include <nlist.h>
#endif

extern int errno;
#include <sys/errno.h>

/* 
 * Fork in a safe fashion:  Check error codes and retry if temporary failure
 */
int safe_fork()
{
    int cc, retry = RETRY_INITIAL;
    
    while ((cc = fork()) == -1 && errno == EAGAIN)
    {
	sleep(retry);
	if (retry < RETRY_MAX) retry *= 2; /* Exponential backoff */
    }

    if (cc == -1)			/* Fatal error */
    {
	perror("Can't fork");
	exit(-1);
    }

    return cc;
}    

#ifdef WAITSTART
/*
 * Get load average
 */

#ifdef HAS_KVM
#define KVM_T kvm_t *
#else
#define KVM_T int
#endif

struct	nlist nl[] = {
	{ "_avenrun" },
	{ 0 },
};

static KVM_T kmem_init()
{
    KVM_T kmem;

#ifdef HAS_KVM
    kmem = kvm_open((char *)NULL, (char *) NULL, (char *) NULL,
		    O_RDONLY, "detach");
    if (!kmem)
	exit(-1);
#endif    

#ifdef sgi
# include <sys/sysmp.h>
    nl_avenrun[0].n_value = sysmp(MP_KERNADDR, MPKA_AVENRUN) & 0x7fffffff;
#else

# ifdef HAS_KVM
    kvm_nlist(kmem, nl);
# else
    nlist("/vmunix", nl);
# endif
    if (nl[0].n_type==0) {
        fputs("detach: /vmunix has no namelist\n",stderr);
	exit(-1);
    }
#endif

#ifndef HAS_KVM
     if((kmem = open("/dev/kmem", 0)) == -1) {
	 perror("detach: can't open(/dev/kmem)");
	 exit(-1);
     }
#endif

    return kmem;
}

#ifndef HAS_KVM
int kvm_read(kd, addr, buf, nbytes)
int kd;
unsigned long addr;
char *buf;
unsigned nbytes;
{
    if( lseek(kd, addr, 0) == -1 )
    {
	return -1;
    }
    return read(kmem, addr, nbytes);
}
#endif

static getload(a, kmem)
float a[];
KVM_T kmem;
{
	int i;
#ifdef vax
	double avenrun[3];
#else
	long avenrun[3];
#endif

	if (kvm_read(kmem, nl[0].n_value, (char *) avenrun, sizeof(avenrun))
	    != sizeof(avenrun))
	{
	    perror("detach: can't read kmem");
	    exit(-1);
	}

	for (i = 0; i < 3; i++)
#if defined(sun) || defined(sequent)
		a[i] = (float) avenrun[i] / FSCALE;
#else 
#ifdef sgi
		a[i] = (float) avenrun[i] / 1024;
#else
#ifdef BSD4_2
		a[i] = (float) avenrun[i];
#else 
		a[i] = (float) avenrun[i] / 1024;
#endif /*BSD4_2*/
#endif /*sgi*/
#endif /*sun*/
	return;
}

/*
 * Wait for process pid to finish initialization and sleep.
 */
waitstart(pid)
int pid;
{
    float loadavg[3];
    struct proc *pd;
    struct user *u;
    long prev_faults = 0;
    int count = 0;
    KVM_T kmem;

    /*
     * Open kmem and get namelist
     */
    kmem = kmem_init();
    
    /* 
     * First, test load average.  Priority one is fast turnaround
     * if machine is lightly loaded.
     */
    getload(loadavg, kmem);
    if (loadavg[0] < (float) LOAD_LIMIT) return;

    /*
     * Then, hang around until child process is quiescent.
     */
    for(;;)
    {
	pd = kvm_getproc(kmem, pid);
	if (!pd)
	{
	    fputs("detach: couldn't read proc entry of child\n", stderr);
	    exit(-1);
	}

	if (pd->p_stat == SZOMB)
	    break;		/* No point in going on, child died */

	u = kvm_getu(kmem, pd);
	if (!u)
	{
	    fputs("detach: couldn't read u structure of child\n", stderr);
	    exit(-1);
	}

#ifdef DEBUG	
	printf("cpu = %d, slptime = %d, cpticks = %d, pctcpu = %ld \n", 
	       pd->p_cpu,pd->p_slptime,pd->p_cpticks,pd->p_pctcpu);
	printf("minflt = %ld, majflt = %ld, nswap = %ld, nvcsw = %ld, nivcsw = %ld \n",
	       u->u_ru.ru_minflt, u->u_ru.ru_majflt, u->u_ru.ru_nswap, u->u_ru.ru_nvcsw, u->u_ru.ru_nivcsw);
#endif
	
	if ((u->u_ru.ru_majflt - prev_faults) <= Q_FAULTS)
	{
	    count++;
	    if (count > SEC_QUIET) break;
	}
	else
	    count = 0;

	prev_faults = u->u_ru.ru_majflt;
	sleep(1);
    }
    
    kvm_close(kmem);
}
#endif

/*
 * Create daemon subprocess
 */
detach(cmd, args)
char *cmd;
char **args;
{
    int fd, pid, status;

#ifdef SIGTTOU
signal(SIGTTOU, SIG_IGN);
signal(SIGTTIN, SIG_IGN);
signal(SIGTSTP, SIG_IGN);
#endif /* SIGTTOU */

    if (pid = safe_fork())
    {					/* parent */
#ifdef WAITSTART
	waitstart(pid);			/* wait for child to get going */
#else
	sleep(1);
#endif

#ifdef SYS_V
	exit(0);
#else
	if (waitpid(pid, &status, WNOHANG))
	{
	    if (WIFEXITED(status))
	    {
		fprintf(stderr,"detach: child process exited with status %d\n",
			WEXITSTATUS(status));
		exit(WEXITSTATUS(status));
	    }
	    else if (WIFSIGNALED(status))
	    {
		fprintf(stderr,"detach: child process killed by signal %d\n",
			WTERMSIG(status));
		exit(-1);
	    }
	    else
		exit(0);
	}
	else
	    exit(0);
#endif
    }
#ifdef WAITSTART
    setegid(getgid());			/* May be running setgid kvm */
#endif


/*
 * Three alternate ways of losing controlling terminal is here.
 * The new, posix way is the default.
 * Define OLDBSD or OLDSYSV if you don't have setsid.
 */
    
#if defined(OLDSYSV)
    /* child */
    setpgrp();			/* lose controlling terminal & */
				/*  change process group       */
    signal(SIGHUP, SIG_IGN);	/* immune from pgrp leader death */
    if (safe_fork())		/* become non-pgrp-leader */
	exit(0);

    /* second child */
#elif defined(OLDBSD)
    setpgrp(0, getpid());	/* change process group */
#else
    if (setsid() == -1) {
       perror("Can't setsid");
       exit(1);
    }
#endif

#ifdef TIOCNOTTY
    if ((fd = open("/dev/tty", O_RDWR)) >= 0) {
	ioctl(fd, TIOCNOTTY, 0); /* lose controlling terminal */
	close(fd);
    }
#endif

    /*
     * Close all open file descriptors.
     * We may want to keep those which goes to a file open, so you
     * can do "detach make >& file" - later.
     * This is non-portable. Blame IBM, whose getdtablesize return 2 Giga.
     */
    for (fd=0; fd < 256; fd++)
	close(fd);

    /* 
     * ensure that we have stdin/stdout/stderr open to *somewhere*
     */

    open("/dev/null", O_RDONLY);
    dup2(0, 1);
    dup2(0, 2);

    /* umask(0); */ /* don't change this! inherit instead. -he */

    /* Set signals back to default */
    signal (SIGHUP, SIG_DFL);
#ifdef SIGTTOU
    signal(SIGTTOU, SIG_DFL);
    signal(SIGTTIN, SIG_DFL);
    signal(SIGTSTP, SIG_DFL);
#endif /* SIGTTOU */

    /* spawn off the wanted command */
    execv(cmd, args);

    /* If that didn't work, return an error */
    perror(cmd);
    exit(1);
}

main(argc, argv)
int             argc;
char           *argv[];
{
    char           *path, *getenv(), *index(), *i, *fixcolon, j[1024];

    if (argc == 1)
        exit(0);

    /* if the cmd to run in daemon mode has a '/' in it, the path
     * has been specified, so don't search the path for it;
     */
    if (index(argv[1], '/')) {
	detach(argv[1],&argv[1]);
    }
    else {
	path = getenv("PATH");
	while (path) {
	    fixcolon=0;
	    i = index(path, ':');
	    if (i) {
		*i = 0;
		fixcolon=i;
		i++;
	    }
	    strcpy(j, path);
	    strcat(j, "/");
	    strcat(j, argv[1]);
	    
	    if (fixcolon)
		*fixcolon = ':';
	    
	    if (!access(j, X_OK || F_OK)) {
		detach(j, &argv[1]);
	    }
	    path = i;
	}
    }

}
