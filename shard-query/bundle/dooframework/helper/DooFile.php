<?php
/**
 * DooFile class file.
 * @package doo.helper
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009-2010 Leng Sheng Hong
 * @license http://www.doophp.com/license
 * @since 1.3
 */
 
/**
 * Provides functions for managing file system
 */
class DooFile {

    const LIST_FILE = 'file';
    const LIST_FOLDER = 'folder';

    public $chmod;

    public function  __construct($chmod=0777) {
        $this->chmod = $chmod;
    }

    /**
     * Delete contents in a folder recursively
     * @param string $dir Path of the folder to be deleted
     * @return int Total of deleted files/folders
     */
	public function purgeContent($dir){
        $totalDel = 0;
		$handle = opendir($dir);

		while (false !== ($file = readdir($handle))){
			if ($file != '.' && $file != '..'){
				if (is_dir($dir.$file)){
					$totalDel += $this->purgeContent($dir.$file.'/');
					if( rmdir($dir.$file) )
                        $totalDel++;
				}else{
					if( unlink($dir.$file) )
                        $totalDel++;
				}
			}
		}
		closedir($handle);
        return $totalDel;
	}

    /**
     * Delete a folder (and all files and folders below it)
     * @param string $path Path to folder to be deleted
     * @param bool $deleteSelf true if the folder should be deleted. false if just its contents.
     * @return int|bool Returns the total of deleted files/folder. Returns false if delete failed
     */
	public function delete($path, $deleteSelf=true){

		if (file_exists($path)) {
			//delete all sub folder/files under, then delete the folder itself
			if(is_dir($path)){
				if($path[strlen($path)-1] != '/' && $path[strlen($path)-1] != '\\' ){
					$path .= DIRECTORY_SEPARATOR;
					$path = str_replace('\\', '/', $path);
				}
				if($total = $this->purgeContent($path)){
					if($deleteSelf)
						if($t = rmdir($path))
							return $total + $t;
					return $total;
				}
				else if($deleteSelf){
					return rmdir($path);
				}
				return false;
			}
			else{
				return unlink($path);
			}
		}
    }


	/**
	 * If the folder does not exist creates it (recursively)
	 * @param string $path Path to folder/file to be created
	 * @param mixed $content Content to be written to the file
	 * @param string $writeFileMode Mode to write the file
     * @return bool Returns true if file/folder created
	 */
	public function create($path, $content=null, $writeFileMode='w+') {
        //create file if content not empty
		if (!empty($content)) {
            if(strpos($path, '/')!==false || strpos($path, '\\')!==false){
                $path = str_replace('\\', '/', $path);
                $filename = $path;
                $path = explode('/', $path);
                array_splice($path, sizeof($path)-1);

                $path = implode('/', $path);
                if($path[strlen($path)-1] != '/'){
                    $path .= '/';
                }
            }else{
                $filename = $path;
            }

            if($filename!=$path && !file_exists($path))
                mkdir($path, $this->chmod, true);
            $fp = fopen($filename, $writeFileMode);
            $rs = fwrite($fp, $content);
            fclose($fp);
            
            return ($rs>0);
		}else{
			if (!file_exists($path)) {
				return mkdir($path, $this->chmod, true);
			} else {
				return true;
			}
        }
	}

    /**
     * Move/rename a file/folder
     * @param string $from Original path of the folder/file
     * @param string $to Destination path of the folder/file
     * @return bool Returns true if file/folder created
     */
    public function move($from, $to) {
        if(strpos($to, '/')!==false || strpos($to, '\\')!==false){
            $path = str_replace('\\', '/', $to);
            $path = explode('/', $path);
            array_splice($path, sizeof($path)-1);

            $path = implode('/', $path);
            if($path[strlen($path)-1] != '/'){
                $path .= '/';
            }
            if(!file_exists($path))
                mkdir($path, $this->chmod, true);
        }
        
        return rename($from, $to);
    }

    /**
     * Copy a file/folder to a destination
     * @param string $from Original path of the folder/file
     * @param string $to Destination path of the folder/file
	 * @param array $exclude An array of file and folder names to be excluded from a copy
     * @return bool|int Returns true if file copied. If $from is a folder, returns the number of files/folders copied
     */
    public function copy($from, $to, $exclude=array()) {
        if(is_dir($from)){
            if($to[strlen($to)-1] != '/' && $to[strlen($to)-1] != '\\' ){
                $to .= DIRECTORY_SEPARATOR;
                $to = str_replace('\\', '/', $to);
            }
            if($from[strlen($from)-1] != '/' && $from[strlen($from)-1] != '\\' ){
                $from .= DIRECTORY_SEPARATOR;
                $from = str_replace('\\', '/', $from);
            }
            if(!file_exists($to))
                mkdir($to, $this->chmod, true);

            return $this->copyContent($from, $to, $exclude);
        }else{
            if(strpos($to, '/')!==false || strpos($to, '\\')!==false){
                $path = str_replace('\\', '/', $to);
                $path = explode('/', $path);
                array_splice($path, sizeof($path)-1);

                $path = implode('/', $path);
                if($path[strlen($path)-1] != '/'){
                    $path .= '/';
                }

                if(!file_exists($path))
                    mkdir($path, $this->chmod, true);
            }
            return copy($from, $to);
        }
    }

    /**
     * Copy contents in a folder recursively
     * @param string $dir Path of the folder to be copied
     * @param string $to Destination path
	 * @param array $exclude An array of file and folder names to be excluded from a copy
     * @return int Total of files/folders copied
     */
	public function copyContent($dir, $to, $exclude=array()){
        $totalCopy = 0;
		$handle = opendir($dir);

		while(false !== ($file = readdir($handle))){
			if($file != '.' && $file != '..' && !in_array($file, $exclude)){

                if (is_dir($dir.$file)){
                    if(!file_exists($to.$file))
                        mkdir($to.$file, $this->chmod, true);

					$totalCopy += $this->copyContent($dir.$file.'/', $to.$file.'/', $exclude);
				}else{
					if( copy($dir.$file, $to.$file) )
                        $totalCopy++;
				}
			}
		}
		closedir($handle);
        return $totalCopy;
	}


    /**
     * Get the space used up by a folder recursively.
     * @param string $dir Directory path.
     * @param string $unit Case insensitive units: B, KB, MB, GB or TB
     * @param int $precision 
     * @return float total space used up by the folder (KB)
     */
    public function getSize($dir, $unit='KB', $precision=2){
        if(!is_dir($dir)) return filesize($dir);
        $dir = str_replace('\\', '/', $dir);
        if($dir[strlen($dir)-1] != '/'){
            $dir .= '/';
        }

        $totalSize = 0;
		$handle = opendir($dir);

		while(false !== ($file = readdir($handle))){
			if($file != '.' && $file != '..'){
                if (is_dir($dir.$file)){
					$totalSize += $this->getSize($dir.$file, false);
				}else{
                    $totalSize += filesize($dir.$file);
				}
			}
		}
		closedir($handle);
        return self::formatBytes($totalSize, $unit, $precision);
    }

    /**
     * Convert bytes into KB, MB, GB or TB.
     * @param int $bytes
     * @param string $unit Case insensitive units: B, KB, MB, GB or TB OR false if not to format the size
     * @param int $precision
     * @return float
     */
    public static function formatBytes($bytes, $unit='KB', $precision=2) {
		if ($unit === false) {
			return $bytes;
		}
        $unit = strtoupper($unit);
        $unitPow = array('B'=>0, 'KB'=>1, 'MB'=>2, 'GB'=>3, 'TB'=>4);
        $bytes /= pow(1024, $unitPow[$unit]);
        return round($bytes, $precision);
    }

    /**
     * Get a list of files with its path in a directory (recursively)
     * @param string $path
     * @return array 
     */
    public static function getFilePathList($path){
        $path = str_replace('\\', '/', $path);
        if($path[strlen($path)-1] != '/'){
            $path .= '/';
        }
        
        $handle = opendir($path);
        $rs = array();

        while (false !== ($file = readdir($handle))){
            if ($file != '.' && $file != '..' && $file!='.svn'){
                if (is_dir($path.$file)===true){
                        $rs = array_merge($rs, self::getFilePathList($path.$file.'/'));
                }else{
                    $rs[$file] = $path.$file;
                }
            }
        }
        closedir($handle);
        return $rs;
    }    
    
    /**
     * Get a list of folders or files or both in a given path.
     *
     * @param string $path Path to get the list of files/folders
     * @param string $listOnly List only files or folders. Use value DooFile::LIST_FILE or DooFile::LIST_FOLDER
     * @param string $unit Unit for the size of files. Case insensitive units: B, KB, MB, GB or TB
     * @param int $precision Number of decimal digits to round the file size to
     * @return array Returns an assoc array with keys: name(file name), path(full path to file/folder), folder(boolean), extension, type, size(KB)
     */
	public function getList($path, $listOnly=null, $unit='B', $precision=2){
        $path = str_replace('\\', '/', $path);
        if($path[strlen($path)-1] != '/'){
            $path .= '/';
        }

		$filetype = array('.', '..');
		$name = array();

		$dir = opendir($path);
		if ($dir === false ) {
			return false;
		}

		while( $file = readdir($dir) ){
			if( !in_array(substr($file, -1, strlen($file)), $filetype) && !in_array(substr($file, -2, strlen($file)), $filetype) ){
				$name[] = $path . $file;
			}
		}
		closedir($dir);

		if(count($name)==0)return false;

        if(!function_exists('mime_content_type')) {
            function mime_content_type($filename) {
                $mime_types = array(
                    'txt' => 'text/plain',
                    'htm' => 'text/html',
                    'html' => 'text/html',
                    'php' => 'text/html',
                    'css' => 'text/css',
                    'js' => 'application/javascript',
                    'json' => 'application/json',
                    'xml' => 'application/xml',
                    'swf' => 'application/x-shockwave-flash',
                    'flv' => 'video/x-flv',
                    'sql' => 'text/x-sql',

                    // images
                    'png' => 'image/png',
                    'jpe' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'jpg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'bmp' => 'image/bmp',
                    'ico' => 'image/vnd.microsoft.icon',
                    'tiff' => 'image/tiff',
                    'tif' => 'image/tiff',
                    'svg' => 'image/svg+xml',
                    'svgz' => 'image/svg+xml',

                    // archives
                    'zip' => 'application/zip',
                    'rar' => 'application/x-rar-compressed',
                    'exe' => 'application/x-msdownload',
                    'msi' => 'application/x-msdownload',
                    'cab' => 'application/vnd.ms-cab-compressed',

                    // audio/video
                    'mp3' => 'audio/mpeg',
                    'qt' => 'video/quicktime',
                    'mov' => 'video/quicktime',

                    // adobe
                    'pdf' => 'application/pdf',
                    'psd' => 'image/vnd.adobe.photoshop',
                    'ai' => 'application/postscript',
                    'eps' => 'application/postscript',
                    'ps' => 'application/postscript',

                    // ms office
                    'doc' => 'application/msword',
                    'rtf' => 'application/rtf',
                    'xls' => 'application/vnd.ms-excel',
                    'ppt' => 'application/vnd.ms-powerpoint',

                    // open office
                    'odt' => 'application/vnd.oasis.opendocument.text',
                    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
                );

                $f = explode('.',$filename);
                $ext = strtolower(array_pop($f));
                if (array_key_exists($ext, $mime_types)) {
                    return $mime_types[$ext];
                }
                elseif (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME);
                    $mimetype = finfo_file($finfo, $filename);
                    finfo_close($finfo);
                    return $mimetype;
                }
                else {
                    return 'application/octet-stream';
                }
            }
        }

		$fileInfo=array();
		foreach($name as $key=>$val){
            if($listOnly==DooFile::LIST_FILE){
                if(is_dir($val)) continue;
            }
			if ($listOnly==DooFile::LIST_FOLDER) {
				if(!is_dir($val)) continue;
			}
			$filename = basename($val);
			$ext = $this->getFileExtensionFromPath($val, true);

            if(!is_dir($val)){
                $fileInfo[] = array('name' => $filename,
                                    'path' => $val,
                                    'folder' => is_dir($val),
                                    'extension' => $ext,
                                    'type' => mime_content_type($val),
                                    'size' => $this->formatBytes(filesize($val), $unit, $precision)
                                );
            }else{
                $fileInfo[] = array('name' => $filename,
                                    'path' => $val,
                                    'folder' => is_dir($val));
            }
		}
		return $fileInfo;
	}


    /**
     * Save the uploaded file(s) in HTTP File Upload variables
     *
     * @param string $uploadPath Path to save the uploaded file(s)
     * @param string $filename The file input field name in $_FILES HTTP File Upload variables
     * @param string $rename Rename the uploaded file (without extension)
     * @return string|array The file name of the uploaded file.
     */
    public function upload($uploadPath, $filename, $rename=''){
        $file = !empty($_FILES[$filename]) ? $_FILES[$filename] : null;
        if($file==Null)return;

		if (!file_exists($uploadPath)) {
			$this->create($uploadPath);
		}

        if(is_array($file['name'])===False){
			$ext = $this->getFileExtensionFromPath($file['name']);

            if($rename=='')
                $newName = time().'-'.mt_rand(1000,9999) . '.' . $ext;
            else
                $newName = $rename . '.' . $ext;

			$filePath = $uploadPath . $newName;

            if (move_uploaded_file($file['tmp_name'], $filePath)){
                return $newName;
            }
        }
        else{
			if (!file_exists($uploadPath)) {
				$this->create($uploadPath);
			}
            $uploadedPath = array();
            foreach($file['error'] as $k=>$error){
                if(empty($file['name'][$k])) continue;
                if ($error == UPLOAD_ERR_OK) {
				   $ext = $this->getFileExtensionFromPath($file['name'][$k]);

                   if($rename=='')
                       $newName = time().'-'.mt_rand(1000,9999) . '_' . $k . '.' . $ext;
                   else
                       $newName = $rename . '_' . $k . '.' . $ext;

				   $filePath = $uploadPath . $newName;

                   if (move_uploaded_file($file['tmp_name'][$k], $filePath)){
                       $uploadedPath[] = $newName;
                   }
                }else{
                   return false;
                }
            }
            return $uploadedPath;
        }
    }

    /**
     * Get the uploaded files' type
     *
     * @param string $filename The file field name in $_FILES HTTP File Upload variables
     * @return string|array The image format type of the uploaded image.
     */
    public function getUploadFormat($filename){
        if(!empty($_FILES[$filename])){
            $type = $_FILES[$filename]['type'];
            if(is_array($type)===False){
                if(!empty($type)){
                    return $type;
                }
            }
            else{
                $typelist = array();
                foreach($type as $t){
                    $typelist[] = $t;
                }
                return $typelist;
            }
        }
    }

    /**
     * Checks if file mime type of the uploaded file(s) is in the allowed list
     *
     * @param string $filename The file input field name in $_FILES HTTP File Upload variables
     * @param array $allowType Allowed file type.
     * @return bool Returns true if file mime type is in the allowed list.
     */
    public function checkFileType($filename, $allowType){
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
     * Checks if file extension of the uploaded file(s) is in the allowed list.
     *
     * @param string $filename The file input field name in $_FILES HTTP File Upload variables
     * @param array $allowExt Allowed file extensions.
     * @return bool Returns true if file extension is in the allowed list.
     */
    public function checkFileExtension($filename, $allowExt){
        if(!empty($_FILES[$filename])){
            $name = $_FILES[$filename]['name'];
            if(is_array($name)===false){
                $ext = $this->getFileExtensionFromPath($name);
                return in_array($ext, $allowExt);
            }
            else{
                foreach($name as $nm){
					$ext = $this->getFileExtensionFromPath($nm);
                    if(!in_array($ext, $allowExt)){
                        return false;
                    }
                }
                return true;
            }
        }
    }

    /**
     * Checks if file size does not exceed the max file size allowed.
     *
     * @param string $filename The file input field name in $_FILES HTTP File Upload variables
     * @param int $maxSize Allowed max file size in kilo bytes.
     * @return bool Returns true if file size does not exceed the max file size allowed.
     */
    public function checkFileSize($filename, $maxSize){
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

	/**
	 * Reads the contents of a given file
	 * @param string $fullFilePath Full path to file whose contents should be read
	 * @return string|bool Returns file contents or false if file not found
	 */
	public function readFileContents($fullFilePath, $flags = 0, resource $context = null, $offset = -1, $maxlen = null) {
		if (file_exists($fullFilePath)) {
			if ($maxlen !== null)
				return file_get_contents($fullFilePath, $flags, $context, $offset, $maxlen);
			else
				return file_get_contents($fullFilePath, $flags, $context, $offset);
		} else {
			return false;
		}
	}

	/**
	 * Extracts the file extension (characters following last '.' in string) from a file path.
	 * @param string $filePath Full path or filename including extension to be extracted
	 * @param bool $toLowerCase Should the extension be converted to lower case ?
	 * @return string|Returns the file extension (characters following last . in string)
	 */
	public function getFileExtensionFromPath($path, $toLowerCase = false) {
		$ext = substr($path, strrpos($path, '.') + 1);
		return ($toLowerCase == true) ? strtolower($ext) : $ext;
	}

}
