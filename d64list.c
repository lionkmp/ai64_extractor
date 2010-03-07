/*
D64 lister and file extractor V0.2  (c) Lion of Chromance 1998

You can use this program to list your .D64 emulator files,
and extract them into binary c64 files on Unix.

Using: d64list [-lcef] [disk_image.d64] [disk_image2.d64] ...
  -l = use lowercase charset (like CBM+SHIFT)
  -c = start in copy mode. You can accept the default filename
       by pressing enter or type a new one
  -e = don't add extensions (.prg,.seq,etc) to the default filenames
       in copy mode
  -f = print diskimage filenames

If there is no input filename given, the program uses the standard
input, reads the first d64 file.

lion@c64.rulez.org
*/

#include <stdio.h>
#include <ctype.h>
#include <stdlib.h>
#include <unistd.h>
#include <fcntl.h>
#include <sys/stat.h>
#include <errno.h>
#define EMPTY '.'
#define TRUE 1
#define FALSE 0
#define D64SIZE (256*683)

char *filetypes[] = {"DEL","SEQ","PRG","USR","REL","???","???","???" };
int fext=TRUE;               /* file extension for default copy names */
int lowercase = FALSE;       /* lowercase chars for listing */
int copymode = FALSE;        /* -c = copy mode */
int namepr = FALSE;          /* print d64 filenames too */
unsigned char d64[683][256]; /* the disk image */

/*** loads the d64 file into an array (from stdin if fname==NULL) ***/
int d64load(char *fname) {
 int i,fil,alldatas;
 char temp[1];

 if (fname!=NULL) {                   /* opening the file, if not stdin */
     if ((fil = open(fname,O_RDONLY)) < 0) {
        printf("d64list: cannot open %s\n",fname);
        close(fil); return(FALSE);
        }
 	i=read(fil,d64,D64SIZE);                          /* loading the datas */
 	if (i!=D64SIZE) {
		printf("d64list: error reading %s (too small for a .D64 file)\n",fname);
		close(fil); return(FALSE);
		}
     i=read(fil,temp,1);                /* checking if file is too long */
     if (i!=0) {
        printf("d64list: error reading %s (too big for a .D64 file)\n",fname);
        close(fil); return(FALSE);
        }
     close(fil);
    }
 else {                        /* loading from stdin, continously trying */
 	alldatas=0;
 	while (alldatas!=D64SIZE) {
		i=read(STDIN_FILENO,d64+alldatas,D64SIZE-alldatas);
		alldatas+=i;
		printf("%d %d\n",alldatas,i);
		perror("d64list");
		if (i<0) exit(1);
		}
	}

 return(TRUE);
}

/*** calculate the index in the d64[] array of a sector and track ***/
int d64seek(int trk,int sec) {
  int pos=0;   /*  1  2  3  4  5  6  7  8  9 10 11 12 13 14 15 16 17 18
                  19 20 21 22 23 24 25 26 27 28 29 30 31 32 33 34 35 */
  static int sectors[]={
                  21,21,21,21,21,21,21,21,21,21,21,21,21,21,21,21,21,19,
                  19,19,19,19,19,19,18,18,18,18,18,18,17,17,17,17,17};

  if (trk>35 || sectors[trk-1]<sec) {           /* correct request? */
    fprintf(stderr,"67,ILLEGAL TRACK OR SECTOR,%d,%d\n",trk,sec);
    exit(1);
    }

  trk--;                                          /* tracks = 0-34 */
  for (;trk>0;trk--)                                   /* counting */
	pos += sectors[trk-1];
  pos += sec;
  return (pos);
}

/*** convert one c64 char into ASCII code for listing ***/
int d64print(int betu) {
  int nbetu;
  if (!lowercase) {
    if (betu<32) nbetu=EMPTY;
    else if (betu<65) nbetu=betu;
    else if (betu<91) nbetu=betu;
    else if (betu==91) nbetu=91;
    else if (betu==92) nbetu=EMPTY;
    else if (betu==93) nbetu=93;
    else if (betu==94) nbetu=EMPTY;
    else if (betu==95) nbetu=EMPTY;
    else if (betu==96) nbetu=45;
	else nbetu=EMPTY;
	}
  else {
	if (betu<32) nbetu=EMPTY;
	else if (betu<65) nbetu=betu;
	else if (betu<91) nbetu=betu+32;
	else if (betu==91) nbetu=91;
	else if (betu==92) nbetu=EMPTY;
	else if (betu==93) nbetu=93;
	else if (betu==94) nbetu=EMPTY;
	else if (betu==95) nbetu=EMPTY;
	else if (betu==96) nbetu=45;
	else if (betu<123) nbetu=betu-32;
	else if (betu<193) nbetu=EMPTY;
	else if (betu<219) nbetu=betu-128;
	else nbetu=EMPTY;
	}
  return(nbetu);
}

/*** generate a filename from a the ASCII coded c64 filename ***/
void d64conv(char *oname,char *nname,int tipus) {
  int first=TRUE;       /* first printable character? */
  int wordt=0;
  int i;
  i=0;
  do {
    if (first && isprint(*oname)) first=FALSE;  /* no output until the */
    if (!first) {                               /* first printable char*/
		if (isalnum(*oname))
            if (wordt!=1) {                       /* word's first char */
                *nname++=toupper(*oname);
                wordt=1;                                    /* in word */
			}
            else *nname++=tolower(*oname);
        else if (*oname=='-') {                        /* '-'? sotring */
                *nname++='-';
            wordt=2;                                /* like a new word */
			}
        else {                      /* no normal chars, but we were in */
            if (wordt==1) *nname++='_';    /* a nice word, printing '_'*/
            wordt=2;                          /* like a new word again */
		}
	}
  }
  while (*++oname!='\0');                                 /* go to end */
  while (*--nname=='_') ;               /* strip trailling underscores */
  if (fext) {                       /* adding file extension if wanted */
	*++nname='.';
	for (i=0;*(filetypes[tipus]+i);i++)
		*++nname=tolower(*(filetypes[tipus]+i));
	}
   *++nname='\0';
}

/*** save the file with fnev filename from the given track and sector ***/
int f_save(char *fnev,unsigned char trk,unsigned char sec) {
 int siz,sblokk,sfil;
 mode_t jogok;

 jogok=S_IRUSR | S_IWUSR | S_IRGRP | S_IWGRP | S_IROTH;
 if ((sfil=open(fnev,O_WRONLY|O_CREAT,jogok))>=0) {
	do {
		sblokk=d64seek(trk,sec);
        siz=d64[sblokk][0]?254:d64[sblokk][1]-1;        /* last block? */
		if (write(sfil,d64[sblokk]+2,siz)!=siz) return(1);
		trk=d64[sblokk][0]; sec=d64[sblokk][1];
		}
	while (trk!=0);
	close(sfil);
	} else {
	printf("d64list:error opening file %s",fnev);
	return(1);
	}
  return(0);
}

/*** listing one d64 file, main function ***/
void d64list(char *filenev) {
  int i,j,k,blokk,ntrk,nsec,filecount;
  int cr=TRUE;
  char attrib,sor[BUFSIZ],nname[BUFSIZ],oname[BUFSIZ];

  if (d64load(filenev)) {

  printf("\n");
  if (namepr) printf("%s:\n",filenev);                    /* d64 filename */
  blokk=d64seek(18,0);                              /* disk header and ID */
  i=0; k=d64[blokk][144]; if (k==160) k=32;
  while (i<23) {
	oname[i]=d64print(k);
	k=d64[blokk][144+(++i)]; if (k==160) k=32;
	}
  oname[i]='\0'; oname[16]='"';
  printf("0 \"%s",oname);

  /*  ntrk=d64[blokk][0]; nsec=d64[blokk][1]; not following link! */

  ntrk=18; nsec=1; filecount=0;
  do {                                                    /* listing the dir */
	blokk=d64seek(ntrk,nsec);
	for (j=0; j < 256 && filecount<144 ;j+=32) {
		attrib=d64[blokk][j+2];
		if (attrib != 0) {
            if (cr) printf("\n");         /* user sent LF coz saved a file? */
			printf("%-5d\"",d64[blokk][j+31]*256+d64[blokk][j+30]); /* blks */
			i=0; k=d64[blokk][j+5];                             /* filename */
			while (i<16 && k!=160) {
				oname[i]=d64print(k);
				k=d64[blokk][j+5+(++i)];
				}
			oname[i]='\0';
			printf("%s\"",oname);
            for (;i<16;i++) putchar(' ');               /* padding filename */
			if (attrib & 0x80) putchar(' '); else putchar('*');   /* opened */
			if (lowercase)                                      /* filetype */
				for (i=0;i<3;i++)
					putchar(tolower(*(filetypes[attrib & 0x07]+i)));
				else printf("%s",filetypes[attrib & 0x07]);
			if (attrib & 0x40) putchar(' '); else putchar('<'); /* proteced */
			cr=TRUE;
			if (copymode && (attrib & 0x07) &&                 /* copy mode */
                (attrib & 0x07) < 4 && (attrib & 0x80)) {   /* normal file? */
				do {
                    printf(" (y/n)");                      /* want to save? */
					fgets(sor,100,stdin);
					}
				while (*sor!='n' && *sor!='y');
				if (*sor=='y') {
                    d64conv(oname,nname,attrib & 0x07);     /* default name */
					printf("Destination (%s):",nname);
					fgets(sor,100,stdin); *(sor+strlen(sor)-1)='\0';
					i=f_save((*sor=='\0')?nname:sor,d64[blokk][j+3],d64[blokk][j+4]);
					cr=FALSE;
					}
				else cr=FALSE;
				}
			}
		filecount++;
		}
    nsec=d64[blokk][1];                        /* stepping on the dir track */
	}
  while((ntrk=d64[blokk][0])!=0 && filecount < 144);
  if (cr) printf("\n");

  j=0; blokk=d64seek(18,0);                          /* BAM for free blocks */
  for (i=0;i<35;i++) {
	if (i!=17) j+=d64[blokk][4+i*4];
	}
  if (lowercase) printf ("%d blocks free.\n\n",j);
	else printf("%d BLOCKS FREE.\n\n",j);
  /* return(0); */
  }
}

/*** printing help and exit program ***/
void helpexit() {
    printf("%s\n","D64 lister and extractor V0.2 by Lion of Chromance");
    printf("%s\n","Usage: d64list [-lcef] [diskname.d64] ...");
	printf("%s\n","       -l = use lowercase charset");
	printf("%s\n","       -c = copy mode");
	printf("%s\n","       -e = add extensions in copy mode");
	printf("%s\n","       -f = print input filenames");
	exit(1);
}

int main (int argc,char *argv[]) {
  char *s;

  while (--argc>0 && (*++argv)[0]=='-')         /* commandline parameters */
	for (s=argv[0]+1;*s!='\0';s++)
		switch (*s) {
		case 'c': copymode=TRUE; break;
		case 'l': lowercase=TRUE; break;
        case 'e': fext=FALSE; break;
		case 'f': namepr=TRUE; break;
        case 'h':
        case '?': helpexit();

        default: printf("d64list:Illegal option %c\n",*s); helpexit(); break;
		} 
  if (argc < 1) {          /* no file, reading standart input (hi MEpk :)  */
    d64list(NULL);
	}
  else {                                   /* listing the files one by one */
    while (argc-- > 0) {
        d64list(*argv++);
        }
    }
}

