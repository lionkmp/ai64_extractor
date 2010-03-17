ai64 - C64 file extractor 

Copyright (c) 2004-2010 Ferenc 'Lion/Kempelen' Veres
lion@xaraya.hu http://lion.xaraya.hu

LICENSE

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

WHAT IS THIS?

ai64 allows you to comvert complete directory structures containing
c64 programs into IDE64 compatible copy of the whole strucure. e.g.
try running it on a complete copy of Arnold Game Archive, before 
burning a CD for IDE64 usage. Most of the archive will be converted to 
.prg and .d64 files, which are very easly to use on C64!

SYSTEM REQUIREMENTS

PHP-CLI
    Which makes it possible to run PHP programs from the command line.
Linux/Unix
    Was only tested, I doubt that the program can work in non *nix environment,
    but prove me wrong and let me know (or send patches).
unzip
    Command line unzip to decompress the files.
unrar
    Command line unrar to decompress the files.
tar
    Command line tar to decompress the files.
gzip
    Command line gzip to decompress the files.
zip2disk
    Zipcode to d64 converter by Marko Makela. 
    You can grab it from ftp://ftp.funet.fi/pub/cbm
cbmconvert
    Marko Makela's converter masterpiece to handle all the c64 file formats.
    You can grab it from ftp://ftp.funet.fi/pub/cbm
d64list [Optional]
    This is a little C program I made years ago, you can grab it from
    ftp://c64.rulez.org/pub/c64/other-OS/Unix/ for example.

"d64list" external program is used to analize d64 files. Any program
which prints d64 dirlists given in the parameter list, could do the
job, if the output is similar. If this program is not available, all 
d64's containing only 1 file will be extracted. Using d64list, only those
disks are extracted where the single file size plus free blocks size 
sums to 664.

WHAT DOES IT DO?

The program will walk recursively in the sepcified source directory, 
convert all files to c64 usable format and copy them into the destination
directory structure. Thus from all your ZIP, T64, etc files, you will 
just have a big directory which is directly usable on your IDE64 CDROM.

Currently the following formats are handled (anything can be nested, 
e.g. d64 in zip and so on):

TXT: not copied
DIZ: not copied
ME:  not copied
NFO: not copied
COM: not copied
EXE: not copied
DEL: not copied (D64 extraction dirt)
ZIP: uncompressed and re-processed 
D64: extraced if 1 file only (and valid BAM if d64list available. Hi-score
     files - if identified - are ignored if there is only 1 more file. Some
     BAM validation messages in names/headers are taken into consideration.)
T64: extraced to files (if there is a single file named "FILE" is inside, 
     the name of the original t64 is used when saving it to prg.)
P00: extracted to normal files
PRG: copied as is
TAR: extracted and re-processed
GZ : extracted and re-processed
TGZ: extracted and re-processed
RAR: extracted and re-processed
LNX: converted to d64 and re-processed
ZIPCODE: (1!,2!..) converted to d64 and re-processed

Files starting with dot are not copied (Unix hidden files, FTP site messages).

Not listed files: copied as is.

Support is missing for: BZIP2, LHA (lzh).

All the stored files are saved with characters which are readable on C64.
Filenames are converted to 16 + extension, IDE64 can handle this length 
(requires 0.9x or higher IDEDOS). Already existing files are not 
overwritten, the new file will get "-1", "-2".. index, thus running the
program twice will create all destination files twice.

When the whole directory structure conversion is finished, ai64 will take
another long walk. It will rearrange all the directories which contain 
more than 100 files, to make sure there are no more than 100 files in a
directory (easier to handle on c64, MAN, etc). The created subdirs will
be called "ai100-X", "ai200-X" and so on, where "X" means the first word
of the first program in that dirctory. E.g. "ai300-blackjack".

INSTALLATION

Edit ai64 executable file if necessary: 
1. Customize the location of your php interpreter in the first line. 
2. Set the location of your d64list program or set it to empty string to 
   bypass analizig d64 contents. 
3. Make sure the $tmp_dir is far from any important locations, because the
   program will run thousands of "rm -rf" commands inside it (honestly, I 
   create a "lion2" user just to run the program, you never know... 
   especially while developing it.) See ramdisk tip below.
4. Customize the location of "cbmconvert" executable as well.
5. Make sure the other decompress executables are on your path.
6. There are some other configurations in the source's head.

USAGE

ai64.php [options] original_dir destination_dir

    -s path/name    Skip to this file before staring processing
                    (Use this to continue after something went wrong)
    -x ,            Use ',' as file extension separator (default is '.')
    -v              Verbose, list succesfully processed files
    -w              Force windows compatible file naming

1. Collect the c64 stuff what you want to convert into a directory structure. 
2. Create an emtpy directory for the destination. 
3. Run "ai64.php sourcedirname destinationdirname".

If ".php" is not registered to your PHP interpreter, you may need to type 
"php ai64.php" instead, assuming php.exe is on your PATH.

The conversion process of one CD (600 MB) takes a lot of time.

There is no warranty of any kind. So I advice again, to create a temporary
user which cannot write your home directory, and run the whole conversion by
that user!

TIPS

1. Using Ramdisk or TmpFS

Since the program creates and deletes a LOT OF TEMPORARY FILES while 
uncomressing the archives, it is a good idea to choose a temporary dir 
(configred in the script) in RAM. 

Using tmpfs (recommended)

mount -t tmpfs none /mnt/rd
chown lion2:lion2 /mnt/rd

Using ramdisk (more complicated)

Usually Linux distros have ramdisk by default, to find it see:

ls /dev/ram*
dmesg | grep -i ramdisk

The second one also displays the size, what you can configure with the 
"ramdisk_size=64000" (64MB) kernel parameter at boot time (bootloader config).
I use the following small shell script as root, before converting:

mke2fs -m 0 /dev/ram0
mount /dev/ram0 /mnt/rd
chown lion2:lion2 /mnt/rd

2. Logging the errors

There is no option in the program to log the error messages, but it is very simple
using standard Unix tool, "tee" (-a is for append, if needed).

ai64.php orig_dir dest_dir 2>&1 | tee -a errorlog.txt

CONTACT

Feedback and patches are welcomed on lion@xaraya.hu. For updates look at my
homepage http://lion.xaraya.hu.

