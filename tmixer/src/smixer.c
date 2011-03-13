/*
 * smixer - a simple interface to /dev/mixer
 * Copyright (C) 2000 David Johnson
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 * or view it online at http://www.gnu.org/copyleft/gpl.html
 * 
 */

#define _GNU_SOURCE

#include <stdio.h>
#include <sys/ioctl.h>
#include <sys/soundcard.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <string.h>
#include <unistd.h>
#include <stdlib.h>
#include <getopt.h>

/* defines */
#define SMIXER_VERSION            "v1.0.3"
#define SMIXER_VERSION_DATE       "2003-06-09"

#define SIMXER_MAX_VOL            25700
#define SIMXER_MAX_NAME_LEN       11
#define SMIXER_MIXER_DEV          "/dev/mixer"
#define SMIXER_STR_SIZE           100
#define SMIXER_PLUS_MINUS_AMOUNT  5
#define DELAY                     5000


#define max2(a,b) ( ((a) > (b)) ? (a) : (b) )
#define min2(a,b) ( ((a) < (b)) ? (a) : (b) )
#define max3(a,b,c) ( max2((a),max2((b),(c))) )
#define min3(a,b,c) ( min2((a),min2((b),(c))) )
#define between(min,a,max) ( max2(min2((a),(max)),(min)) )



/* globals */
const char *smixer_dev_names_const[] = SOUND_DEVICE_LABELS;
char smixer_dev_names[SOUND_MIXER_NRDEVICES][SIMXER_MAX_NAME_LEN+1];

/* structs */
typedef struct
{
  int devmask;
  int recsrc;
  int recmask;
  int stereo;
  int vols[SOUND_MIXER_NRDEVICES];
} smixer_data_t;

/* functions */
static void smixer_remove_spaces_and_caps(void);
static void smixer_read_mixer(int do_all);
static void simxer_write_mixer(char *filename, int do_all);
static int smixer_get_data(int fd, smixer_data_t *data);
static void smixer_print_data(const smixer_data_t *data, int do_all);
static char* smixer_nocaps (char *text);
static void smixer_print_help(void);

/* main */

int main(int argc, char *argv[])
{
  int do_p = 0;
  int do_s = 0;
  int do_a = 0;
  int ok = 1;
  char *filename = NULL;
  int retval;
  const struct option longopts[] = 
    {
      { "print",  0, NULL, 'p' },
      { "all",    0, NULL, 'a' },
      { "set",    2, NULL, 's' },
      { 0 }
    };
  
  while ( 1 )
    {
      retval = getopt_long(argc, argv, "pas::", longopts, NULL);
      
      if (retval < 0)
        {
          break;
        }
      
      switch (retval)
        {
        case 'p':
          do_p = 1;
          break;
          
        case 'a':
          do_a = 1;
          break;
          
        case 's':
          do_s = 1;
          filename = optarg;
          break;
          
        default:
          ok = 0;
          break;
        }
    }

  if (optind < argc)
    {
      ok = 0;
    }
  
  smixer_remove_spaces_and_caps();
  
  if ( ok && do_p && !do_s )
    {
      smixer_read_mixer(do_a);
    }
  else if ( ok && do_s && !do_p )
    {
      simxer_write_mixer(filename, do_a);
    }
  else
    {
      fprintf(stderr,
              "smixer " SMIXER_VERSION " (" SMIXER_VERSION_DATE ") Copyright (C) 2000-2003 David Johnson\n"
              "  http://centerclick.org/programs/smixer/\n"
              "  smixer@centerclick.org\n"
              "usage: %s [-a] -p           (print settings)\n"
              "usage: %s [-a] --set [file] (set settings from file or stdin)\n",
              argv[0],argv[0]);
      return (1);
    }
  
  return (0);
}


static void smixer_read_mixer(int do_all)
{
  int mixer_fd;
  smixer_data_t data;
  
  if ( ( mixer_fd = open(SMIXER_MIXER_DEV,O_RDWR) ) < 0 )
    {
      perror("open " SMIXER_MIXER_DEV);
      goto error_out;
    }
  
  if ( smixer_get_data(mixer_fd,&data) < 0 )
    {
      goto error_out;
    }
  
  smixer_print_data(&data, do_all);
  
 error_out:
  
  if (mixer_fd > 2)
    {
      close(mixer_fd);
    }
  
  return;
}

static void simxer_write_mixer(char *filename, int do_all)
{
  int step;
  int mixer_fd=0, i, num_args, found;
  FILE *config_file=NULL;
  smixer_data_t data;
  char templine[SMIXER_STR_SIZE];
  char args[3][SMIXER_STR_SIZE];
  int vol1, vol2;
  
  if ( !filename || !strcmp(filename,"-") )
    {
      config_file = stdin;
    }
  else
    {
      if ( ( config_file = fopen(filename,"r") ) == NULL )
        {
          perror("open config");
          goto error_out;
        }
    }
  
  fflush(stdout);
  
  if ( ( mixer_fd = open(SMIXER_MIXER_DEV,O_RDWR) ) < 0 )
    {
      perror("open " SMIXER_MIXER_DEV);
      goto error_out;
    }
  
  while ( fgets(templine,SMIXER_STR_SIZE,config_file) )
    {
      
      memset(args[0],0,SMIXER_STR_SIZE);
      memset(args[1],0,SMIXER_STR_SIZE);
      vol1 = -1;
      vol2 = -1;
      
      num_args = sscanf(templine,"%s %s %s\n",args[0],args[1],args[2]);
      
      if ( num_args == 0 || args[0][0] == '\0' || args[0][0] == '#' )
        {
          continue;
        }
      
      if ( args[0][0] == 'v' )
        {
          found=0;
          smixer_nocaps(args[1]);
          
          if (num_args < 3)
            {
              fprintf(stderr,"vol: missing argument\n");
              continue;
            }
          
          if ( smixer_get_data(mixer_fd,&data) < 0 )
            {
              goto error_out;
            }
          
          for (i=0; i<SOUND_MIXER_NRDEVICES; i++)
            {
              if ( data.devmask & (1 << i) && !strcmp(args[1],smixer_dev_names[i]) )
                {
                  found++;
                  
                  if ( !strcmp(args[2],"+") ) /* + 5 % */
                    {
                      vol1 = between(0, data.vols[i] + (SMIXER_PLUS_MINUS_AMOUNT*SIMXER_MAX_VOL/100), SIMXER_MAX_VOL);
                    }
                  else if ( !strcmp(args[2],"-") ) /* - 5 % */
                    {
                      vol1 = between(0, data.vols[i] - (SMIXER_PLUS_MINUS_AMOUNT*SIMXER_MAX_VOL/100), SIMXER_MAX_VOL);
                    }
                  else
                    {
                      vol1 = (int)((float)SIMXER_MAX_VOL * (float)atoi(args[2]) / 100.0);
                    }
                  
                  if (vol1 < 0 || vol1 > SIMXER_MAX_VOL)
                    {
                      fprintf(stderr,"vol: value out of range\n");
                      continue;
                    }
                  vol2 = (data.vols[i]&0xff) + ((data.vols[i]&0xff)<<8);
                
                  if (vol1 < vol2) {
                    step = -0x101;
                  }
                  else { //if (vol2 < vol1) {
                    step = 0x101;
                  }
                
                  while (vol1 != vol2) {
                      vol2 += step;
                      if (ioctl(mixer_fd,MIXER_WRITE(i),&vol2) < 0) {
                          perror("write vol");
                          goto error_out;
                      }
                      usleep(DELAY);
                  }
                  data.vols[i] = vol2;

                }
            }
          
          if (!found)
            {
              fprintf(stderr,"vol: name not found: %s\n",args[1]);
            }
        }
      
      else if ( args[0][0] == 'r' )
        {
          found=0;
          smixer_nocaps(args[1]);
          
          if ( smixer_get_data(mixer_fd,&data) < 0 )
            {
              goto error_out;
            }
          
          for (i=0; i<SOUND_MIXER_NRDEVICES; i++)
            {
              if ( data.recmask & (1 << i) && !strcmp(args[1],smixer_dev_names[i]) )
                {
                  found++;
                  vol1 = (1 << i);
                  
                  if ( ioctl(mixer_fd,SOUND_MIXER_WRITE_RECSRC,&vol1) < 0 )
                    {
                      perror("write vol");
                      goto error_out;
                    }
                  
                  if ( ioctl(mixer_fd,SOUND_MIXER_READ_RECSRC,&vol2) < 0 )
                    {
                      perror("comfirm vol");
                      goto error_out;
                    }
                  
                  data.recsrc = vol2;
                  
                  if ( vol1 != vol2 )
                    {
                      fprintf(stderr,
                              "comparision failed: tried 0x%.8X got 0x%.8X\n",
                              vol1,vol2);
                    }
                }
            }
          
          if (!found)
            { 
              fprintf(stderr,"recsrc: name not found: %s\n",args[1]);
            }
  
        }

      else if ( args[0][0] == 's' )
        {
          if ( smixer_get_data(mixer_fd,&data) < 0 )
            {
              goto error_out;
            }
          
          smixer_print_data(&data, do_all);
        }
     
      else if ( (args[0][0] == 'h') || (args[0][0] == '?') )
        {
          smixer_print_help();
        }
     
      else if ( (args[0][0] == 'q') || (args[0][0] == 'e') )
        {
          goto error_out;
        }
     
      else
        {
          fprintf(stderr,"unknown command try \"help\": %s\n",args[0]);
        }
     
    }
 
 error_out:
 
  if (mixer_fd > 2) { close(mixer_fd); }
  if (config_file && (config_file != stdin)) { fclose(config_file); }
  
  return;
}

static int smixer_get_data (int fd, smixer_data_t *data)
{
  int i;
  
  memset(data,0,sizeof(smixer_data_t));
  
  if ( ioctl(fd,SOUND_MIXER_READ_DEVMASK,&data->devmask) < 0 )
    {
      perror("read SOUND_MIXER_DEVMASK");
      return -1;
    }
  
  if ( ioctl(fd,SOUND_MIXER_READ_RECSRC,&data->recsrc) < 0 )
    {
      perror("read SOUND_MIXER_RECSRC");
      return -1;
    }
  
  if ( ioctl(fd,SOUND_MIXER_READ_RECMASK,&data->recmask) < 0 )
    {
      perror("read SOUND_MIXER_RECMASK");
      return -1;
    }
  
  if ( ioctl(fd,SOUND_MIXER_READ_STEREODEVS,&data->stereo) < 0 )
    {
      perror("read SOUND_MIXER_STEREODEVS");
      return -1;
    }
  
  for (i=0; i<SOUND_MIXER_NRDEVICES; i++)
    {
      if ( data->devmask & ( 1 << i) )
        {
          if ( ioctl(fd,MIXER_READ(i),&data->vols[i]) < 0 )
            {
              perror("read SOUND_MIXER_STEREODEVS");
              return -1;
            }
        }
    }
  
  return 0;
}

static void smixer_print_data (const smixer_data_t *data, int do_all)
{
  int i;
  
  printf("ID: Name      Play  Rec   Channels  Volume\n"
         "--  --------  ----  ----  --------  ------\n");
  
  for (i=0; i<SOUND_MIXER_NRDEVICES; i++)
    {
      if ( (data->devmask | data->recmask) & (1 << i) )
        {
          printf("%2i: %-8s  %-4s  %-4s  %-8s  %5.0f%%\n",
                 i,
                 smixer_dev_names_const[i],
                 (data->devmask & (1 << i)) ? (data->vols[i] ? "on" : "off" ) : "-",
                 (data->recmask & (1 << i)) ? ((data->recsrc & (1 << i)) ? "on" : "off" ) : "-",
                 (data->stereo  & (1 << i)) ? "stereo" : "mono",
                 100.0*((float)data->vols[i])/((float)SIMXER_MAX_VOL));
        }
      else if (do_all)
        {
          printf("%2i: %-8s  -     -     -              -\n",i,smixer_dev_names_const[i]);
        }
    }
  return;
}


static void smixer_print_help(void)
{
  
  printf("\nsmixer commands:\n"

         "\nvol [name] [value|-|+]\n"
         "  'vol' sets the volume for a specific input or output device\n"
         "  'name' is the name of the device, do smixer -p to get a list\n"
         "  'value' is the percentage volume (no %%)\n"
         "    or \"-\" to decrease 5%% or \"+\" to increase 5%%\n"
 
         "\nrecsrc [name]\n"
         "  sets the recording source to a specific input device\n"
         "  'name' is the name of the device, do smixer -p to get a list\n"
 
         "\nshow\n"
         "  prints the same list as 'smixer -p' does\n"
         "\nhelp|?\n"
         "  gets help\n"

         "\nend|exit|quit|q\n"
         "  exits\n"

         );
  return;
}


static void smixer_remove_spaces_and_caps(void)
{
  int i,j;
  
  for (i=0; i<SOUND_MIXER_NRDEVICES; i++)
    {
      strncpy(smixer_dev_names[i],smixer_dev_names_const[i],SIMXER_MAX_NAME_LEN);
      smixer_nocaps(smixer_dev_names[i]);
      for (j=0; smixer_dev_names[i][j]; j++)
        {
          if ( smixer_dev_names[i][j] == ' ')
            {
              smixer_dev_names[i][j] = '\0';
            }
        }
    }
  return;
}

static char* smixer_nocaps(char *text)
{
  int i;
  
  if (text)
    {
      for (i=0; text[i]; i++)
        {
          if ( text[i] >= 'A' && text[i] <= 'Z' )
            {
              text[i] += 'a' - 'A';
            }
        }
    }
  return text;
}
