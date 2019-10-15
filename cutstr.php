<?php

$str = "你好啊123发挥好阿斯顿发撒发生地方";

$nstr = cutstr($str, 11, '', 2, 'utf-8');

var_dump($str, $nstr);

/**	功能 截取字符串
 *	@param $string String 要截取的字符串
 *	@param $length Integer 截取的长度
 *	@param $dot String 截取后追加的串(没有截取则不会追加)
 *	@param $rule Integer 截取规则 缺省0 [0:按照字节长度截取,utf8或非utf8,1个中文字符字节长度记为3或2,1个非中文字符字节长度记为1或1, 1:按照字长度截取,不论何种编码,不论是否中文字符,1个字符的字长均记为1, 2或其他值:按照字长度截取,不论何种编码,1个中文字符的字长记为2非中文字长度记为1]
 *	@param $charset String $string参数的字符编码类型 缺省utf-8
 *	@return String
 */
function cutstr($string, $length, $dot = '...', $rule = 0, $charset = 'utf-8') {
    if($string === '')
        return $string;
    $charset = $charset === '' ? 'utf-8' : strtolower($charset);
    $charset = $charset == 'utf-8' || $charset == 'utf8' ? 'utf-8' : $charset;

    $strlen = strlen($string); // 字符串的字节长度
    if($rule == 0 && $strlen <= $length) {
        return $string;
    }

    $wl = $rule == 0 ? ($charset == 'utf-8' ? 3 : 2) : ($rule == 1 ? 1 : 2); // 1个非英文字符用于计算的长度
    $strcut = '';
    if($charset == 'utf-8') {
        $n = $tn = $noc = 0; // 累计的字节数 当前字符占的字节数 按长度规则累计的字符长度
        while($n < $strlen) {
            $t = ord($string[$n]);
            if($t == 9 || $t == 10 || $t == 13 || (32 <= $t && $t <= 126)) { // 水平制表符,换行符,回车符,及空格,字母普通字符
                $tn = 1; $n++; $noc++;
            } elseif(194 <= $t && $t <= 223) {
                $tn = 2; $n += 2; $noc += $wl;
            } elseif(224 <= $t && $t <= 239) {
                $tn = 3; $n += 3; $noc += $wl;
            } elseif(240 <= $t && $t <= 247) {
                $tn = 4; $n += 4; $noc += $wl;
            } elseif(248 <= $t && $t <= 251) {
                $tn = 5; $n += 5; $noc += $wl;
            } elseif($t == 252 || $t == 253) {
                $tn = 6; $n += 6; $noc += $wl;
            } else { // [0,8] U {11,12} U [14,31] U [127,193] U {254,255} 均为控制符,不计入长度,但产生字节位移
                $n++;
            }

            if($noc >= $length) {
                break;
            }

        }
        if($noc > $length) {
            $n -= $tn;
        }
    } else {
        $n = $tn = $noc = 0; // 累计的字节数 当前字符占的字节数 按长度规则累计的字符长度
        while($n < $strlen) {
            if(ord($string[$n]) <= 127) {
                $tn = 1; $n++; $noc++;
            } else {
                $tn = 2; $n += 2; $noc += $wl;
            }
            if($noc >= $length) {
                break;
            }
        }
        if($noc > $length) {
            $n -= $tn;
        }
    }
    $strcut = substr($string, 0, $n);

    $pos = strrpos($strcut, chr(1));
    if($pos !== false) {
        $strcut = substr($strcut, 0, $pos);
    }
    return strlen($strcut) == $strlen ? $strcut : $strcut.$dot;
}