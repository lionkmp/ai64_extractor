ai64_extractor - C64 file extractor

Copyright (c) 2004-2021 Ferenc 'Lion/Kempelen' Veres
lion@c64.rulez.org https://lion.xaraya.hu

WHAT IS THIS?

ai64 allows you to convert complete directory structures containing
c64 programs into IDE64 compatible copy of the whole structure. e.g.
try running it on a complete copy of Digital Dungeon FTP Archive, before
burning a CD for IDE64 usage. Most of the archive will be converted to 
.prg and .d64 files, which are very easy to use on C64!

SYSTEM REQUIREMENTS

PHP-CLI
    Which makes it possible to run PHP programs from the command line.
Linux/Unix
    Tested on Linux only. Windows compatible filename generation is now 
    supported, Windows platform support is planned (never tested).
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
    https://c64.rulez.org/pub/c64/other-OS/Unix
cbmconvert
    Marko Makela's converter masterpiece to handle all the c64 file formats
    (tested with v2.1.2).
    ftp://ftp.zimmers.net/pub/cbm/crossplatform/converters/unix
c1541
    Command line utility, part of Vice emulator (tested with v2.2).
    https://vice-emu.sourceforge.io/
d64list [Not needed if you use Vice c1541]
    This is a little C program I made years ago, included in ai64 download.
    https://c64.rulez.org/pub/c64/other-OS/Unix/

WHAT DOES IT DO?

The program will process a complete directory of C64 files
convert all files to c64 usable format and copy them into a destination
directory structure. Thus from all your ZIP, TGZ, etc files, you will
get a big directory directly usable on your IDE64 or via PCLink cable,
SD card readers, pendrive compatible cards like 1541 Ultimate, or on
Ultimate 64 or a C64 Emulator.

Currently the following formats are handled (anything can be nested, 
e.g. a single prg in a d64 in a zip):

C64 files:
D64: extracted if 1 file only and BAM is valid. Kept as D64 otherwise.
     Hi-score files (if identified) are ignored if there is only 1 more file.
     Some BAM validation messages in names/header are taken into consideration.
T64: extracted to files (if there is a single file named "FILE" is inside,
     the name of the original t64 is used when saving it to prg.)
P00: extracted to normal files
PRG: copied as is

Compressed files:
ZIP: extracted and re-processed
TAR: extracted and re-processed
GZ : extracted and re-processed
TGZ: extracted and re-processed
RAR: extracted and re-processed
LNX: converted to d64 and re-processed
ZIPCODE: (1!,2!..) converted to d64 and re-processed

Not copied (not usable on C64):
TXT (also extensionless README, 00INDEX)
DIZ, ME, NFO
COM, EXE
AVI, MPG, MPEG
PDF, DOC, DJVU
PNG, JPG, JPEG, GIF
DB
INI
C and any one letter extension (causes error with FuseCFS)
DEL (D64 extraction dirt)
DIR (IDE64 dir type, cannot be copied)
LNK (cannot be copied to IDE64)
REL (cannot be copied to IDE64)

Files starting with dot are not copied (Unix hidden files, FTP site messages).

Not listed files: copied as is.

Support is missing for: BZIP2, LHA (lzh).

All the stored files are saved with characters which are readable on C64.
Filenames are converted to 16 + extension, IDE64 can handle this length 
(requires 0.9x or higher IDEDOS). Already existing files are not 
overwritten, the new file will get "-1", "-2".. index, thus running the
program twice will create all destination files twice.

When the whole directory structure conversion is finished, ai64 will make
another process. It will rearrange all the directories which contain 
more than 300 files, to make sure there are no more than 300 files in a
directory (easier to handle on c64, MAN, etc). The created subdirs will
be called "ai100-X", "ai200-X" and so on, where "X" means the first word
of the first program in that directory. E.g. "ai300-blackjack".

INSTALLATION

Edit ai64 executable file if necessary: 
1. Customize the location of your php interpreter in the first line. 
2. Set the location of your d64list program or set it to empty string to 
   bypass analyzing d64 contents.
3. Make sure the -t tmp_dir is far from any important locations, because the
   program will run thousands of "rm -rf" commands inside it (honestly, I 
   create a "lion2" user just to run the program, you never know... 
   especially while developing it.) See ramdisk tip below.
4. Customize the location of "cbmconvert" executable as well.
5. Make sure the other decompress executables are on your path.
6. There are some other configurations in the source's head.

USAGE

1. Collect the c64 stuff what you want to convert into a directory structure
2. Run "ai64.php sourcedirname destinationdirname"

ai64.php [options] original_dir destination_dir

    -s path/name    Skip to this source file before staring processing
                    (Use this to continue after something went wrong)
    -x ,            Use ',' as file extension separator (default '.')
    -n 100          Number of maximum files per folder (default 300)
    -v              Verbose, list successfully processed files
    -V              Super-verbose, also list archives while processing them
    -w              Force windows compatible file naming (remove more chars)
    -l 16           Limit non-standard files' name length (default 16, w/o ext)
    -L 16           Limit standard files' name length (default 16) (PRG, SEQ..)
    -u              Enable unicode chars like ↑ | (remove otherwise)
    -t path         Temp dir for extractions (default '/tmp/[USER].ai64/')
    -e err_handling Error handling, either 'ignore' (default), 'ask' or 'halt'.

If ".php" is not registered to your PHP interpreter, you may need to type 
"php ai64.php" instead, assuming php.exe is on your PATH.

There is no warranty of any kind. So I advice again, to create a temporary
user which cannot write your home directory, and run the whole conversion by
that user!

Examples

ai64 -V -x , -u -t /mnt/rd Downloads/Games games-fuse 2>&1 | tee games.txt

    Converts for copying to HDD with FuseCFS (https://singularcrew.hu/idedos/)
    Uses comma as extension separator, unicode for PETSCII file names,
    tmpfs ramdisk and keeps full log. Creates Linux friendly file names.

ai64 -v -w Downloads/Games games-emu

    Converts for using with Emulators, Windows friendly file names.
    Also usable on ISO9660 CD/DVD disks for use with IDE64.

ai64 -v -w -l 12 -n 100 Downloads/Games games-sd2iec

    Converts for using with SD2IEC. Requires XE+ mode, which allows 16 chars
    for standard file types. Use -L 12 to further limit standard names too
    when using the default XE- mode. Puts 100 files in dirs instead of 300
    for faster operation. Creates Windows friendly names for FAT SD cards.

TIPS

1. Using Ramdisk or TmpFS

Since the program creates and deletes a LOT OF TEMPORARY FILES while 
uncomressing the archives, it is a good idea to choose a temporary dir 
(configred in the script) in RAM. 

Using tmpfs (recommended)

sudo mount -t tmpfs none /mnt/rd
sudo chown lion2:lion2 /mnt/rd

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

There is no option in the program to log the error messages, but it is very
simple using standard Unix tool, "tee" (-a is for append, if needed). This
will also help to find where some buggy files originated from and fix
errors in your original archive dirs.

ai64.php orig_dir dest_dir 2>&1 | tee -a errorlog.txt

LICENSE

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.

CONTACT

Feedback and patches are welcome on lion@c64.rulez.org.
Source code on GitHub: https://github.com/lionkmp/ai64_extractor/
Find my converted c64 downloads on my blog: https://lion.xaraya.hu/

