CC	= gcc
CFLAGS  = 
LIBS    = 
INSTALL = install
INSTALLPREFIX = /usr/local

detach: detach.c
	$(CC) $(CFLAGS) $< $(LIBS) -o $@

install: detach detach.1
	$(INSTALL) -s -m 755 detach $(INSTALLPREFIX)/bin
	$(INSTALL) detach.1 $(INSTALLPREFIX)/man/man1

clean:
	rm -f detach
