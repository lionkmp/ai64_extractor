#!/usr/bin/php
<?php
$version = "1.3";

/*
    ai64 - C64 archive files batch extractor
    Copyright (C) 2004-2010 Ferenc Veres (Lion/Kempelen) (lion@netngine.hu)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

$helptext="  ai64 V".$version." - C64 archive files batch extractor
  (c) 2004-2010 Ferenc Veres (Lion/Kempelen) (lion@netngine.hu) 

  ai64 allows you to convert complete directory structures containing
  c64 wares into IDE64 compatible copy of the whole strucure, before 
  burning a CD for C64/IDE64 usage. Read the README file for more info.
 
  Usage: ai64.php [options] original_dir destination_dir
  
    -s path/name    Skip to this file before staring processing
                    (Use this to continue after something went wrong)
    -x ,            Use ',' as file extension separator (default is '.')
    -v              Verbose, list succesfully processed files
    -w              Force windows compatible file naming

  Current configuration (hardcoded in this script):
    D64LIST={d64list}
    CBMCONVERT={cbmconvert}
    ARRPREFIX={arrprefix}
    ERRORHALT={errorhalt}
    tmp_dir={tmpdir}
";

// Set this to empty string if you don't have d64list,c1541 or compatible
//$D64LIST = "d64list {0}";
$D64LIST = "c1541 {0} -list";

// Cbmconvert's location
$CBMCONVERT="cbmconvert";

// Direcory prefix for ai100,ai200,ai300 subdirs when arranging to subfolders
$ARRPREFIX="ai";

// Stop on archiver errors? Values: "halt", "ask" or "ignore".
$ERRORHALT="ignore";

// Creating extraction dirs below this one (see ramdisk hint in the readme)
//$tmp_dir = '/tmp/'.getenv('USER').".ai64";
$tmp_dir = '/mnt/rd/'.getenv('USER').".ai64";

// ------- end of config ------

// Windows device names for file name validity check
$windevices = "(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9]|CLOCK\\$)";
$is_windows = (strpos(strtolower(php_uname('s')), "windows") === 0);

// Check argument list
$arm_file = 0;
$armed = 1;
$args = 0;
$verbose = false;
$extsep = ".";

$opts = getopt("vhs:x:w");

// Help?
if(isset($opts['h'])) {
	$search = array("{d64list}", "{cbmconvert}", "{arrprefix}", "{errorhalt}", "{tmpdir}");
	$replace = array($D64LIST, $CBMCONVERT, $ARRPREFIX, $ERRORHALT, $tmp_dir);
	echo str_replace($search, $replace, $helptext);
	exit(0);
}

// Skip to file? (incl. path)
if(isset($opts['s'])) {
	$arm_file = $opts['s'];
	$armed = 0;
	$args += 2; // Eat -s and value
}

// Extension separator?
if(isset($opts['x'])) {
	$extsep = $opts['x'];
	$args += 2; // Eat -x and value
}

// Verbose?
if(isset($opts['v'])) {
	$verbose = true;
	$args++; // Eat -v
}

// Force windows naming
if(isset($opts['w'])) {
	$is_windows = true;
	$args++; // Eat -w
}

// Last two args are source and destination 
if ($argc - $args != 3)
{
	echo "Usage: ai64.php [options] source_dir destination_dir (-h for help)\n\n";
	exit(1);
}
$source_dir = $argv[$argc-2];
$dest_dir = $argv[$argc-1];

// Check source dir
if (!is_dir($source_dir))
{
	echo "ai64: source directory does not exist: $source_dir.\n";
	exit(1);
}

// Check destiation dir and create if needed
if (!is_dir($dest_dir))
{
	echo "ai64: destination directory does not exist: $dest_dir.\n";
	$reply = ask_user("Create directory now? (y/n) ");
	if ($reply == "y")
	{
		if(!mkdir($dest_dir)) 
		{
			echo("ai64: Creating destination dir failed.\n");
			exit(1);
		}
	}
	else
	{
		echo("Exiting...\n");
		exit(1);
	}
} 

// Check skip file
if($armed != 1)
{
	if(!is_file($arm_file)) 
	{
		echo("ai64: Skip file is not found: $arm_file.\n");
		exit(1);
	}
}

// Stores all zipcode names to avoid processing them 4 times (1!x 2!x etc)
unset($processed_zipcodes);

/*** check external tools availabilty ***/

$tmpdir_count = 0;

// Check if all external tools are available

ob_start();
	system("unrar 2>/dev/null",$have_unrar);
	system("unzip 2>/dev/null",$have_unzip);
	system("gzip --version 2>/dev/null",$have_gzip);
	system("tar --version 2>/dev/null",$have_tar);
	system("zip2disk 2>/dev/null",$have_zipcode);
	system("$CBMCONVERT 2>/dev/null",$have_cbmconvert);
	if ($D64LIST != "")
	{
		system("$D64LIST -h 2>/dev/null",$have_d64list);
	}
	else
	{
		// d64list is optional
		$have_d64list = 0;
	}
ob_end_clean();

$missing_tools = "";
if ($have_unrar != 0 && $have_unrar != 7) { $missing_tools .= "unrar, "; }
if ($have_unzip != 0) { $missing_tools .= "unzip, "; }
if ($have_gzip != 0) { $missing_tools .= "gzip, "; }
if ($have_tar != 0) { $missing_tools .= "tar, "; }
if ($have_zipcode != 0 && $have_zipcode != 1) { $missing_tools .= "zip2disk, "; }
if ($have_cbmconvert != 0 && $have_cbmconvert != 1 ) { $missing_tools .= "cbmconvert, "; }
if ($have_d64list != 0 && $have_d64list != 1 ) { $missing_tools .= "d64list ($D64LIST), "; }

if ($missing_tools != "")
{
	echo "ai64: Required tool(s) missing: ".substr($missing_tools,0,strlen($missing_tools)-2)."\n";
	exit(1);
}

/** Temp dir check ***/

// Check if tmp_dir exists and try to make it
if (!is_dir($tmp_dir))
{
	if (!mkdir($tmp_dir))
	{
		echo "ai64: Cannot create temporary dir: $tmp_dir\n";
		exit(1);
	}
}
if (($dh = opendir($tmp_dir)) === false)
{
	echo "ai64: Cannot open temporary dir: $tmp_dir\n";
	exit(1);
}

// Temp dir must be empty or clean up now
$found_files="";
while ($file = readdir($dh))
{
	if ($file <> "." && $file <> "..")
	{
		$found_files .= $file.", ";
	}
}
closedir($dh);
if ($found_files != "")
{
	echo("ai64: Temporary directory ($tmp_dir) is not empty.\n");
	echo("Contents: ".substr($found_files,0,strlen($found_files)-2)."\n\n");
	$reply = ask_user("Delete complete contents now? (y/n) ");
	if ($reply == "y")
	{
		system("rm -rf ".escape_filename($tmp_dir)."/*");
		echo("Cleaned up.\n");
	}
	else
	{
		echo("Exiting...\n");
		exit(1);
	}
}


/*** Start processing ***/

echo("\nPass 1: Converting files.\nIf you see error messages from the external tools, you may want to examine those files.\n");
process_dir($source_dir, $dest_dir);

echo("Pass 2: Fixing directory names.\n");
rename_dirs_recursive($dest_dir, true);

echo("Pass 3: Rearranging for max 100 files per dir.\n");
arrange_files($dest_dir);

exit(0);


/*** PROCESS ONE DIR ***/

// Loop on the dir, process files and call recursive for subdirs
function process_dir($dir,$dest_dir,$count_only = 0) {

	global $armed,$arm_file;

	$dh = opendir($dir);
	if ($dh === false)
	{
		echo("ai64: Cannot open dir for processing: $dir\n");
		exit(1);
	}
	unset($dirs_here);
	unset($files_here);

	// Loop on the current dir

	// Collect files and dirs into two arrays

	while (($file = readdir($dh)) !== false) 
	{
		if ($file != '.' && $file != '..')
		{
			$fullname = "$dir/$file";

			if (is_dir($fullname))
			{
				// Subdirectory, store for later processing
				$dirs_here[] = $file;
			}
			elseif (is_file($fullname))
			{
				$files_here[] = $file;
			}
			else
			{
				echo "ai64: No file, no dir? $fullname\n";
			}
		}
	}
	closedir($dh);

	// If counting only, return the current level
	if ($count_only == 1) 
	{
		if(!empty($files_here))
		{
			return count($files_here);
		}
		else
		{
			return 0;
		}
	}

	// Process all files here
	if (!empty($files_here) && count($files_here) > 0) 
	{
		foreach ($files_here as $file) 
		{
			if ($armed == 1) 
			{
				process_file($dir,$file,$dest_dir);
			}
			elseif ("$dir/$file" == $arm_file) 
			{
				$armed = 1;
				process_file($dir,$file,$dest_dir);
			}
		}
	}

	// Process all subdirs here
	if (!empty($dirs_here) && count($dirs_here) > 0)
	{
		foreach ($dirs_here as $newdir) 
		{
			if (!is_dir("$dest_dir/$newdir")) 
			{
				if (!mkdir("$dest_dir/$newdir")) 
				{
					echo "ai64: Cannot create dir: $dest_dir/$newdir\n";
					exit(1);
				}
			}
			process_dir("$dir/$newdir","$dest_dir/$newdir");
		}
	}
}

/*** PROCESS ONE FILE ***/

// Detect type and call related function
function process_file($dir,$file,$dest_dir)
{
	global $verbose;

	if($verbose)
	{
		echo "ai64: Processing ".$dir."/".$file."\n";
	}	

	// Split at dot for extensions
	$nameparts = split('\.',$file);

	// Zipcode (requires special handling)
	if (preg_match('/^[1234]!(.*)$/',$file,$matches)) 
	{
		// Seems to be a zipcode
		$zipcodename = $matches[1];
	}
	else
	{
		// Not zipcode (no 1! 2! 3! or 4! at filename start)
		$zipcodename = "";
	}
		
	// Process files (first special ones, then swicth-case by extension)
	if ($zipcodename != "" &&
		// Do we have all 4 zipcode parts?
		is_file("$dir/1!$zipcodename") &&
		is_file("$dir/2!$zipcodename") &&
		is_file("$dir/3!$zipcodename") &&
		is_file("$dir/4!$zipcodename"))
	{
		// Yes
		process_file_zipcode($dir,$file,$dest_dir);

	}
	elseif (substr($file,0,1) == ".")
	{
		// Dotfile, ignore dotfiles (boring message files of FTP sites)
	}
	elseif (count($nameparts) == 1)
	{
		// Filename cosinsts 1 part, just save it (cannot detect type)
		save_file($dir,$file,$dest_dir);
	}
	else
	{
		// File consist of some dot separated parts, take the last as extension
		$lext = strtolower($nameparts[count($nameparts)-1]);

		switch ($lext)
		{

		// IGNORE THESE FILES

		case 'txt':
			// Text file
			//save_file($dir,$file,$dest_dir);
			break;

		case 'diz':
			// Text file
			//save_file($dir,$file,$dest_dir);
			break;

		case 'me':
			// Text file
			//save_file($dir,$file,$dest_dir);
			break;

		case 'nfo':
			// Text file
			//save_file($dir,$file,$dest_dir);
			break;

		case 'com':
			// Windows program file
			//save_file($dir,$file,$dest_dir);
			break;

		case 'exe':
			// Windows program file
			//save_file($dir,$file,$dest_dir);
			break;

		case 'del':
			// DEL separator extraced from D64 files
			//save_file($dir,$file,$dest_dir);
			break;

		// PROCESS THESE FILES

		case 'zip':
			// Zip compressed
			process_file_zip($dir,$file,$dest_dir);
			break;

		case 'rar':
			// Rar compressed
			process_file_rar($dir,$file,$dest_dir);
			break;

		case 'gz':
			// Gzip compressed
			process_file_gz($dir,$file,$dest_dir);
			break;

		case 'tar':
			// Tar archive
			process_file_tar($dir,$file,$dest_dir);
			break;

		case 'tgz':
			// Gzip compressed Tar archive
			process_file_tar($dir,$file,$dest_dir,"gzip");
			break;

		case 'd64':
			// Disk image
			process_file_d64($dir,$file,$dest_dir);
			break;

		case 't64':
			// Tape image
			process_file_t64($dir,$file,$dest_dir);
			break;

		case 'p00':
			// PC64 emu file image
			process_file_p00($dir,$file,$dest_dir);
			break;

		case 'lnx':
			// Lynx file
			process_file_lnx($dir,$file,$dest_dir);
			break;

		case 'prg':
			// C64 program
			save_file($dir,$file,$dest_dir);
			break;


		// KEEP UNKNOWN FILES

		default:
			// Unknown file type
			save_file($dir,$file,$dest_dir);
			break;
		}
	}

}

/*** FILE FORMATS ***/

/*
	All these functions process one single file format.
	Most of them creates a temporary dir, extracts things to
	that directory and when finished processing, removes the dir.
	In many cases, after extracting to the temporary dir, the
	"process_dir()" is called again, if the resulting file format
	was not a c64 native file and/or requires checking recursively.

	Most of these functions do very similar things, but to keep them
	able to handle special cases, currently I keep the one function
	per file format structure (only tar and tgz has the same).

	The functions must return with the same CWD as they were invoked.
	They must not change the source file specified in $dir/$file, they
	must not try to create temporary files in the source location.
	They must use make_workdir() to receive a temporary dir and must
	clear that before returning.
*/

// ZIP

function process_file_zip($dir,$file,$dest_dir)
{
	global $CBMCONVERT, $D64LIST;

	$workdir = make_workdir("ai64zip");

	// Extract the files
	$shellfile = escape_filename("$dir/$file");

	system("unzip -qq -o -L -d $workdir $shellfile",$ret);

	if ($ret != 0)
	{
		echo "ai64: EXTERNAL ERROR: unzipping $shellfile to $workdir failed\n";
		handle_error();
	}

	if ($ret == 0)
	{
		// Recall processing them 
		process_dir($workdir,$dest_dir);
	}

	system("rm -rf $workdir");
}

// RAR

function process_file_rar($dir,$file,$dest_dir)
{
	global $CBMCONVERT, $D64LIST;

	$workdir = make_workdir("ai64rar");

	// Extract the files
	$shellfile = escape_filename("$dir/$file");

	system("unrar x -inul $shellfile $workdir/",$ret);

	if ($ret != 0)
	{
		echo "ia64: EXTERNAL ERROR: unrar $shellfile to $workdir failed\n";
		handle_error();
	}

	if ($ret == 0)
	{
		// Recall processing them 
		process_dir($workdir,$dest_dir);
	}

	system("rm -rf $workdir");
}

// GZ

function process_file_gz($dir,$file,$dest_dir)
{
	global $CBMCONVERT, $D64LIST;

	$workdir = make_workdir("ai64gz");

	// Copy the file to workdir
	$shellfile = escape_filename("$dir/$file");
	system("cp $shellfile $workdir", $ret);

	if ($ret != 0)
	{
		echo "ia64: EXTERNAL ERROR: Copying $shellfile to $workdir failed\n";
		handle_error();
	}
	else
	{
		// Extract the file (if copy was ok)
		$shellfile = escape_filename("$workdir/$file");
		system("gzip -d $shellfile",$ret);

		if ($ret != 0)
		{
			echo "ia64: EXTERNAL ERROR: gzip -d $shellfile failed\n";
			handle_error();
		}
	}

	if ($ret == 0)
	{
		// Recall processing them 
		process_dir($workdir,$dest_dir);
	}

	system("rm -rf $workdir");
}

// TAR

function process_file_tar($dir,$file,$dest_dir,$compress="")
{
	global $CBMCONVERT, $D64LIST;

	$workdir = make_workdir("ai64tar");

	// Extract the files
	$shellfile = escape_filename("$dir/$file");

	if ($compress == "gzip")
	{
		$command = "tar -xz -C $workdir -f $shellfile";
	}
	else
	{
		$command = "tar -x -C $workdir -f $shellfile";
	}
	system($command,$ret);

	if ($ret != 0)
	{
		echo "ia64: EXTERNAL ERROR tar extracting $shellfile to $workdir failed\n";
		handle_error();
	}

	if ($ret == 0)
	{
		// Recall processing them 
		process_dir($workdir,$dest_dir);
	}

	system("rm -rf $workdir");
}

// D64

function process_file_d64($dir,$file,$dest_dir)
{
	global $CBMCONVERT, $D64LIST;

	$workdir = make_workdir("ai64d64");

	// Variables for analizing D64 contents
	$dont_validate = 0;
	$totalblocks = 0;
	$freeblocks = -1;
	$lastignored = 0;
	$numitems = 0;
	$firstline = 1;
	$hiscores = 0;

	// Extract the files
	$old_cwd = getcwd();

	if (substr($dir,0,1) != "/")
	{ 
		// Relative path, add old cwd
		$shellfile = escape_filename("$old_cwd/$dir/$file");
	}
	else
	{
		// Absolute path in $dir, keep it
		$shellfile = escape_filename("$dir/$file");
	}

	// If d64list is available, use the "nice way": count free and used blocks and compare
	if ($D64LIST != "")
	{
		// List d64, split block numbers, buffer output into a variable
		ob_start();
		system(str_replace('{0}', $shellfile, $D64LIST), $ret);
		$contents = ob_get_contents();
		ob_end_clean();

		if($ret != 0)
		{
			echo "ia64: EXTERNAL ERROR: listing $shellfile returned with error\n";
			handle_error();
		}

		// Analize output, calculate entries, total blocks, free blocks
		foreach (split("\n",$contents) as $line)
		{
			// Get the number from the line
			if (preg_match('/^([0-9]+)/', $line, $matches))
			{
				$totalblocks += $matches[1];
				$freeblocks = $matches[1]; // keep the last one

				// Count lines, ignore separators 
				if (!preg_match('/^0 /',$line) &&		// Zero bytes file
					!preg_match('/del<*$/i',$line) &&	// Del file
					$firstline != 1)			// Disk header
				{					
					$numitems++;
					$lastignored=0;
				}
				else
				{
					$lastignored=1;
				}
			}

			// Any "Don't validate" warnings? Oh, those unwritten laws :-) 
			if (preg_match('/validate/i',$line) ||
				preg_match('/change dir/i',$line) ||
				preg_match('/change bam/i',$line)) 
			{
				$dont_validate = 1;
			}

			// Any Hi-Score file on it?
			if (!empty($matches[1]) && $matches[1] > 0 && $matches[1] < 5 && hiscore_name($line)) 
			{
				$hiscores = 1;
			}
			$firstline = 0; // No more disk headers
		}
		$numitems -= 1-$lastignored; // ignore header & freeblocks line
		if ($hiscores == 1 && $numitems == 2) 
		{ 
			// Ignore hiscores file
			$numitems--;
		}
		$totalblocks -= $freeblocks; // substract last addition

		// If don't validate notes found, ignore the analizis result
		if ($dont_validate == 1)
		{
			$numitems = 0;
		}

		//echo "$file: Total: $totalblocks Free: $freeblocks Entries: $numitems\n";
	}

	// if d64list not available, or the d64 was nice extractable, extract it
	if ($D64LIST == "" || ($D64LIST != "" && $numitems == 1 && $totalblocks+$freeblocks == 664))
	{
		// Extract the d64
		chdir($workdir);
		system($CBMCONVERT." -v0 -N -d $shellfile",$ret);
		chdir($old_cwd);

		if ($ret != 0)
		{
			echo "ia64: EXTERNAL ERROR extracting $shellfile to $workdir failed\n";
			handle_error();
		}

		// COUNT(!!!) the created files/dirs
		$filenum = process_dir($workdir,$dest_dir,1);
	}

	// Amount of extracted files?
	// First is the nice way: d64list available & there was 1 file on & the BAM was ok.
	// Second is the ugly way depending on the extracted files only.
	if (($D64LIST != "" && ($numitems != 1 || $totalblocks+$freeblocks != 664)) || 
		($D64LIST == "" && ($filenum > 1 || $ret != 0)))
	{
		// If more than 1 file, keep the d64 file
		save_file($dir,$file,$dest_dir);
	}
	else
	{
		// Process the extracted files

		// Last hack: if found a hiscores file, try to delete it
		if ($hiscores == 1)
		{
			$score_dh = opendir($workdir);
			if ($score_dh === false)
			{
				echo("ai64: Cannot open dir for deleting high-scores file: $workdir\n");
				exit(1);
			}
			
			// Loop on all files in this dir
			while(($fname = readdir($score_dh)) !== false)
			{
				// If matching hiscore filename patterns
				if (hiscore_name($fname)) 
				{
					// Get filesize
					$filedata = stat("$workdir/$fname");
					if ($filedata === false)
					{
						echo("ai64: Cannot stat high-scores file: $workdir/$fname\n");
						exit(1);
					}
					
					// If smaller than 5 blocks, delete it
					if ($filedata[7] < 1024)
					{
						if (!unlink("$workdir/$fname"))
						{
							echo("ai64: Cannot unlink high-scores file: $workdir/$fname\n");
							exit(1);
						}
					}
				}
			}
			closedir($score_dh);
		}

		// Process the remaining file(s)
		process_dir($workdir,$dest_dir);
	}

	system("rm -rf $workdir");
}

// T64

function process_file_t64($dir,$file,$dest_dir)
{
	global $CBMCONVERT, $D64LIST;

	$workdir = make_workdir("ai64t64");

	// Extract the files
	$old_cwd = getcwd();

	if (substr($dir,0,1) != "/")
	{
		// Relative path, add old cwd
		$shellfile = escape_filename("$old_cwd/$dir/$file");
	}
	else
	{
		// Absolute path in $dir, keep it
		$shellfile = escape_filename("$dir/$file");
	}

	chdir($workdir);
	system($CBMCONVERT." -v0 -N -t $shellfile",$ret);
	chdir($old_cwd);

	if ($ret != 0)
	{
		echo "ai64: EXTERNAL ERROR: extracting $shellfile to $workdir failed\n";
		handle_error();
	}

	// Count the created files/dirs
	$filenum = process_dir($workdir,$dest_dir,1);

	// Amount of extracted files?
	if ($filenum > 2 || $ret != 0)
	{
		// If more than 2 files, keep is as archive
		save_file($dir,$file,$dest_dir);

	}
	elseif ($filenum == 1 && is_file("$workdir/file.prg"))
	{

		// One file, and that is the "unnamed" file.prg

		// Rename it to the t64's name plus .prg, and process the dir
		rename("$workdir/file.prg","$workdir/".str_replace('t64','prg',$file));
		process_dir($workdir,$dest_dir);

	}
	else
	{
		// Less or equal to 2, process them
		process_dir($workdir,$dest_dir);
	}

	system("rm -rf $workdir");
}

// P00

function process_file_p00($dir,$file,$dest_dir)
{
	global $CBMCONVERT, $D64LIST;

	$workdir = make_workdir("ai64p00");

	// Extract the files
	$old_cwd = getcwd();

	if (substr($dir,0,1) != "/")
	{
		// Relative path, add old cwd
		$shellfile = escape_filename("$old_cwd/$dir/$file");
	}
	else
	{
		// Absolute path in $dir, keep it
		$shellfile = escape_filename("$dir/$file");
	}

	chdir($workdir);
	system($CBMCONVERT." -v0 -N -p $shellfile",$ret);
	chdir($old_cwd);

	if ($ret != 0)
	{
		echo "ia64: EXTERNAL ERROR: extracting $shellfile to $workdir failed\n";
		handle_error();
	}

	// Count the created files/dirs
	$filenum = process_dir($workdir,$dest_dir,1);

	// Amount of extracted files?
	if ($filenum > 2 || $ret != 0)
	{
		// If more than 2 files, keep is as archive
		save_file($dir,$file,$dest_dir);
	}
	else
	{
		// Less or equal to 2, process them
		process_dir($workdir,$dest_dir);
	}

	system("rm -rf $workdir");
}

// LNX

function process_file_lnx($dir,$file,$dest_dir)
{
	global $CBMCONVERT, $D64LIST;

	$workdir = make_workdir("ai64lnx");

	// Convert lnx to d64
	$old_cwd = getcwd();

	if (substr($dir,0,1) != "/")
	{
		// Relative path, add old cwd
		$shellfile = escape_filename("$old_cwd/$dir/$file");
	}
	else
	{
		// Absolute path in $dir, keep it
		$shellfile = escape_filename("$dir/$file");
	}

	// Create a d64 with the same name like the lnx was
	$d64name = escape_filename(preg_replace('/lnx$/i','d64',$file));

	chdir($workdir);
	system($CBMCONVERT." -v0 -D4 $d64name -l $shellfile",$ret);
	chdir($old_cwd);

	if ($ret != 0)
	{
		echo "ia64: EXTERNAL ERROR: lnx to d64 conversion of $shellfile to $workdir failed\n";
		handle_error();
	}

	// Re-process from d64 now
	process_dir($workdir,$dest_dir);

	system("rm -rf $workdir");
}

// ZIPCODE

function process_file_zipcode($dir,$file,$dest_dir)
{
	global $CBMCONVERT, $D64LIST;
	global $processed_zipcodes; // Array for previous ones to avoid processing 4 times

	// Convert zipcode to d64
	$old_cwd = getcwd();

	// Cut the zipcode file's name without 1! 2!..
	$zipname = substr($file,2,strlen($file));
	
	// Check if already processed with another prefix	
	if (!empty($processed_zipcodes["$dir/$zipname"]) && $processed_zipcodes["$dir/$zipname"] == 1)
	{
		return;
	}
	
	// Store that this one was processed
	$processed_zipcodes["$dir/$zipname"] = 1;

	$workdir = make_workdir("ai64zipcode");

	if (substr($dir,0,1) != "/")
	{
		// Relative path, add old cwd
		$filesdir = "$old_cwd/$dir";
	}
	else
	{
		// Absolute path in $dir, keep it
		$filesdir = $dir;
	}

	// z64 is standard extension, remove it, other extensions are untouched
	if (preg_match('/z64$/i',$zipname))
	{
		$d64name = escape_filename(preg_replace('/z64$/','d64',"$workdir/$zipname"));
	}
	else
	{
		$d64name = escape_filename("$workdir/$zipname.d64");
	}
	$zipname = escape_filename($zipname);

	chdir($filesdir);
	system("zip2disk $zipname $d64name",$ret);
	chdir($old_cwd);

	if ($ret != 0)
	{
		echo "ia64: EXTERNAL ERROR: zipcode to d64 conversion of $old_cwd/$zipname to $workdir/$d64name failed.\n";
		handle_error();
	}

	// Re-process from d64 now
	process_dir($workdir,$dest_dir);

	system("rm -rf $workdir");
}

/*** HELPER functions for filetype handlers ***/

function escape_filename($name)
{
	return escapeshellarg($name);
}

// Create just another dir in the temporary dir for one single operation
function make_workdir($dirname)
{
	global $tmp_dir, $tmpdir_count;

	$workdir = "$tmp_dir/$dirname-$tmpdir_count";

	// This counter makes the tmp dir unique
	// thus same type of nested archives are handled ok
	$tmpdir_count++;

	// Create temporary dir
	if (!is_dir($workdir))
	{
		if (!mkdir($workdir,0755))
		{
			echo "ia64: temporary directory cannot be created: $workdir\n";
			exit(1);
		}
	}

	system("rm -rf $workdir/*");

	return($workdir);
}

// On fatal errors: quit, ask or continue
function handle_error()
{
	global $ERRORHALT;

	switch($ERRORHALT)
	{
		// Interactive mode?
		case "ask":
			$reply = ask_user("Continue? (y/n)");

			if ($reply == "n")
			{
				exit(1);
			}
			break;

		// Always halt
		case "halt":
			exit(1);
			break;
	}
	// Otherwise just continue
}

// hiscore_name: returns true if the filename looks like a hiscore file
function hiscore_name($name)
{
	return (preg_match('/hi/i',$name) ||
			preg_match('/best/i',$name) ||
			preg_match('/heros/i',$name) ||
			preg_match('/top10/i',$name) ||
			preg_match('/score/i',$name));
}

// Ask a question from the user and return y or n. 
function ask_user($question)
{
	do
	{
		echo($question);
		$reply = fgets(STDIN);
		$reply = strtolower(substr($reply,0,1));
	}
	while ($reply != "y" && $reply != "n");

	return $reply;
}

/*** SAVING DESTINATION FILES ***/

// SAVE FILE
function save_file($dir,$file,$dest_dir)
{
	global $verbose;

	$normalname = normalize_name($file);

	// If destination file already exists, make DOS ~1 indexing :-)
	for ($i = 1; is_file("$dest_dir/$normalname") || is_dir("$dest_dir/$normalname"); $i++)
	{
		$normalname = normalize_name($file,$i);
	}

	if($verbose)
	{
		echo "ai64: Saving: $dir/$file to $dest_dir/$normalname\n";
	}
	copy("$dir/$file", "$dest_dir/$normalname");
	

}

// CONVERT ONE FILENAME TO IDE64 COMPATIBLE
function normalize_name($file, $index = 0)
{
	global $extsep, $windevices, $is_windows;
	
	$file = normalize_fixchars($file);
	
	// Get last extension ("." and "," are both separators)
	$nameparts = split('[\.,]',$file);

	// If no extension, use .prg
	if (count($nameparts) == 1)
	{
		$lext = "prg";
		$nameonly = $file;
	}
	else
	{
		$lext = $nameparts[count($nameparts)-1];
		$nameonly = substr($file,0,strlen($file)-strlen($lext)-1);
	}

	// If the extension is invalid (according to Soci) add .prg!
	if ($lext == "s" || $lext == "p" || $lext == "d" || $lext == "u" ||
		$lext == "l" || $lext == "b" || $lext == "j" || $lext == "a" ||
		$lext == "dir" || $lext == "lnk" || $lext == "rel" || $lext == "del" || $lext == "")
	{
		$nameonly .= ".$lext"; // Add back to filename, may be useful
		$lext = "prg";
	}

	// Truncate long extension
	$lext = substr($lext,0,3);

	if($is_windows)
	{
		// Match device name ("PRN", "PRN.txt").
		if(preg_match("/^".$windevices."$/i", $nameonly))
		{
			$nameonly .= "win"; // Devices are short, so this will fit in 16
		}
		
		// Disallow filename ending with "."
		$nameonly = preg_replace('/\.+$/', '', $nameonly);
		$lext = preg_replace('/\.+$/', '', $lext);
	}
	
	$nameonly = normalize_spacing($nameonly);
	
	// Nothing left after filters?
	if($nameonly == '')
	{
		$nameonly = 'noname';
	}
	if($lext == '')
	{
		$lext == 'prg';
	}

	// No indexing requested, just cut the name (make place for extenstion)
	if ($index == 0)
	{
		$file = substr($nameonly, 0, 16) . $extsep . $lext;
		return($file);
	}

	// Cut the name, make space for extension and index) (15 => place for "-")
	$file = substr($nameonly, 0, 15 - strlen($index)) . "-" . $index . $extsep . $lext;
	return($file);
}

// CONVERT ONE DIRNAME TO IDE64 COMPATIBLE
function normalize_dirname($dir, $index = 0)
{
	global $is_windows, $windevices;
	
	$dir = normalize_fixchars($dir);

	if($is_windows)
	{
		// Match device name ("PRN"..).
		if(preg_match("/^".$windevices."$/i", $dir))
		{
			$dir .= "win"; // Devices are short, so this will fit in 16
		}
		
		// Disallow filename ending with "."
		$dir = preg_replace('/\.+$/', '', $dir);
	}
	
	$dir = normalize_spacing($dir);
	
	// Nothing left after filters?
	if($dir == '')
	{
		$dir = 'noname';
	}

	// No indexing requested, just cut the name
	if ($index == 0)
	{
		return substr($dir, 0, 16);
	}

	// Cut the name, make space for index (15 => place for "-")
	return substr($dir, 0, 15 - strlen($index)) . "-" . $index;
}

// Replace or remove not allowed filename characters
function normalize_fixchars($file)
{
	global $is_windows;

	// Remove non-ascii chars
	$file = preg_replace('/[^\x20-\x7e]+/',' ',$file);

	// Remove invalid characters * : = / and ? (According to Soci.)
	$file = preg_replace('/[\*:=\?]/','.',$file);

	if($is_windows)
	{
		// More invalid characters on windows '<', '>', '\\', '/', ':', '"', '|', '?', '*'
		$file = preg_replace('/[<>\\:\/"|\?\*]+/', '.', $file);
	}
	
	// Lowercase, trim it
	return trim(strtolower($file));  // normalize_spacing() is also called later!
}

// Truncate leading and trailling dots and spaces
// Convert repeated dots or spaces to one 
function normalize_spacing($file)
{
	// Reduce multiple spaces and dots to a single
	$file = preg_replace('/  */', ' ', $file);
	$file = preg_replace('/\.\.*/', '.', $file);

	// Trim dot and space
	return trim($file, " .");
}

/*** RENAME DIRS TO FIT ON IDE64 ***/

function rename_dirs_recursive($dir, $topdir)
{
	global $verbose;
	
	$dh = opendir($dir);
	if ($dh === false)
	{
		echo("ai64: Cannot open dir: $dir\n");
		exit(1);
	}

	// Loop on the current dir
	while (($file = readdir($dh)) !== false) 
	{
		if ($file != '.' && $file != '..')
		{
			$fullname = "$dir/$file";

			if (is_dir($fullname))
			{
				// Process deepest dirs first
				rename_dirs_recursive($fullname, false);
			}
		}
	}
	closedir($dh);

	// Don't rename conversion destination	
	if(!$topdir)
	{
		// path[1] is parent + /, path[2] is the currnent dir name
		preg_match("/^(.*\/)(.*)$/", $dir, &$path);
		
		$normalname = normalize_dirname($path[2]);
		
		// Renaming needed?
		if($normalname != $path[2])
		{
			// If destination file already exists, make DOS ~1 indexing :-)
			for ($i = 1; is_file($path[1].$normalname) || is_dir($path[1].$normalname); $i++)
			{
				$normalname = normalize_dirname($path[2], $i);
			}
			
			if($verbose)
			{
				echo("ai64: Rename dir: $dir ---> ".$path[1].$normalname."\n");
			}
			
			rename($dir, $path[1].$normalname);
		}
	}
}

/*** ARRANGE FILES TO LIMIT FILES/DIR ***/

// Create subdirectories for the files if there are more than 100
// files in a dir (IDE64 MAN reads only 127!)
function arrange_files($dir)
{
	global $ARRPREFIX;

	$dh = opendir($dir);
	if ($dh === false)
	{
		echo("ai64: Cannot open dir for arranging files: $dir\n");
		exit(1);
	}
	unset($dirs_here);
	unset($files_here);

	// Loop on the current dir
	// Collect files and dirs into two arrays
	while (($file = readdir($dh)) !== false)
	{
		if ($file != '.' && $file != '..')
		{
			$fullname = "$dir/$file";

			if (is_dir($fullname))
			{
				// Subdirectory, store for later processing
				$dirs_here[] = $file;
			}
			elseif (is_file($fullname))
			{
				$files_here[] = $file;
			}
			else
			{
				echo "ai64: No file, no dir? $fullname\n";
			}
		}
	}
	closedir($dh);


	// How many files and dirs we have here?
	$filecount = ( empty($files_here) ? 0 : count($files_here) );
	$dircount = ( empty($dirs_here) ? 0 : count($dirs_here) );

	// If here are some files and the total of files+dirs > 100, do the rearrange
	if ($filecount > 0 && ($filecount + $dircount) > 100)
	{
		sort($files_here);

		$filesnum = count($files_here);
		$lastdir = -1;
		$dirpart = "";

		// Process all files here
		for ($i = 0; $i < $filesnum; $i++)
		{
			// What is the /100 part of this (to see if we must open new dir for this file)
			$currdir = (int) ($i/100) * 100 + 100;

			// Create the dir if not yet done
			if ($currdir != $lastdir)
			{
				// Take first part of the current file's name for new dir's name
				$dirpart = preg_replace('/^([a-z0-9]*).*$/i', '$1', $files_here[$i]);
				$dirpart = "-".substr($dirpart, 0, 16 - strlen($ARRPREFIX) - strlen($currdir));
				
				// Create new dir e.g. "ai500-bubble". 
				if (!mkdir("$dir/$ARRPREFIX$currdir$dirpart"))
				{
					echo "ai64: Cannot create final dir: $dir/$ARRPREFIX$currdir$dirpart\n";
					exit(1);
				}
				$lastdir = $currdir;
			}
			rename("$dir/$files_here[$i]","$dir/$ARRPREFIX$currdir$dirpart/$files_here[$i]");
		}
	}

	// Process all subdirs here
	if (!empty($dirs_here) && count($dirs_here) > 0)
	{
		foreach ($dirs_here as $newdir)
		{
			arrange_files("$dir/$newdir");
		}
	}
}

/* *********************
** ai64 KNOWLEGE BASE **
************************

About file extensions:
----------------------
>> Hi!
>>
>> Soci/Singular wrote:
>>
>
>>>> >>Yep, I should check the docs, but if I recall right, the new IDE64 DOS
>>>> >>handles 16+3 filenames on ISO fine, is that true? (Just to fix my batch
>>>> >>converter from 16 total (12+dot+ext). So will 16+dot+3 work fine? Any
>>>> >>custom extension? 3 chars of extension?)
>>
>>> >
>>> >
>>> > 16+dot+3 works fine. Do not use ,s ,p ,d ,u ,l ,b ,j ,a ,dir ,lnk ,rel
>>> > ,del as extensions. Also 20041222 still reserves ,c and ,cbm but that's
>>> > fixed in later versions.
>
>>
>> ","?? Comma? Where? Nowhere inside the filename? What if there is a
>> ",shit" in the filename? Is that problem when reading the ISO or Joliet?
>> What do you mean on all these commas spearated stuff? (I know, that OPEN
>> uses these, but do I have to avoid then on CD too? Then it would be
>> better to completely replace "," with ".".
>>
>> Or did you mean ".s" ".p" ".d", etc? Unless the original archive has
>> such files (unlikely), my program will not write out such files. I save
>> "unknown" files as-is!


"." and "," is equivalent for the CD filesystem when used as the delimiter
for the filetype. If you save a a.s.basketball.prg then it will be
"a.s.basketball" with type "prg". Like a,seq will be "a" with type "seq".
I forgot to say that do not use * : = / and ? in filenames. Yes of course
I meant "code.s" as an illegal name, but "code.s,seq", "code.s.seq",
"code.seq" or "code,seq" is correct
----------------------

*/
?>
