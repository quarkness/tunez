all: MODULE MPG123
clean:
	rm -f *.o daemon_*

MODULE: module.cc
	g++ -ggdb -Wall -c module.cc
MPG123: module.o
	g++ -ggdb -Wall mpg123.cc module.o -o tunezd_mpg123
OGG123: module.o
	g++ -ggdb -Wall ogg123.cc module.o -o tunezd_ogg123
