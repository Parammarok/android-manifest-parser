<?php
/**
 * apk包解析
 * apk package parsing
 */
class ApkParser
{
    // 解析需要的一些命令(需要安装java1.7，例如: yum install java-1.7.0-openjdk-devel)
    // We need some commands (require java1.7, for example CentOS : yum install java-1.7.0-openjdk-devel)
    private static $arrCmdMap = array(
        "app_parser.cmd.aapt" => "aapt d badging",
        "app_parser.cmd.verify" => "jarsigner -verify",
        "app_parser.cmd.rsa" => "keytool -printcert -jarfile",
    );

    /**
     * 解析包入口
     * Base function of parsing
     * @param string $apkPath apk path
     */
    public static function parse($apkPath)
    {
        if (!file_exists($apkPath)) {
            throw new Exception("apk($apkPath) not exists!");
        }

        $parseInfo = self::parseBase($apkPath);
        return array_merge($parseInfo, self::parseRsa($apkPath));
    }

    /**
     * 解析基本信息
     * Parsing the basic info
     */
    private static function parseBase($apkPath)
    {
        self::verifyApk($apkPath);
        clearstatcache();
        $parseInfo = self::parseAapt($apkPath);
        $parseInfo['md5'] = md5_file($apkPath);
        $parseInfo['size'] = filesize($apkPath);
        $parseInfo['language'] = ApkLanguage::getLanguage($parseInfo['apk_name']);
        return $parseInfo;
    }

    private static function verifyApk($apkPath)
    {
        $verifyCmd = self::$arrCmdMap["app_parser.cmd.verify"]." $apkPath";
        exec($verifyCmd, $out, $ret);
        if ($ret !== 0) {
            $msg = "Package verification failed ($verifyCmd: ".implode("\n", $out).")!";
            throw new Exception($msg);
        }
    }

    private static function getAaptOutput($apkPath)
    {
        $aaptCmd = self::$arrCmdMap["app_parser.cmd.aapt"]." $apkPath";
        exec($aaptCmd, $out, $ret);
        if ($ret !== 0) {
            $msg = "aapt error ($aaptCmd: ".implode("\n", $out).")!";
            throw new Exception($msg);
        }
        return $out;
    }

    /**
     * get aapt application name
     * name application-label-zh_CN application: label=
     * modify: read chinese name first, if not exists, read the default name
     */
    private static function getAaptApkName($outStr)
    {
        $pattern  = "/application-label-zh_CN:'(.*)'/iU";
        preg_match($pattern, $outStr, $m);
        if (!$m) {
            $pattern = "/application-label:'(.*)'/iU";
            preg_match($pattern, $outStr, $m);
            
        }
        $name = $m ? trim($m[1]) : "";
        return self::cleanName($name);
    }

    /**
     * Clear the special characters in application name
     */
    private static function cleanName($str)
    {
        if (!$str) {
            return "";
        }
        $regex = "/[\x{00a0}\x{fffe}\x{200b}]/u"; //Clear utf8 blank space and others
        $str = preg_replace($regex, "", $str);
        return trim($str); //header and footer spaces
    }

    /**
     * Get package name
     * @param type $outStr
     */
    private static function getAaptPname($outStr)
    {
        $pattern = "/package: name='(.*)'/iU";
        preg_match($pattern, $outStr, $m);
        if (!$m) {
            throw new Exception("Extract package name failed!");
        }
        return trim($m[1]); //Use the initial package name, keep the upper and lower case.
    }

    // Get package version string, version code
    private static function getAaptVerCode($outStr)
    {
        $pattern = "/versionCode='(.*)'/iU";
        preg_match($pattern, $outStr, $m);
        return $m ? (int)$m[1] : 0;
    }

    //Get os version, number
    private static function getAaptOsVerCode($outStr)
    {
        $pattern = "/sdkVersion:'(.*)'/iU";
        preg_match($pattern, $outStr, $m);
        return $m ? (int)$m[1] : 0;
    }

    //Get package version, string 
    private static function getAaptVerName($outStr)
    {
        $pattern = "/versionName='(.*)'/iU";
        preg_match($pattern, $outStr, $m);
        return $m ? self::getVer($m[1]) : "";
    }
    
    //Get version value, the rule is number mixed with dot. 
    private static function getVer($str)
    {
        $regex = "/([0-9\.]+)/";
        preg_match($regex, $str, $m);
        return $m ? trim($m[1], '.') : "";
    }

    //Get application permission
    private static function getAaptPermissions($outStr)
    {
        $pattern = "/uses-permission:'(.*)'/iU";
        preg_match_all($pattern, $outStr, $m);
        return $m ? array_unique($m[1]) : array();
    }

    //execute aapt, get the output info
    private static function parseAapt($apkPath)
    {
        $parseInfo = array();
        $outArr = self::getAaptOutput($apkPath);
        $outStr = implode("\n", $outArr);

        $parseInfo['apk_name'] = self::getAaptApkName($outStr);
        $parseInfo['pname'] = self::getAaptPname($outStr);
        $parseInfo['ver_code'] = self::getAaptVerCode($outStr);
        $parseInfo['ver_name'] = self::getAaptVerName($outStr);
        $parseInfo['os_ver_code'] = self::getAaptOsVerCode($outStr);
        $parseInfo['permissions'] = self::getAaptPermissions($outStr); //array
        // $parseInfo['aapt_out'] = $outStr;
        return $parseInfo;
    }

    private static function getRsaOutput($apkPath)
    {
        $rsaCmd = self::$arrCmdMap["app_parser.cmd.rsa"]." $apkPath";
        exec($rsaCmd, $out, $ret);
        if ($ret !== 0) {
            $msg = "Get Cert md5 failed ($rsaCmd: ".implode("\n", $out).")!";
            throw new Exception($msg);
        }
        return $out;
    }
    
    private function parseRsa($apkPath) {
        $parseInfo = array();
        $outArr = self::getRsaOutput($apkPath);
        var_dump($outArr);
        $content = implode("\n", $outArr);
        $regex = "/签名:.*?证书指纹:.*?MD5: ([0-9A-F:]+)/s"; // I have to remain this. It parses Chinese apk :(
        preg_match_all($regex, $content, $m, PREG_PATTERN_ORDER);
        $rsaList = $m[1];
        if (!$rsaList) {
            throw new Exception("Cert file is empty!");
        }
        $md5s = array();
        foreach ($rsaList as $rsa) {
            $md5s[] = self::getRsaMd5($rsa);
        }
        $parseInfo['rsa_md5s'] = $md5s;
        return $parseInfo;
    }

    /**
     * 解析证书md5
     * Parse Cert md5
     */
    /*private static function parseRsa($apkPath) {
        $parseInfo = array();
        $outArr = self::getRsaOutput($apkPath);
        $rsaList = array();
        $pattern = "/MD5:(.*)/";
        foreach ($outArr as $line) {
            if (preg_match($pattern, $line, $m)) {
                $rsaList[] = self::getRsaMd5($m[1]);
            }
        }
        $parseInfo['rsa_md5s'] = $rsaList;
        return $parseInfo;
    }*/

    /**
     * 从解析中获取rsa md5
     */
    private static function getRsaMd5($str)
    {
        $str = trim($str);
        $md5 = strtolower(str_replace(":", "", $str));
        if (strlen($md5) != 32) {
            throw new Exception("Cert length is not 32 ($str)!");
        }
        return $md5;
    }
}

class ApkLanguage
{
    public static function isZh($str, $rate)
    {
        $regex = "/([a-zA-Z0-9\x{4e00}-\x{9fa5}])/u";
        return self::judge($str, $regex, $rate);
    }

    public static function isEn($str, $rate)
    {
        $regex = "/([a-zA-Z0-9])/";
        return self::judge($str, $regex, $rate);
    }

    private static function judge($str, $regex, $rate)
    {
        preg_match_all($regex, $str, $arr);
        if ($arr) {
            $filterStr = implode("", $arr[1]);
            $filterLen = strlen($filterStr);
            $len = strlen($str);
            if (!$len) {
                return false;
            }
            $filterRate = ($filterLen / $len) * 1.0;
            if ($filterRate > $rate) {
                return true;
            } else {
                return false;
            }
        }
        return false;    
    }
    
    /**
     * Get language (just by package name)
     */
    public static function getLanguage($apkName)
    {
        $isZh = self::isZh($apkName, 0.6);
        $isEn = self::isEn($apkName, 0.6);
        if (!$isZh && !$isEn) {
            return 4;
        }
        if ($isZh) {
            return 1;
        }
        return 3;
    }
}