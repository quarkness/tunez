

WARNS=			\
-Wshadow		\
-Wpointer-arith		\
-Wcast-qual		\
-Wcast-align		\
-Wstrict-prototypes	\
-Wmissing-prototypes	\
-Wmissing-declarations	\
-Winline		\
-Wwrite-strings		\
-Wall

CPPFLAGS   = $(WARNS)
CFLAGS     = -O2
LFLAGS     = 

CC=gcc
RM=rm -f
INSTALL=install
TOUCH=touch
CD=cd
MV=mv
TAR=tar
GZIP=gzip

INS_BIN=/usr/local/bin
INS_ETC=/etc
INS_MAN=/usr/local/man/man1


all: smixer

smixer: Makefile src/smixer.o
	$(CC) $(LDFLAGS) src/smixer.o $(LOADLIBES) $(LDLIBS) -o smixer

smixer.o: Makefile src/smixer.c
	$(CC) -c $(CPPFLAGS) $(CFLAGS) -o src/smixer.o src/smixer.c

clean:
	$(RM) smixer core src/*.o *~ */*~

install: all
	$(INSTALL) -o root -g root -m 0755 smixer $(INS_BIN)/smixer
	$(INSTALL) -o root -g root -m 0644 man/smixer.1 $(INS_MAN)/smixer.1
	$(INSTALL) -o root -g root -m 0644 smixer.conf $(INS_ETC)/smixer.conf

tar: clean
	$(TOUCH) * src/*
	$(CD) ..; $(TAR) -cf \
		smixer/smixer.tar \
		smixer/src/smixer.c \
		smixer/Makefile \
		smixer/smixer.conf \
		smixer/README \
		smixer/COPYING \
		smixer/man/smixer.1
	$(GZIP) smixer.tar
	$(MV) smixer.tar.gz smixer.tgz
