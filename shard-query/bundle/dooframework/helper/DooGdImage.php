<?php
/**
 * DooGdImage class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * A helper class that helps to manage images, upload, resize, create thumbnails, crop images, etc.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooGdImage.php 1000 2009-08-19 21:37:38
 * @package doo.helper
 * @since 1.1
 */
class DooGdImage {
    
    /**
     * Path to store the uploaded image files
     * @var string
     */
    public $uploadPath;

    /**
     * Path to store the resized/processed image files(thumbnails/watermarks)
     * @var string
     */
    public $processPath;

    /**
     * Suffix name for the generated thumbnail image files, eg. 203992029_thumb.jpg
     * @var string
     */
    public $thumbSuffix = '_thumb';

    /**
     * Suffix name for the generated watermarked image files, eg. 203992029_water.jpg
     * @var string
     */
    public $waterSuffix = '_water';

    /**
     * Suffix name for the cropped image files, eg. 203992029_crop.jpg
     * @var string
     */
    public $cropSuffix = '_crop';

	/**
	 * Suffix name for the rotate image files, eg 203992029_rotated.jpg
	 */
	public $rotateSuffix = '_rotated';

    /**
     * Determine whether to save the processed images
     * @var bool
     */
    public $saveFile = true;

    /**
     * The file name of the TTF font to be used with watermarks.
     * @var string
     */
    public $ttfFont;

    /**
     * Generated image type. gif, png, jpg
     * @var string
     */
    public $generatedType = 'jpg';

    /**
     * Generated image quality. For png & jpg only
     * @var string
     */
    public $generatedQuality = 100;

    /**
     * Determine whether to use time + unique value as a file name for the uploaded images.
     * @var bool
     */
    public $timeAsName = true;

    /**
     * Construtor for DooGdImage
     *
     * @param string $uploadPath Path of the uploaded image
     * @param string $processPath Path to save the processes images
     * @param bool $saveFile To save the processed images
     */
    public function  __construct($uploadPath='', $processPath='', $saveFile=true, $timeAsName=true){
        $this->uploadPath = $uploadPath;
        $this->processPath = $processPath;
        $this->saveFile = $saveFile;
        $this->timeAsName = $timeAsName;
    }

    /**
     * Set the TTF Font type for water mark processing.
     * @param string $ttfFontFile the TTF font file name
     * @param string $path Path to the font
     */
    public function setFont($ttfFontFile, $path=''){
        $this->ttfFont = $path . $ttfFontFile;
    }

    /**
     * Creates an image object based on the file type.
     *
     * @param mixed The variable to store the image resource created.
     * @param string $type Image type, gif, png, jpg
     * @param string $file File name of the image
     * @return resource
     */
    protected function createImageObject(&$img, $type, $file){
        switch($type){
            case 1:
                $img = imagecreatefromgif($file);
                break;
            case 2:
                $img = imagecreatefromjpeg($file);
                break;
            case 3:
                $img = imagecreatefrompng($file);
                break;
            default:
                return false;
        }
        return $img;
    }

    /**
     * Creates an image from a resouce based on the generatedType
     * 
     * @param resource $img The image resource
     * @param string $file File name to be stored.
     * @return bool
     */
    protected function generateImage(&$img, $file=null){
        switch($this->generatedType){
            case 'gif':
                return imagegif($img, $file);
                break;
            case 'jpg':
                return imagejpeg($img, $file, $this->generatedQuality);
                break;
            case 'png':
                if($this->generatedQuality>9)
                        $quality = 9;
                else
                    $quality = $this->generatedQuality;
                return imagepng($img, $file, $quality);
                break;
            default:
                return false;
        }
    }

    /**
     * Crops an image.
     * 
     * @param string $file Image file name
     * @param int $cropWidth Width to be cropped
     * @param int $cropHeight Height to be cropped
     * @param int $cropStartX Position x to start cropping
     * @param int $cropStartY Position y to start cropping
     * @param string $rename New file name for the processed image file to be saved.
     * @return bool|string Returns the generated image file name. Return false if failed.
     */
    public function crop($file, $cropWidth, $cropHeight, $cropStartX=0, $cropStartY=0, $rename=''){
        $file = $this->uploadPath . $file;
        $imginfo = $this->getInfo($file);

        if($rename=='')
            $newName = substr($imginfo['name'], 0, strrpos($imginfo['name'], '.')) . $this->cropSuffix .'.'. $this->generatedType;
        else
            $newName = $rename .'.'. $this->generatedType;

        //create image object based on the image file type, gif, jpeg or png
        $this->createImageObject($img, $imginfo['type'], $file);

        if(!$img) return false;

        $cropimg = imagecreatetruecolor($cropWidth,$cropHeight);
        $width = $imginfo['width'];
        $height = $imginfo['height'];

        //Crop now
        imagecopyresampled($cropimg, $img, 0, 0, $cropStartX, $cropStartY, $width, $height, $width, $height);

        if($this->saveFile){
            //delete if exist
            if(file_exists($this->processPath . $newName))
                unlink($this->processPath . $newName);
            $this->generateImage($cropimg, $this->processPath . $newName);
            imagedestroy($cropimg);
            imagedestroy($img);
            return $this->processPath . $newName;
        }
        else{
            $this->generateImage($cropimg);
            imagedestroy($cropimg);
            imagedestroy($img);
        }

        return true;
    }

	/**
	 * Rotate an image Clockwise by specified amount
	 *
	 * @param string $file Image file name
	 * @param int $rotateBy Amount to rotate the image by in clockwise direction
	 * @param string $rename New file name for the processed image file to be saved
	 * @return bool|string Returns the generated image file name. Return false if failed
	 */
	public function rotate($file, $rotateBy, $rename='') {

		$file = $this->uploadPath . $file;
		$imginfo = $this->getInfo($file);

		if($rename=='') {
            $newName = substr($imginfo['name'], 0, strrpos($imginfo['name'], '.')) . $this->rotateSuffix .'.'. $this->generatedType;
		} else {
            $newName = $rename .'.'. $this->generatedType;
		}

		// create image object based on the image file type, gif, jpeg or png
        $this->createImageObject($img, $imginfo['type'], $file);

        if(!$img) return false;

		// Rotate the image. We take roation away from 360 as image rotate rotates Counter Clockwise
		$img = imagerotate($img, (360 - intval($rotateBy)), 0);

		if($this->saveFile){
            //delete if exist
            if(file_exists($this->processPath . $newName)) {
                unlink($this->processPath . $newName);
			}
            $this->generateImage($img, $this->processPath . $newName);
            imagedestroy($img);
            return $this->processPath . $newName;
        }
        else{
            $this->generateImage($img);
            imagedestroy($img);
        }

        return true;
	}


    /**
     * Resize/Generates thumbnail from an existing image file.
     * 
     * @param string $file The image file name.
     * @param int $width Width of the thumbnail
     * @param int $height Height of the thumbnail
     * @param string $rename New file name for the processed image file to be saved.
     * @return bool|string Returns the generated image file name. Return false if failed.
     */
    public function createThumb($file, $width=128, $height=128, $rename=''){
        $file = $this->uploadPath . $file;
        $imginfo = $this->getInfo($file);
        
        if($rename=='')
            $newName = substr($imginfo['name'], 0, strrpos($imginfo['name'], '.')) . $this->thumbSuffix .'.'. $this->generatedType;
        else
            $newName = $rename .'.'. $this->generatedType;

        //create image object based on the image file type, gif, jpeg or png
        $this->createImageObject($img, $imginfo['type'], $file);

        if(!$img) return false;

        $width  = ($width > $imginfo['width']) ? $imginfo['width'] : $width;
        $height = ($height > $imginfo['height']) ? $imginfo['height'] : $height;
        $oriW = $imginfo['width'];
        $oriH = $imginfo['height'];

        //maintain ratio
        if($oriW*$width > $oriH*$height)
            $height = round($oriH * $width/$oriW);
        else
            $width = round($oriW * $height/$oriH);

        //For GD version 2.0.1 only
        if (function_exists('imagecreatetruecolor')){
            $newImg = imagecreatetruecolor($width, $height);
            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $width, $height, $imginfo['width'], $imginfo['height']);
        }
        else{
            $newImg = imagecreate($width, $height);
            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $width, $height, $imginfo['width'], $imginfo['height']);
        }

        if($this->saveFile){
            //delete if exist
            if(file_exists($this->processPath . $newName))
                unlink($this->processPath . $newName);
            $this->generateImage($newImg, $this->processPath . $newName);
            imagedestroy($newImg);
            imagedestroy($img);
            return $this->processPath . $newName;
        }
        else{
            $this->generateImage($newImg);
            imagedestroy($newImg);
            imagedestroy($img);
        }
        
        return true;
    }

    /**
     * Create a square thumbnail (resize adaptively)
     *
     * @param string $file The image file name.
     * @param int $size Width/height of the thumbnail
     * @param string $rename New file name for the processed image file to be saved.
     * @return bool|string Returns the generated image file name. Return false if failed.
     */
    public function createSquare($file, $size, $rename=''){
        return $this->adaptiveResize($file, $size, $size, $rename);
    }

    /**
     * Adaptively Resizes the Image
     *
     * Resize the image to as close to the provided dimensions as possible, and then crops the
     * remaining overflow (from the center) to get the image to be the size specified
     *
     * @param string $file The image file name.
     * @param int $width
     * @param int $height
     * @param string $rename New file name for the processed image file to be saved.
     * @return bool|string Returns the generated image file name. Return false if failed.
     */
    public function adaptiveResize($file, $width, $height, $rename='') {
        $file = $this->uploadPath . $file;
        $imginfo = $this->getInfo($file);
        
        if($rename=='')
            $newName = substr($imginfo['name'], 0, strrpos($imginfo['name'], '.')) . $this->thumbSuffix .'.'. $this->generatedType;
        else
            $newName = $rename .'.'. $this->generatedType;

        //create image object based on the image file type, gif, jpeg or png
        $this->createImageObject($img, $imginfo['type'], $file);

        if(!$img) return false;

        if($imginfo['height'] == $imginfo['width']){
            $resizeWidth = $width;
            $resizeHeight = $height;
        }
        else if($imginfo['height'] > $imginfo['width']){
            $resizeWidth = $width;
            $resizeHeight = ($imginfo['height']/$imginfo['width'])*$resizeWidth;
        }else{
            $resizeHeight = $height;
            $resizeWidth = ($imginfo['width']/$imginfo['height'])*$resizeHeight;
        }

        //For GD version 2.0.1 only
        if (function_exists('imagecreatetruecolor')){
            $newImg = imagecreatetruecolor($width, $height);
            imagecopyresampled($newImg, $img, ($width-$resizeWidth)/2, ($height-$resizeHeight)/2, 0, 0, $resizeWidth, $resizeHeight, $imginfo['width'], $imginfo['height']);
        }
        else{
            $newImg = imagecreate($width, $height);
            imagecopyresampled($newImg, $img, ($width-$resizeWidth)/2, ($height-$resizeHeight)/2, 0, 0, $resizeWidth, $resizeHeight, $imginfo['width'], $imginfo['height']);
        }

        if($this->saveFile){
            //delete if exist
            if(file_exists($this->processPath . $newName))
                unlink($this->processPath . $newName);
            $this->generateImage($newImg, $this->processPath . $newName);
            imagedestroy($newImg);
            imagedestroy($img);
            return $this->processPath . $newName;
        }
        else{
            $this->generateImage($newImg);
            imagedestroy($newImg);
            imagedestroy($img);
        }

        return true;
    }


	    public function adaptiveResizeCropExcess($file, $width=128, $height=128, $rename=''){
        $file = $this->uploadPath . $file;

        $imginfo = $this->getInfo($file);

        if($rename=='')
            $newName = substr($imginfo['name'], 0, strrpos($imginfo['name'], '.')) . $this->thumbSuffix .'.'. $this->generatedType;
        else
            $newName = $rename .'.'. $this->generatedType;

        //create image object based on the image file type, gif, jpeg or png
        $this->createImageObject($img, $imginfo['type'], $file);

        if(!$img) return false;

		if (($imginfo['width'] / $imginfo['height']) > ($width / $height) ) {	
			$srcY = 0;
			$srcH = $imginfo['height'];
			$srcW = round($width / ($height / $imginfo['height']));
			$srcX = round(($imginfo['width'] / 2) - ($srcW / 2));
		} else {
			$srcX = 0;
			$srcW = $imginfo['width'];
			$srcH = round($height / ($width / $imginfo['width']));
			$srcY = round(($imginfo['height'] / 2) - ($srcH / 2));
		}

        //For GD version 2.0.1 only
        if (function_exists('imagecreatetruecolor')){
            $newImg = imagecreatetruecolor($width, $height);

            imagecopyresampled($newImg, $img, 0, 0, $srcX, $srcY, $width, $height, $srcW, $srcH);
        }
        else{
            $newImg = imagecreate($width, $height);
            imagecopyresampled($newImg, $img, 0, 0, $srcX, $srcY, $width, $height, $srcW, $srcH);
        }

        if($this->saveFile){
            //delete if exist
            if(file_exists($this->processPath . $newName))
                unlink($this->processPath . $newName);
            $this->generateImage($newImg, $this->processPath . $newName);
            imagedestroy($newImg);
            imagedestroy($img);
            return $this->processPath . $newName;
        }
        else{
            $this->generateImage($newImg);
            imagedestroy($newImg);
            imagedestroy($img);
        }

        return true;
    }


	/**
     * Resizes the Image and keep it proportioned
     *
     * Resize the image to fit within specified area while maintaining the image ratio.
	 *
	 * There are 3 modes of operation which are
	 *  - To resize so width matches specification and height it auto determined (set width but leave height as null)
	 *  - To resize so height matches spectification and width is auto determined (set width to null and height to desired height)
	 *  - To resize so image fits within the area defined by width and height (set both width and height)
     *
     * @param string $file The image file name.
     * @param int $width The maximum width of the new image (or null if only setting height)
     * @param int $height The maximum height of the new image (or null if only setting width)
     * @param string $rename New file name for the processed image file to be saved.
     * @return bool|string Returns the generated image file name. Return false if failed.
     */
	public function ratioResize($file, $width=null, $height=null, $rename='') {

		$file = $this->uploadPath . $file;
        $imginfo = $this->getInfo($file);

        if($rename=='')
            $newName = substr($imginfo['name'], 0, strrpos($imginfo['name'], '.')) . $this->thumbSuffix .'.'. $this->generatedType;
        else
            $newName = $rename .'.'. $this->generatedType;

		if ($width === null && $height === null) {
			return false;
		} elseif ($width !== null && $height === null) {
			$resizeWidth = $width;
			$resizeHeight = ($width / $imginfo['width']) * $imginfo['height'];
		} elseif ($width === null && $height !== null) {
			$resizeWidth = ($height / $imginfo['height']) * $imginfo['width'];
			$resizeHeight = $height;
		} else {
			if ($imginfo['width'] > $imginfo['height']) {
				$resizeWidth = $width;
				$resizeHeight = ($width / $imginfo['width']) * $imginfo['height'];
			} else {
				$resizeWidth = ($height / $imginfo['height']) * $imginfo['width'];
				$resizeHeight = $height;
			}
		}

		//create image object based on the image file type, gif, jpeg or png
        $this->createImageObject($img, $imginfo['type'], $file);
		if(!$img) return false;

        if (function_exists('imagecreatetruecolor')){
            $newImg = imagecreatetruecolor($resizeWidth, $resizeHeight);
            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $resizeWidth, $resizeHeight, $imginfo['width'], $imginfo['height']);
        }
        else{
            $newImg = imagecreate($resizeWidth, $resizeHeight);
            imagecopyresampled($newImg, $img, ($width-$resizeWidth)/2, ($height-$resizeHeight)/2, 0, 0, $resizeWidth, $resizeHeight, $imginfo['width'], $imginfo['height']);
        }
		
		imagedestroy($img);

        if($this->saveFile){
            //delete if exist
            if(file_exists($this->processPath . $newName))
                unlink($this->processPath . $newName);
            $this->generateImage($newImg, $this->processPath . $newName);
            imagedestroy($newImg);
            return $this->processPath . $newName;
        }
        else{
            $this->generateImage($newImg);
            imagedestroy($newImg);
        }

        return true;

	}

    /**
     * Add water mark text to an image.
     * 
     * @param string $file Image file name
     * @param string $text Text to be added as water mark
     * @param int $maxWidth Maximum width of the processed image
     * @param int $maxHeight Maximum height of the processed image
     * @param string $rename New file name for the processed image file to be saved.
     * @return bool|string Returns the generated image file name. Return false if failed.
     */
    public function waterMark($file, $text, $maxWidth, $maxHeight, $rename=''){
        $file = $this->uploadPath . $file;
        $imginfo = $this->getInfo($file);

        if($rename=='')
            $newName = substr($imginfo['name'], 0, strrpos($imginfo['name'], '.')) . $this->waterSuffix .'.'. $this->generatedType;
        else
            $newName = $rename .'.'. $this->generatedType;

        //create image object based on the image file type, gif, jpeg or png
        $this->createImageObject($img, $imginfo['type'], $file);

        if(!$img) return false;

        $width  = ($maxWidth > $imageInfo['width']) ? $imageInfo['width'] : $maxWidth;
        $height = ($maxHeight > $imageInfo['height']) ? $imageInfo['height'] : $maxHeight;
        $oriW	= $imageInfo['width'];
        $oriH	= $imageInfo['height'];

        if ($oriW*$width > $oriH*$height)
            $height = round($oriH*$width/$oriW);
        else
            $width = round($oriW*$height/$oriH);

        if (function_exists('imagecreatetruecolor')){
            $new = imagecreatetruecolor($width, $height);
            imagecopyresampled($new, $img, 0, 0, 0, 0, $width, $height, $imageInfo['width'], $imageInfo['height']);
        }
        else{
            $new = imagecreate($width, $height);
            imagecopyresampled($new, $img, 0, 0, 0, 0, $width, $height, $imageInfo['width'], $imageInfo['height']);
        }

        $white = imagecolorallocate($new, 255, 255, 255);
        $black = imagecolorallocate($new, 0, 0, 0);
        $alpha = imagecolorallocatealpha($new, 230, 230, 230, 40);

        imagefilledrectangle($new, 0, $height-26, $width, $height, $alpha);
        imagefilledrectangle($new, 13, $height-20, 15, $height-7, $black);
        imageTTFText($new, 4.9, 0, 20, $height-14, $black, $this->ttfFont, $text[0]);
        imageTTFText($new, 4.9, 0, 20, $height-6, $black, $this->ttfFont, $text[1]);

        if ($this->saveFile){
            if (file_exists($this->processPath . $newName))
                unlink($this->processPath . $newName);
            $this->generateImage($newImg, $this->processPath . $newName);
            imagedestroy($new);
            imagedestroy($img);
            return $this->processPath . $newName;
        }
        else{
            $this->generateImage($newImg);
            imagedestroy($new);
            imagedestroy($img);
        }

        return true;
    }

	/**
	 * Embed a watermark image onto a given image.
	 *
	 * @param string $file Image file name
	 * @param string $watermarkImgPath Full path to watermark image
	 * @param string|int $posX Position of watermark horizontally: left | middle | right  OR > 0 to position Xpx from left or < 0 to position Xpx from right
	 * @param string|int $posY Position of watermark vertically: top  | middle | bottom OR > 0 to position Xpx from top  or < 0 to position Xpx from bottom
	 * @param string $rename New file name for the processed image file to be saved
	 * @return bool|string Returns the generated image file name. Return false if failed
	 */
	public function waterMarkImage($file, $watermarkImgPath, $posX='right', $posY='bottom', $rename='') {
		$file = $this->uploadPath . $file;
        $imgInfo = $this->getInfo($file);
		$watermarkImgInfo = $this->getInfo($watermarkImgPath);

        if($rename=='')
            $newName = substr($imgInfo['name'], 0, strrpos($imgInfo['name'], '.')) . $this->waterSuffix .'.'. $this->generatedType;
        else
            $newName = $rename .'.'. $this->generatedType;

		$destX = 0;
		$destY = 0;

		if ($posX === 'left') {
			$destX = 0;
		} elseif ($posX === 'right') {
			$destX = $imgInfo['width'] - $watermarkImgInfo['width'];
		} elseif ($posX === 'middle') {
			$destX = ($imgInfo['width'] - $watermarkImgInfo['width']) / 2;
		} elseif ($posX > 0) {
			$destX = $posX;
		} elseif ($posX < 0) {
			$destX = $imgInfo['width'] - $watermarkImgInfo['width'] + $posX;
		}

		if ($posY === 'top') {
			$destY = 0;
		} elseif ($posY === 'bottom') {
			$destY = $imgInfo['height'] - $watermarkImgInfo['height'];
		} elseif ($posY === 'middle') {
			$destY = ($imgInfo['height'] - $watermarkImgInfo['height']) / 2;
		} elseif ($posY > 0) {
			$destY = $posY;
		} elseif ($posY < 0) {
			$destY = $imgInfo['height'] - $watermarkImgInfo['height'] + $posX;
		}

        //create image object based on the image file type, gif, jpeg or png
        $this->createImageObject($img, $imgInfo['type'], $file);
		$this->createImageObject($watermarkImg, $watermarkImgInfo['type'], $watermarkImgPath);

        if(!$img || !$watermarkImg) return false;

		if (function_exists('imagecreatetruecolor')){
            $new = imagecreatetruecolor($imgInfo['width'], $imgInfo['height']);
            imagecopyresampled($new, $img, 0, 0, 0, 0, $imgInfo['width'], $imgInfo['height'], $imgInfo['width'], $imgInfo['height']);
        }
        else{
            $new = imagecreate($imgInfo['width'], $imgInfo['height']);
            imagecopyresampled($new, $img, 0, 0, 0, 0, $imgInfo['width'], $imgInfo['height'], $imgInfo['width'], $imgInfo['height']);
        }
		imagedestroy($img);
		
		imagecopy($new, $watermarkImg, $destX, $destY, 0, 0, $watermarkImgInfo['width'], $watermarkImgInfo['height']);
		imagedestroy($watermarkImg);

		if ($this->saveFile){
            if (file_exists($this->processPath . $newName))
                unlink($this->processPath . $newName);
            $this->generateImage($new, $this->processPath . $newName);
			imagedestroy($new);
            return $this->processPath . $newName;
        }
        else{
            $this->generateImage($new);
			imagedestroy($new);
        }

        return true;
	}

	/**
	 * Centers an image in the middle of a container image. Useful when you want to align an image horizontally and vertically
	 * for example a landscape image in a square box.
	 *
	 * @param string $file Image file name
	 * @param integer $width The width of the new image
	 * @param integer $height The height of the new image
	 * @param array $bgcolor Array defining the background color array(RED, GREEN, BLUE) eg. (0, 255, 0) for bright green
	 * @param string $rename New file name for the processed image file to be saved
	 * @return bool|string Returns the generated image file name. Return false if failed
	 */
	public function centerImageInContrainer($file, $width, $height, $bgcolor, $rename='') {

		$file = $this->uploadPath . $file;
        $imginfo = $this->getInfo($file);

        if($rename=='')
            $newName = substr($imginfo['name'], 0, strrpos($imginfo['name'], '.')) . $this->thumbSuffix .'.'. $this->generatedType;
        else
            $newName = $rename .'.'. $this->generatedType;

		//create image object based on the image file type, gif, jpeg or png
        $this->createImageObject($img, $imginfo['type'], $file);
		if(!$img) return false;

        //For GD version 2.0.1 only
        if (function_exists('imagecreatetruecolor')){
            $newImg = imagecreatetruecolor($width, $height);
        } else {
            $newImg = imagecreate($width, $height);
        }

		$bgColor = imagecolorallocate($newImg, $bgcolor[0], $bgcolor[1], $bgcolor[2]);
		imagefilledrectangle($newImg, 0, 0, $width, $height, $bgColor);

		imagecopyresampled($newImg, $img, ($width-$imginfo['width'])/2, ($height-$imginfo['height'])/2, 0, 0, $imginfo['width'], $imginfo['height'], $imginfo['width'], $imginfo['height']);

		imagedestroy($img);

        if($this->saveFile){
            //delete if exist
            if(file_exists($this->processPath . $newName))
                unlink($this->processPath . $newName);
            $this->generateImage($newImg, $this->processPath . $newName);
            imagedestroy($newImg);
            return $this->processPath . $newName;
        }
        else{
            $this->generateImage($newImg);
            imagedestroy($newImg);
        }

        return true;
	}

    /**
     * Get the file name of an image's thumbnail.
     * @param string $file Image file name
     * @return string
     */
    public function getThumb($file){
        $thumbName = substr($file, 0, strrpos($file, '.')) . $this->thumbSuffix .'.'. $this->generatedType;
        $file = $this->processPath . $thumbName;
        if(!file_exists($file))
            return;
        return $file;
    }

    /**
     * Get the file name of an image's watermark.
     * @param string $file Image file name
     * @return string
     */
    public function getWaterMark($file){
        $markName = substr($file, 0, strrpos($file, ".")) . $this->waterSuffix .'.'. $this->generatedType;
        $file = $this->processPath . $markName;
        if (!file_exists($file))
            return;
        return $file;
    }

    /**
     * Tries to remove the images along with its thumbnails & watermarks
     *
     * @param string|array $file Image file name(s)
     * @return string
     */
    public function removeImage($file){
        if(is_array($file)){
            foreach($file as $f){
                $oriName   = $this->processPath . $f;
                $thumbName	= $this->processPath . substr($f, 0, strrpos($f, '.')) . $this->thumbSuffix .'.'. $this->generatedType;
                $markName  = $this->processPath . substr($f, 0, strrpos($f, '.')) . $this->waterSuffix .'.'. $this->generatedType;

                if(file_exists($thumbName))
                    unlink($thumbName);

                if(file_exists($markName))
                    unlink($markName);

                if(file_exists($oriName))
                    unlink($oriName);
            }
        }
        else{
            $oriName   = $this->processPath . $file;
            $thumbName	= $this->processPath . substr($file, 0, strrpos($file, '.')) . $this->thumbSuffix .'.'. $this->generatedType;
            $markName  = $this->processPath . substr($file, 0, strrpos($file, '.')) . $this->waterSuffix .'.'. $this->generatedType;

            if(file_exists($thumbName))
                unlink($thumbName);

            if(file_exists($markName))
                unlink($markName);

            if(file_exists($oriName))
                unlink($oriName);
        }
    }


    /**
     * Get the info of an image
     *
     * @param string $file Image file name
     * @return array Returns info in array consists of width, height, type, name & filesize.
     */
    public function getInfo($file){
        $data = getimagesize($file);
        $img['width'] = $data[0];
        $img['height'] = $data[1];
        $img['type'] = $data[2];
        $img['name'] = basename($file);
        $img['filesize'] = filesize($file);
        return $img;
    }

    /**
     * Save the uploaded image(s) in HTTP File Upload variables
     * 
     * @param string $filename The file field name in $_FILES HTTP File Upload variables
     * @param string $rename Rename the uploaded file (without extension)
     * @return string|array The file name of the uploaded image.
     */
    public function uploadImage($filename, $rename=''){
        $img = !empty($_FILES[$filename]) ? $_FILES[$filename] : null;
        if($img==Null)return;

		if (!file_exists($this->uploadPath)) {
			Doo::loadHelper('DooFile');
			$fileManager = new DooFile(0777);
			$fileManager->create($this->uploadPath);
		}

        if(is_array($img['name'])===False){
            $pic = strrpos($img['name'], '.');
            $ext = strtolower(substr($img['name'], $pic+1));

            if ($this->timeAsName){
                $newName = time().'-'.mt_rand(1000,9999) . '.' . $ext;
            }else{
                $newName = $img['name'];
            }

            if($rename=='')
                $imgPath = $this->uploadPath . $newName;
            else
                $imgPath = $this->uploadPath . $rename . '.' . $ext;

            if (move_uploaded_file($img['tmp_name'], $imgPath)){
                return ($rename=='')? $newName : $rename. '.' . $ext;
            }
        }
        else{
            $uploadImagesPath = array();
            foreach($img['error'] as $k=>$error){
                if(empty($img['name'][$k])) continue;
                if ($error == UPLOAD_ERR_OK) {
                   $pic = strrpos($img['name'][$k], '.');
                   $ext = strtolower(substr($img['name'][$k], $pic+1));

                   if($this->timeAsName){
                       $newName = time().'-'.mt_rand(1000,9999) . '_' . $k . '.' . $ext;
                   }else{
                       $newName = $img['name'][$k];
                   }

                   if($rename=='')
                       $imgPath = $this->uploadPath . $newName;
                   else
                       $imgPath = $this->uploadPath . $rename . '_' . $k . '.' . $ext;

                   if (move_uploaded_file($img['tmp_name'][$k], $imgPath)){
                       $uploadImagesPath[] = $newName;
                   }
                }else{
                   return false;
                }
            }
            return $uploadImagesPath;
        }
    }

    /**
     * Get the uploaded images' format type
     *
     * @param string $filename The file field name in $_FILES HTTP File Upload variables
     * @return string|array The image format type of the uploaded image.
     */
    public function getUploadFormat($filename){
        if(!empty($_FILES[$filename])){
            $type = $_FILES[$filename]['type'];
            if(is_array($type)===False){
                if(!empty($type))
                    return str_replace('image/', '', $type);
            }
            else{
                $typelist = array();
                foreach($type as $t){
                    $typelist[] = str_replace('image/', '', $t);
                }
                return $typelist;
            }
        }
    }

    /**
     * Checks if image mime type of the uploaded file(s) is in the allowed list
     * @param string $filename The file field name in $_FILES HTTP File Upload variables
     * @param array $allowType Allowed image format type. Default: JPEGs, GIFs and PNGs
     * @return bool Returns true if image mime type is in the allowed list.
     */
    public function checkImageType($filename, $allowType=array('jpg','jpeg','pjpeg','gif','png','x-png')){
        $type = $this->getUploadFormat($filename);
        if(is_array($type)===False)
            return in_array($type, $allowType);
        else{
            foreach($type as $t){
                if($t===Null || $t==='') continue;
                if(!in_array($t, $allowType)){
                    return false;
                }
            }
            return true;
        }
    }

    /**
     * Checks if image extension of the uploaded file(s) is in the allowed list.
     *
     * @param string $filename The file input field name in $_FILES HTTP File Upload variables
     * @param array $allowExt Allowed file extensions. Default: jpg, jpeg, gif, png
     * @return bool Returns true if file extension is in the allowed list.
     */
    public function checkImageExtension($filename, $allowExt=array('jpg','jpeg','gif','png')){
        if(!empty($_FILES[$filename])){
            $name = $_FILES[$filename]['name'];
            if(is_array($name)===False){
                $n = strrpos($name, '.');
                $ext = strtolower(substr($name, $n+1));
                return in_array($ext, $allowExt);
            }
            else{
                foreach($name as $nm){
                    $n = strrpos($nm, '.');
                    $ext = strtolower(substr($nm, $n+1));
                    if(!in_array($ext, $allowExt)){
                        return false;
                    }
                }
                return true;
            }
        }
    }

    /**
     * Checks if image file size does not exceed the max file size allowed.
     *
     * @param string $filename The file input field name in $_FILES HTTP File Upload variables
     * @param int $maxSize Allowed max file size in kilo bytes.
     * @return bool Returns true if file size does not exceed the max file size allowed.
     */
    public function checkImageSize($filename, $maxSize){
        if(!empty($_FILES[$filename])){
            $size = $_FILES[$filename]['size'];
            if(is_array($size)===False){
                if(($size/1024)>$maxSize){
                    return false;
                }
            }
            else{
                foreach($size as $s){
                    if(($s/1024)>$maxSize){
                        return false;
                    }
                }
            }
            return true;
        }
    }

}
