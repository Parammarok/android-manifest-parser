<?php
// plugin, sync windows files to linux server
//php "upload2server.php" %:p


define('HOST', '');
define('USER_NAME', '');
define('PASSWD', '');

define('PRJ_ROOT_DIR', 'open_api');
define('PREFIX', '/home/' . USER_NAME);

if(count($argv) == 2)
{
	$sFilePath = $argv[1];

	$sServerPath = sGetServerPath($sFilePath);
	$realPath = sprintf("%s@%s:/%s", USER_NAME, HOST, $sServerPath);

	try
	{
		$cmd = sprintf("pscp.exe -pw %s %s %s", PASSWD, $sFilePath, $realPath);
		
		$cmdinfo = sprintf("pscp.exe -pw ****** %s %s", $sFilePath, $realPath);
		echo $cmdinfo . "\n";
		
		system($cmd);
	}
	catch(Exception $e)
	{
		print_r($e);exit;
	}
}

function sGetServerPath($sWindowsPath)
{
	$ret = "";
	$paths = explode("\\", $sWindowsPath);
	if($startKey = array_search(PRJ_ROOT_DIR, $paths))
	{
		$ret = PREFIX . '/' . PRJ_ROOT_DIR . '/';
		for($i=$startKey+1;    $i<count($paths); $i++)
		{
			$ret .=    $paths[$i] . "/";
		}
		$ret = trim($ret, "/");
	}
	return $ret;
}
?>
