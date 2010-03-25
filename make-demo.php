#!/usr/bin/php
<?php
/* 
This script makes the example output that is
lined from the ai64 site.
*/

if($argc != 2 || !is_dir($argv[1]))
{
	echo "Usage: make-demo.php dirname >demo.html";
	exit(1);
}

show_header();
list_dir($argv[1]);

function list_dir($dir)
{
	$dh = opendir($dir);
	if ($dh === false)
	{
		echo("Cannot open dir for processing: $dir\n");
		exit(1);
	}
	unset($dirs_here);
	unset($files_here);
	
	// Collect files and dirs into two arrays

	while (($file = readdir($dh)) !== false) 
	{
		if ($file != '.' && $file != '..')
		{
			$fullname = "$dir/$file";

			if (is_dir($fullname))
			{
				$dirs_here[] = $file;
			}
			elseif (is_file($fullname))
			{
				$files_here[] = $file;
			}
		}
	}
	closedir($dh);

	// Process all subdirs here
	if (!empty($dirs_here) && count($dirs_here) > 0)
	{
		sort($dirs_here);
		foreach ($dirs_here as $newdir) 
		{
			echo("<fieldset>\n");
			echo("<legend>$dir/$newdir</legend>\n");
			list_dir("$dir/$newdir");
			echo("</fieldset>\n");
		}
	}

	// Process all files here
	if (!empty($files_here) && count($files_here) > 0) 
	{
		sort($files_here);
		foreach ($files_here as $file) 
		{
			echo("<div>$file</div>\n");
		}
	}

}
show_footer();

function show_header()
{
?>
<html>
<head>
<title>ai64 demo result</title>
<style type="text/css">
body 
{
	font-family: sans-serif;
	background: black;
	color: white;
}
a
{
	color: yellow;
}
fieldset div
{
	float: left;
}
legend
{
	border: solid 1px yellow;
	padding: 5px;
	margin: 0px;
}
fieldset
{
	margin: 5px;
	padding: 10px;
}
</style>
<link rel="stylesheet" type="text/css" href="col4.css" title="Four columns" media="screen" />
<link rel="alternate stylesheet" type="text/css" href="col2.css" title="Two columns" media="screen" />
<link rel="alternate stylesheet" type="text/css" href="col3.css" title="Three columns" media="screen" />
<link rel="alternate stylesheet" type="text/css" href="col5.css" title="Five columns" media="screen" />

</head>
<body>
<h1>ai64 demo</h1>
<p>
<a href="http://lion.xaraya.hu/news/70">ai64 batch extractor</a> creates this output from
<a href="ftp://c64.rulez.org/pub/c64/Scene/Old">games on c64.rulez.org FTP</a>.
</p>

<p>You can download archives like this from the 
<a href="http://c64.rulez.org/xlfiles">C64 XLFiles</a> website.</p>
<p>Primary aim is to use with IDE64 or other hard disk solutions or with C64 emulators. 
Most of the files in this collection are converted directly to PRG or D64, regardless
of the original format. Some files have been broken by the conversion, minor issue. 
To convert other archive, get the program from the link above.</p>
<p>Customize 2-3-4-5 columns view to your monitor: 
use your browser's "View-&gt;Stylesheet" menu.</p>
<p>&copy; Lion/Kempelen 2005-2010</p>
<?php
}

function show_footer()
{
?>
</body>
</html>
<?php
}
?>
