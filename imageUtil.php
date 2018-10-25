<?php

/**
 * 图像操作类
 */
class imageUtil
{

    /**
     * 取得图像信息
     * @static
     * @access public
     * @param string $image 图像文件名
     * @return mixed(Array/false)
     */
    public static function getImageInfo($img)
    {
        $imageInfo = getimagesize($img);
        if ($imageInfo !== false) {
            $imageType = strtolower(substr(image_type_to_extension($imageInfo[2]), 1));
            $imageSize = filesize($img);
            $info = array(
                "width" => $imageInfo[0],
                "height" => $imageInfo[1],
                "type" => $imageType,
                "size" => $imageSize,
                "mime" => $imageInfo['mime']
            );
            return $info;
        } else {
            return false;
        }
    }

    /**
     * 生成缩略图(原始比例缩放)
     * @static
     * @access public
     * @param string $image  原图
     * @param string $thumbname 缩略图文件名
     * @param string $maxWidth  宽度(>=0)
     * @param string $maxHeight  高度(>=0)
     * @param boolean $allow_enlarge 是否允许放大(当缩略比例>1时,如果允许放大,则缩略图会按照缩略比对原图进行放大)
     * @param string $position 缩略图保存目录
     * @param boolean $interlace 启用隔行扫描
     * @return Mixed(Srting/false) false:失败,String:成功,缩略图路径
     */
    public static function thumb($image, $thumbname, $maxWidth = 200, $maxHeight = 50, $allow_enlarge = false, $interlace = true)
    {
        $maxWidth = $maxWidth < 0 ? 0 : $maxWidth;
        $maxHeight = $maxHeight < 0 ? 0 : $maxHeight;

        // 获取原图信息
        $info = Image::getImageInfo($image);
        if ($info !== false) {
            $srcWidth = $info['width'];
            $srcHeight = $info['height'];
            $type = strtolower($info['type']);
            $allow_enlarge = $allow_enlarge ? true : false;
            $interlace = $interlace ? 1 : 0;
            unset($info);
            if ($maxWidth <= 0 && $maxHeight <= 0) {
                return false;
            } elseif ($maxWidth == 0) {
                $scale = $maxHeight / $srcHeight; // 计算缩放比例
            } elseif ($maxHeight == 0) {
                $scale = $maxWidth / $srcWidth; // 计算缩放比例
            } else {
                $scale = min($maxWidth / $srcWidth, $maxHeight / $srcHeight); // 计算缩放比例
            }
            if ($scale > 1 && !$allow_enlarge) {
                // 超过原图大小不再缩略
                $width = $srcWidth;
                $height = $srcHeight;
            } else {
                // 缩略图尺寸
                $width = (int) ($srcWidth * $scale);
                $height = (int) ($srcHeight * $scale);
            }

            // 载入原图
            $createFun = 'ImageCreateFrom' . ($type == 'jpg' ? 'jpeg' : $type);
            if (!function_exists($createFun)) {
                return false;
            }
            $srcImg = $createFun($image);
            //创建缩略图
            if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
                $thumbImg = imagecreatetruecolor($width, $height);
            } else {
                $thumbImg = imagecreate($width, $height);
            }
            //png和gif的透明处理
            if ('png'==$type) {
                imagealphablending($thumbImg, false);//取消默认的混色模式（为解决阴影为绿色的问题）
                imagesavealpha($thumbImg, true);//设定保存完整的 alpha 通道信息（为解决阴影为绿色的问题）
            } elseif ('gif'==$type) {
                $trnprt_indx = imagecolortransparent($srcImg);
                if ($trnprt_indx >= 0) {
                    //its transparent
                    $trnprt_color = @imagecolorsforindex($srcImg, $trnprt_indx);//多帧gif图返回boolean(false),并触发一个Warning
                    if ($trnprt_color !== false) {//非多帧gif图
                        $trnprt_indx = imagecolorallocate($thumbImg, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                        imagefill($thumbImg, 0, 0, $trnprt_indx);
                        imagecolortransparent($thumbImg, $trnprt_indx);
                    }
                }
            }
            // 复制图片
            if (function_exists("ImageCopyResampled")) {
                imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);
            } else {
                imagecopyresized($thumbImg, $srcImg, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);
            }

            // 对jpeg图形设置隔行扫描
            if ('jpg' == $type || 'jpeg' == $type) {
                imageinterlace($thumbImg, $interlace);
            }

            // 生成图片
            $imageFun = 'image' . ($type == 'jpg' ? 'jpeg' : $type);
            $imageFun($thumbImg, $thumbname);
            imagedestroy($thumbImg);
            imagedestroy($srcImg);
            return $thumbname;
        }
        return false;
    }

    /**
     * 图象合并(将image2(可能需要适当对image2进行缩放)合并至image1的目标区域并存储为destname)
     * @static
     * @access public
     * @param string $image1  原图1
     * @param string $image2  原图2
     * @param string $destname 目标图
     * @param Array $rect 目标区域宽高及位置(优先使用right,bottom) array('width'=>,'height'=>,'left'=>,'top'=>,'right'=>,'bottom'=>)
     * @param integer $level 目标图片质量[0,100]
     * @param boolean $interlace 启用隔行扫描
     * @return Mixed(Srting/Integer)  <=0:失败,1:成功
        1 : 成功
        0 : 生成失败
        -1 : $rect 参数有误
        -2 : 获取$image1信息失败
        -3 : 目标区域大于$image1的图片区域
        -4 : 目标区域水平方向已出界
        -5 : 目标区域垂直方向已出界
        -6 : 获取$image2信息失败
        -7 : 创建图象(image1)函数不存在
        -8 : 创建图象(image2)函数不存在
     */
    public static function merge($image1, $image2, $destname, $rect, $level = 100, $interlace = true)
    {
        if (empty($rect) || !isset($rect['width']) || !isset($rect['height'])) {
            return -1;
        }

        $info1 = Image::getImageInfo($image1);
        if ($info1 === false) {
            return -2;
        }
        if ($rect['width'] > $info1['width'] || $rect['height'] > $info1['height']) {
            return -3;
        }

        //check rect
        if ($rect['right'] > $info1['width'] || ($rect['right'] <= 0 && $rect['left'] > $info1['width'])) {
            return -4;
        }
        if ($rect['bottom'] > $info1['height'] || ($rect['bottom'] <= 0 && $rect['top'] > $info1['height'])) {
            return -5;
        }

        $info2 = Image::getImageInfo($image2);
        if ($info2 === false) {
            return -6;
        }

        $createFun1 = 'ImageCreateFrom' . ($info1['type'] == 'jpg' ? 'jpeg' : $info1['type']);
        if (!function_exists($createFun1)) {
            return -7;
        }

        $createFun2 = 'ImageCreateFrom' . ($info2['type'] == 'jpg' ? 'jpeg' : $info2['type']);
        if (!function_exists($createFun2)) {
            return -8;
        }

        //load image2
        $srcImg2 = $createFun2($image2);
        if ($info2['width'] != $rect['width'] || $info2['height'] != $rect['height']) {//need resize
            /*计算应该如何缩放*/
            if ($info2['width']/$info2['height'] >= $rect['width']/$rect['height']) {
                /*按宽度缩放*/
                $scale_mode = 1;
                $info2_width = $rect['width'];
                $info2_height = round($info2['height']*$rect['width']/$info2['width']);
            } else {
                /*按高缩放*/
                $scale_mode = 2;
                $info2_width = round($info2['width']*$rect['height']/$info2['height']);
                $info2_height = $rect['height'];
            }
            //create tmpImg
            if ($info2['type'] != 'gif' && function_exists('imagecreatetruecolor')) {
                $tmpImg = imagecreatetruecolor($info2_width, $info2_height);
            } else {
                $tmpImg = imagecreate($info2_width, $info2_height);
            }
            //png和gif的透明处理
            if ('png' == $info2['type']) {
                imagealphablending($tmpImg, false);//取消默认的混色模式
                imagesavealpha($tmpImg, true);//设定保存完整的 alpha 通道信息
            } elseif ('gif' == $info2['type']) {
                $trnprt_indx = imagecolortransparent($srcImg2);//获取透明色
                if ($trnprt_indx >= 0) {
                    //its transparent
                    $trnprt_color = @imagecolorsforindex($srcImg2, $trnprt_indx);//多帧gif图返回boolean(false),并触发一个Warning
                    if ($trnprt_color !== false) {//非多帧gif图
                        $trnprt_indx = imagecolorallocate($tmpImg, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                        imagefill($tmpImg, 0, 0, $trnprt_indx);
                        imagecolortransparent($tmpImg, $trnprt_indx);//设定透明色
                    }
                }
            }
            //复制(含缩放)图片
            if (function_exists("ImageCopyResampled")) {
                imagecopyresampled($tmpImg, $srcImg2, 0, 0, 0, 0, $info2_width, $info2_height, $info2['width'], $info2['height']);
            } else {
                imagecopyresized($tmpImg, $srcImg2, 0, 0, 0, 0, $info2_width, $info2_height, $info2['width'], $info2['height']);
            }
            //update size
            $info2['width'] = $info2_width;
            $info2['height'] = $info2_height;
            imagedestroy($srcImg2);
            $srcImg2 = $tmpImg;
            unset($tmpImg);
        } else {
            $scale_mode = 0;
            //png的透明处理
            if ('png' == $info2['type']) {
                imagealphablending($srcImg2, false);//取消默认的混色模式
                imagesavealpha($srcImg2, true);//设定保存完整的 alpha 通道信息
            }
        }

        //load image1
        $srcImg1 = $createFun1($image1);
        //png的透明处理
        if ('png' == $info1['type']) {
            imagealphablending($srcImg1, false);//取消默认的混色模式
            imagesavealpha($srcImg1, true);//设定保存完整的 alpha 通道信息
        }

        //calculate position
        $dst_x = 0;
        $dst_y = 0;

        if ($rect['right'] > 0) {
            $dst_x = $info1['width'] - ($info2['width'] + $rect['right']);
        } else {
            $dst_x = $rect['left'];
        }
        $dst_x -= ($rect['width'] - $info2['width'])/2;

        if ($rect['bottom'] > 0) {
            $dst_y = $info1['height'] - ($info2['height'] + $rect['bottom']);
        } else {
            $dst_y = $rect['top'];
        }
        $dst_y -= ($rect['height'] - $info2['height'])/2;

        // creating a cut resource from image2
        if ($info2['type'] != 'gif' && function_exists('imagecreatetruecolor')) {
            $cut = imagecreatetruecolor($info2['width'], $info2['height']);
        } else {
            $cut = imagecreate($info2['width'], $info2['height']);
        }
        if ('png' == $info2['type']) {
            imagealphablending($cut, false);//取消默认的混色模式
            imagesavealpha($cut, true);//设定保存完整的 alpha 通道信息
        }
        //copying that section of the background to the cut
        imagecopy($cut, $srcImg1, 0, 0, $dst_x, $dst_y, $info2['width'], $info2['height']);
        // placing the image2
        imagecopy($cut, $srcImg2, 0, 0, 0, 0, $info2['width'], $info2['height']);

        //merge
        imagealphablending($srcImg1, true);
        imagecopy($srcImg1, $cut, $dst_x, $dst_y, 0, 0, $info2['width'], $info2['height']);

        //对jpeg图形设置隔行扫描
        if ('jpg' == $info1['type'] || 'jpeg' == $info1['type']) {
            imageinterlace($srcImg1, $interlace);
        }

        //generate pic
        $imageFun = 'image' . ($info1['type'] == 'jpg' ? 'jpeg' : $info1['type']);
        if ($info1['type'] == 'jpg' || $info1['type'] == 'jpeg') {
            $retflag = $imageFun($srcImg1, $destname, $level);
        } else {
            $retflag = $imageFun($srcImg1, $destname);
        }
        imagedestroy($srcImg1);
        imagedestroy($srcImg2);
        imagedestroy($cut);
        return $retflag?1:0;
    }

    /**
     * 生成裁切图
     * @static
     * @access public
     * @param string $image  原图
     * @param string $destname 目标图
     * @param boolean $destWidth 目标图像宽度
     * @param string $destHeight 目标图像高度
     * @param integer $level 目标图片质量[0,100]
     * @param boolean $interlace 启用隔行扫描
     * @return Mixed(Srting/false)  false:失败,String:成功,裁切后图像路径
     */
    public static function cut($image, $destname, $destWidth = 200, $destHeight = 200, $level = 100, $interlace = true)
    {
        if ($destWidth <= 0 || $destHeight <= 0) {
            return false;
        }
        // 获取原图信息
        $info = Image::getImageInfo($image);
        if ($info === false) {
            return false;
        }
        $srcWidth = $info['width'];
        $srcHeight = $info['height'];
        $type = strtolower($info['type']);
        $interlace = $interlace ? 1 : 0;
        unset($info);
        $srcScale = $srcWidth/$srcHeight;//原图比例
        $destScale = $destWidth/$destHeight;//目标图比例

        if (($srcScale == $destScale && $srcWidth == $destWidth) || ($srcWidth <= $destWidth && $srcHeight <= $destHeight)) {
            if (!@copy($image, $destname)) {
                return false;
            }
            return $destname;
        }

        // 载入原图
        $createFun = 'ImageCreateFrom' . ($type == 'jpg' ? 'jpeg' : $type);
        if (!function_exists($createFun)) {
            return false;
        }
        $srcImg = $createFun($image);

        $srcLeft = $srcTop = 0;
        $cutWidth = $cutHeight = 0;
        if ($srcScale == $destScale) {//等比裁切
            $cutWidth = $srcWidth;
            $cutHeight = $srcHeight;
            $srcLeft = 0;
            $srcTop = 0;
        } else {
            if ($srcWidth > $destWidth && $srcHeight > $destHeight) {
                if ($srcScale > $destScale) {
                    $cutWidth = $srcHeight*$destWidth/$destHeight;
                    $cutHeight = $srcHeight;
                    $srcLeft = ($srcWidth-$cutWidth)/2;
                    $srcTop = 0;
                } else {
                    $cutWidth = $srcWidth;
                    $cutHeight = $srcWidth*$destHeight/$destWidth;
                    $srcLeft = 0;
                    $srcTop = ($srcHeight-$cutHeight)/2;
                }
            } elseif ($srcWidth <= $destWidth && $srcHeight <= $destHeight) {
                if (!@copy($image, $destname)) {
                    return false;
                }
                return $destname;
            } else {
                $cutWidth = $srcWidth;
                $cutHeight = $srcHeight;
                $srcLeft = 0;
                $srcTop = 0;
                if ($srcWidth < $destWidth) {
                    $destWidth = $destHeight*$srcWidth/$srcHeight;
                } else {
                    $destHeight = $destWidth*$srcHeight/$srcWidth;
                }
            }
        }

        //创建目标图
        if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
            $destImg = imagecreatetruecolor($destWidth, $destHeight);
        } else {
            $destImg = imagecreate($destWidth, $destHeight);
        }
        //png和gif的透明处理 by luofei614
        if ('png' == $type) {
            imagealphablending($destImg, false);//取消默认的混色模式（为解决阴影为绿色的问题）
            imagesavealpha($destImg, true);//设定保存完整的 alpha 通道信息（为解决阴影为绿色的问题）
            imagefill($destImg, 0, 0, imagecolorallocatealpha($destImg, 0, 0, 0, 127));
        } elseif ('gif' == $type) {
            $trnprt_indx = imagecolortransparent($srcImg);//获取透明色
            if ($trnprt_indx >= 0) {
                //its transparent
                $trnprt_color = @imagecolorsforindex($srcImg, $trnprt_indx);//多帧gif图返回boolean(false),并触发一个Warning
                if ($trnprt_color !== false) {//非多帧gif图
                    $trnprt_indx = imagecolorallocate($destImg, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                    imagefill($destImg, 0, 0, $trnprt_indx);
                    imagecolortransparent($destImg, $trnprt_indx);//设定透明色
                }
            }
        }

        // 复制(含缩放)图片
        if (function_exists("ImageCopyResampled")) {
            imagecopyresampled($destImg, $srcImg, 0, 0, $srcLeft, $srcTop, $destWidth, $destHeight, $cutWidth, $cutHeight);
        } else {
            imagecopyresized($destImg, $srcImg, 0, 0, $srcLeft, $srcTop, $destWidth, $destHeight, $cutWidth, $cutHeight);
        }

        // 对jpeg图形设置隔行扫描
        if ('jpg' == $type || 'jpeg' == $type) {
            imageinterlace($destImg, $interlace);
        }

        // 生成图片
        $imageFun = 'image' . ($type == 'jpg' ? 'jpeg' : $type);
        if ($type == 'jpg' || $type == 'jpeg') {
            $retflag = $imageFun($destImg, $destname, $level);
        } else {
            $retflag = $imageFun($destImg, $destname);
        }
        imagedestroy($destImg);
        imagedestroy($srcImg);
        return $retflag?$destname:false;
    }

    /**
     * 生成裁切图
     * @static
     * @access public
     * @param string $image  原图
     * @param string $destname 生成的目标图路径
     * @param boolean $srcCoord 原图坐标  array('left'=>, 'top'=>, 'width'=>, 'height'=>)
     * @param string $destCoord 目标图坐标 array('left'=>, 'top'=>, 'width'=>, 'height'=>)
     * @param boolean $destWidth 目标图像宽度
     * @param string $destHeight 目标图像高度
     * @param integer $level 目标图片质量[0,100]
     * @param boolean $interlace 启用隔行扫描
     * @return Mixed(Srting/false)  false:失败,String:成功,裁切后图像路径
     */
    public static function cutnew($image, $destname, $srcCoord, $destCoord, $destWidth, $destHeight, $level = 100, $interlace = true)
    {
        if ($destWidth <= 0 || $destHeight <= 0) {
            return false;
        }
        // 获取原图信息
        $info = Image::getImageInfo($image);
        if ($info === false) {
            return false;
        }
        $srcWidth = $info['width'];
        $srcHeight = $info['height'];
        $type = strtolower($info['type']);
        $interlace = $interlace ? 1 : 0;
        unset($info);

        if ($srcWidth == $destWidth && $srcHeight == $destHeight
            && $srcCoord['left'] == 0 && $srcCoord['top'] == 0
            && $srcCoord['width'] == $srcWidth && $srcCoord['height'] == $srcHeight
            && $destCoord['left'] == 0 && $destCoord['top'] == 0
            && $destCoord['width'] == $destWidth && $destCoord['height'] == $destHeight
          ) {
            if (!@copy($image, $destname)) {
                return false;
            }
            return $destname;
        }

        // 载入原图
        $createFun = 'ImageCreateFrom' . ($type == 'jpg' ? 'jpeg' : $type);
        if (!function_exists($createFun)) {
            return false;
        }
        $srcImg = $createFun($image);

        //创建目标图
        if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
            $destImg = imagecreatetruecolor($destWidth, $destHeight);
        } else {
            $destImg = imagecreate($destWidth, $destHeight);
        }
        //png和gif的透明处理 by luofei614
        if ('png' == $type) {
            imagesavealpha($destImg, true);//设定保存完整的 alpha 通道信息（为解决阴影为绿色的问题）
            imagealphablending($destImg, false);//取消默认的混色模式（为解决阴影为绿色的问题）
            imagefill($destImg, 0, 0, imagecolorallocatealpha($destImg, 0, 0, 0, 127));
        } elseif ('gif' == $type) {
            $trnprt_indx = imagecolortransparent($srcImg);//获取透明色
            if ($trnprt_indx >= 0) {
                //its transparent
                $trnprt_color = @imagecolorsforindex($srcImg, $trnprt_indx);//多帧gif图返回boolean(false),并触发一个Warning
                if ($trnprt_color !== false) {//非多帧gif图
                    $trnprt_indx = imagecolorallocate($destImg, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
                    imagefill($destImg, 0, 0, $trnprt_indx);
                    imagecolortransparent($destImg, $trnprt_indx);//设定透明色
                }
            }
        }

        // 复制(含缩放)图片
        if (function_exists("ImageCopyResampled")) {
            imagecopyresampled($destImg, $srcImg, $destCoord['left'], $destCoord['top'], $srcCoord['left'], $srcCoord['top'], $destCoord['width'], $destCoord['height'], $srcCoord['width'], $srcCoord['height']);
        } else {
            imagecopyresized($destImg, $srcImg, $destCoord['left'], $destCoord['top'], $srcCoord['left'], $srcCoord['top'], $destCoord['width'], $destCoord['height'], $srcCoord['width'], $srcCoord['height']);
        }
        // 对jpeg图形设置隔行扫描
        if ('jpg' == $type || 'jpeg' == $type) {
            imageinterlace($destImg, $interlace);
        }

        // 生成图片
        $imageFun = 'image' . ($type == 'jpg' ? 'jpeg' : $type);
        if ($type == 'jpg' || $type == 'jpeg') {
            $retflag = $imageFun($destImg, $destname, $level);
        } else {
            $retflag = $imageFun($destImg, $destname);
        }
        imagedestroy($destImg);
        imagedestroy($srcImg);
        return $retflag?$destname:false;
    }
}

if (!function_exists('image_type_to_extension')) {
    define("INVALID_IMAGETYPE", '');
    function image_type_to_extension($image_type, $include_dot = true)
    {
        $extension = INVALID_IMAGETYPE;/// Default return value for invalid input
        $image_type_identifiers = array (### These values correspond to the IMAGETYPE constants
            array (IMAGETYPE_GIF        => 'gif',   "mime_type" => 'image/gif'),                    ###  1 = GIF
            array (IMAGETYPE_JPEG       => 'jpg',   "mime_type" => 'image/jpeg'),                   ###  2 = JPG
            array (IMAGETYPE_PNG        => 'png',   "mime_type" => 'image/png'),                    ###  3 = PNG
            array (IMAGETYPE_SWF        => 'swf',   "mime_type" => 'application/x-shockwave-flash'),###  4 = SWF  // A. Duplicated MIME type
            array (IMAGETYPE_PSD        => 'psd',   "mime_type" => 'image/psd'),                    ###  5 = PSD
            array (IMAGETYPE_BMP        => 'bmp',   "mime_type" => 'image/bmp'),                    ###  6 = BMP
            array (IMAGETYPE_TIFF_II    => 'tiff',  "mime_type" => 'image/tiff'),                   ###  7 = TIFF (intel byte order)
            array (IMAGETYPE_TIFF_MM    => 'tiff',  "mime_type" => 'image/tiff'),                   ###  8 = TIFF (motorola byte order)
            array (IMAGETYPE_JPC        => 'jpc',   "mime_type" => 'application/octet-stream'),     ###  9 = JPC  // B. Duplicated MIME type
            array (IMAGETYPE_JP2        => 'jp2',   "mime_type" => 'image/jp2'),                    ### 10 = JP2
            array (IMAGETYPE_JPX        => 'jpf',   "mime_type" => 'application/octet-stream'),     ### 11 = JPX  // B. Duplicated MIME type
            array (IMAGETYPE_JB2        => 'jb2',   "mime_type" => 'application/octet-stream'),     ### 12 = JB2  // B. Duplicated MIME type
            array (IMAGETYPE_SWC        => 'swc',   "mime_type" => 'application/x-shockwave-flash'),### 13 = SWC  // A. Duplicated MIME type
            array (IMAGETYPE_IFF        => 'aiff',  "mime_type" => 'image/iff'),                    ### 14 = IFF
            array (IMAGETYPE_WBMP       => 'wbmp',  "mime_type" => 'image/vnd.wap.wbmp'),           ### 15 = WBMP
            array (IMAGETYPE_XBM        => 'xbm',   "mime_type" => 'image/xbm'),                    ### 16 = XBM
        );

        if ((is_int($image_type)) and (IMAGETYPE_GIF <= $image_type) and (IMAGETYPE_XBM >= $image_type)) {
            $extension = $image_type_identifiers[$image_type-1]; // -1 because $image_type_identifiers array starts at [0]
            $extension = $extension[$image_type];
        } elseif (is_string($image_type) and (($image_type != 'application/x-shockwave-flash') or ($image_type != 'application/octet-stream'))) {
            $extension =  match_mime_type_to_extension($image_type, $image_type_identifiers);
        } else {
            $extension = INVALID_IMAGETYPE;
        }

        if (is_bool($include_dot)) {
            if ((false != $include_dot) and (INVALID_IMAGETYPE != $extension)) {
                $extension = '.' . $extension;
            }
        } else {
            $extension = INVALID_IMAGETYPE;
        }
        return $extension;
    }

    function match_mime_type_to_extension($image_type, $image_type_identifiers)
    {
            // Return from loop on a match
        foreach ($image_type_identifiers as $_key_outer_loop => $_val_outer_loop) {
            foreach ($_val_outer_loop as $_key => $_val) {
                if (is_int($_key)) {// Keep record of extension for mime check
                    $extension = $_val;
                }
                if ($_key == 'mime_type') {
                    if ($_val === $image_type) {// Found match no need to continue looping
                        return $extension;### Return
                    }
                }
            }
        }
            // Compared all values without match
            return $extension = INVALID_IMAGETYPE;
    }
}
