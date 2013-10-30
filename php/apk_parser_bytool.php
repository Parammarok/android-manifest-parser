<?php
/**
 * apk包解析
 */
class ApkParser
{
    // 解析需要的一些命令(需要安装java1.7，例如: yum install java-1.7.0-openjdk-devel)
    private static $arrCmdMap = array(
        "app_parser.cmd.aapt" => "aapt d badging",
        "app_parser.cmd.verify" => "jarsigner -verify",
        "app_parser.cmd.rsa" => "keytool -printcert -jarfile",
    );

    /**
     * 解析包入口
     * @param string $apkPath apk的路径
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
            $msg = "包校验出错($verifyCmd: ".implode("\n", $out).")!";
            throw new Exception($msg);
        }
    }

    private static function getAaptOutput($apkPath)
    {
        $aaptCmd = self::$arrCmdMap["app_parser.cmd.aapt"]." $apkPath";
        exec($aaptCmd, $out, $ret);
        if ($ret !== 0) {
            $msg = "aapt命令执行出错($aaptCmd: ".implode("\n", $out).")!";
            throw new Exception($msg);
        }
        return $out;
    }

    /**
     * 获取aapt的应用名称
     * 名称 application-label-zh_CN application: label=
     * 修正：先读取中文名，如果没有则读取默认名
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
     * 清除名称中的各种特殊字符 
     */
    private static function cleanName($str)
    {
        if (!$str) {
            return "";
        }
        $regex = "/[\x{00a0}\x{fffe}\x{200b}]/u"; //清除utf8空格等其他特殊字符
        $str = preg_replace($regex, "", $str);
        return trim($str); //删除头尾空格
    }

    /**
     * 获取包名
     * @param type $outStr
     */
    private static function getAaptPname($outStr)
    {
        $pattern = "/package: name='(.*)'/iU";
        preg_match($pattern, $outStr, $m);
        if (!$m) {
            throw new Exception("包名提取出错!");
        }
        return trim($m[1]); //包名保持原始包名，不做大小写转换（大小写不同可能是不同包名）
    }

    //获取包的版本号，数字
    private static function getAaptVerCode($outStr)
    {
        $pattern = "/versionCode='(.*)'/iU";
        preg_match($pattern, $outStr, $m);
        return $m ? (int)$m[1] : 0;
    }

    //获取os的系统版本号，数字
    private static function getAaptOsVerCode($outStr)
    {
        $pattern = "/sdkVersion:'(.*)'/iU";
        preg_match($pattern, $outStr, $m);
        return $m ? (int)$m[1] : 0;
    }

    //获取包的版本号，字符串
    private static function getAaptVerName($outStr)
    {
        $pattern = "/versionName='(.*)'/iU";
        preg_match($pattern, $outStr, $m);
        return $m ? self::getVer($m[1]) : "";
    }
    
    //获取版本的值，提取规则为数字加点的模式
    private static function getVer($str)
    {
        $regex = "/([0-9\.]+)/";
        preg_match($regex, $str, $m);
        return $m ? trim($m[1], '.') : "";
    }

    //应用的权限
    private static function getAaptPermissions($outStr)
    {
        $pattern = "/uses-permission:'(.*)'/iU";
        preg_match_all($pattern, $outStr, $m);
        return $m ? array_unique($m[1]) : array();
    }

    //执行aapt命令，并parse其中的信息
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
        $parseInfo['permissions'] = self::getAaptPermissions($outStr); //数组格式的权限
        // $parseInfo['aapt_out'] = $outStr;
        return $parseInfo;
    }

    private static function getRsaOutput($apkPath)
    {
        $rsaCmd = self::$arrCmdMap["app_parser.cmd.rsa"]." $apkPath";
        exec($rsaCmd, $out, $ret);
        if ($ret !== 0) {
            $msg = "获取证书md5出错($rsaCmd: ".implode("\n", $out).")!";
            throw new Exception($msg);
        }
        return $out;
    }
    
    private function parseRsa($apkPath) {
        $parseInfo = array();
        $outArr = self::getRsaOutput($apkPath);
        var_dump($outArr);
        $content = implode("\n", $outArr);
        $regex = "/签名:.*?证书指纹:.*?MD5: ([0-9A-F:]+)/s";
        preg_match_all($regex, $content, $m, PREG_PATTERN_ORDER);
        $rsaList = $m[1];
        if (!$rsaList) {
            throw new Exception("解析证书为空!");
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
            throw new Exception("证书md5长度不为32($str)!");
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
     * 获取语言(当前仅根据包的名称来) 
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