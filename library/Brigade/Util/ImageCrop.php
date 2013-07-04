<?php

/**
 * Description of ImageCrop
 *
 * @author
 */
 ini_set("memory_limit", "64M");
class Brigade_Util_ImageCrop {

    //Constants
    //You can alter these options
    public $upload_dir = "/public/tmp/"; // The directory for the images to be saved in
    public $upload_path = "/public/tmp/"; // The path to where the image will be saved
    public $large_image_name = "resized_pic.jpg"; // New name of the large image
    public $thumb_image_name = "thumbnail_pic.jpg"; // New name of the thumbnail image
    public $max_file = "1148576"; // Approx 1MB
    public $max_width = "900"; // Max width allowed for the large image
    protected $thumb_width = "100"; // Width of thumbnail image
    protected $thumb_height = "100"; // Height of thumbnail image

    //Image functions
    public function resizeImage($image, $width, $height, $scale, $type = "jpeg") {
		$type = strtolower($type);
        if ($type == "png") {
            $source = @imagecreatefrompng($image);
        } elseif ($type == "gif") {
            $source = @imagecreatefromgif($image);
        } elseif ($type == "jpeg" || $type == 'jpg') {
            $source = @imagecreatefromjpeg($image);
        }
        if (!$source) {
            return false;
        }

        $newImageWidth = floor($width * $scale);
        $newImageHeight = floor($height * $scale);
        $newImage = imagecreatetruecolor($newImageWidth,$newImageHeight);

        imagecopyresampled($newImage, $source, 0, 0, 0, 0, $newImageWidth, $newImageHeight, $width, $height) or die('error');
		imagejpeg($newImage, $image);

        chmod($image, 0777);
        return $image;

    }

    public function resizeThumbnailImage($thumb_image_name, $image, $width, $height, $start_width, $start_height, $scale, $type = "jpeg", $resize_logo = false) {
        $type = strtolower($type);
        if ($type == "png") {
            $source = imagecreatefrompng($image);
        } elseif ($type == "gif") {
            $source = imagecreatefromgif($image);
        } elseif ($type == "jpeg" || $type == "jpg") {
            $source = imagecreatefromjpeg($image);
        }

        if ($resize_logo) {
            $max_width = 140;
            $max_height = 140;
			if($height > $max_height) {
            	$ratioh = $max_height/$height;
			} else {
				$ratioh = 1;
			}
			if($width > $max_width) {
	            $ratiow = $max_width/$width;
			} else {
				$ratiow = 1;
			}
            $ratio = min($ratioh, $ratiow);
            // New dimensions
            $newImageWidth = intval($ratio*$width);
            $newImageHeight = intval($ratio*$height); 
        } else {
            $newImageWidth = floor($width * $scale);
            $newImageHeight = floor($height * $scale);
        }
        $newImage = imagecreatetruecolor($newImageWidth,$newImageHeight);

        imagecopyresampled($newImage,$source,0,0,$start_width,$start_height,$newImageWidth,$newImageHeight,$width,$height);
        imagejpeg($newImage, $thumb_image_name);

        chmod($thumb_image_name, 0777);
        return $thumb_image_name;
    }

    public function getHeight($image) {
        $sizes = getimagesize($image);
        $height = $sizes[1];
        return $height;
    }

    public function getWidth($image) {
        $sizes = getimagesize($image);
        $width = $sizes[0];
        return $width;
    }

}
?>
