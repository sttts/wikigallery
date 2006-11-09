<?php if (!defined('PmWiki')) exit();
/*
 * WikiGallery - automatic easy to use gallery extension for PmWiki
 * (c) 2006 Stefan Schimanski <sts@1stein.org>
 *
 * Ideas from SimpleGallery by Bram Brambring <http://www.brambring.nl>
 * Some code from Qdig by Hagan Fox <http://qdig.sourceforge.net/>
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

function WikiGalleryPhpThumb( $path, $size ) {
  global $WikiGallery_PhpThumb;
  if( $size==0 )
    return "$WikiGallery_PhpThumb?src=" . urlencode($path) . " ";
  else
    return "$WikiGallery_PhpThumb?w=" . urlencode($size) . "&src=" . urlencode($path) . " ";
}

function WikiGalleryInternalThumb( $path, $size ) {
  global $WikiGallery_UseAuthorization, $WikiGallery_CacheWebPath,
    $WikiGallery_PicturesWebPath, $WikiGallery_CacheBasePath,
    $pagename;
  
  // we can use a direct url to the file if authorization is not needed
  if( !$WikiGallery_UseAuthorization ) {
    if( $size==0 )
      // give direct url to the original file
      return 'http://'.$_SERVER['HTTP_HOST']."/".preg_replace("/ /","%20", $WikiGallery_PicturesWebPath . $path);
    else {
      // give direct url to the cache file
      $thumbnail = WikiGalleryInternalThumbCacheName( $path, $size );
      if( is_file( $WikiGallery_CacheBasePath . "/" . $thumbnail ) ) {
	// touch it so that it is not purged during cleanup
	touch( $WikiGallery_CacheBasePath . "/" . $thumbnail );
	
	// ok, thumbnail exists, otherwise fall through
	return 'http://'.$_SERVER['HTTP_HOST']."/".preg_replace("/ /","%20",$WikiGallery_CacheWebPath . $thumbnail);
      }
    }
  }

  $url = MakeLink( $pagename, $pagename, NULL, NULL, "\$LinkUrl" );
  if( $size==0 )
    return $url . '?action=thumbnail&image=' . urlencode($path) . ' ';
  else
    return $url . '?action=thumbnail&width=' . $size . '&image=' . urlencode($path) . ' ';
}

function WikiGalleryInternalThumbCacheName( $path, $width=0, $height=0 ) {
  global $WikiGallery_CacheBasePath;
  if( $width!=0 || $height!=0 )
    $size=intval($width)."x".intval($height);
  else
    $size = "original";

  $ext = substr( strrchr( $path, "." ), 1 );
  return $path . "/" . $size . "." . $ext;
}

function WikiGalleryScaleGD( $original, $thumb, $width, $height ) {
  global $WikiGallery_HighQualityResize;

  // libgd2 installed?
  $info = @gd_info();
  if( !$info ) return;
  $version = ereg_replace('[[:alpha:][:space:]()]+', '', $info['GD Version']);
  if( !$version>=2 ) return;

  // get file format
  $format=WikiGalleryMimeType( $original );
  if( !$format ) return;
  
  // get current size
  list($origWidth, $origHeight) = getimagesize($original);

  // compute new height
  if( $width==0 && $height==0 ) { $width = $origWidth; $height = $origHeight; }
  else if( $width==0 ) $width = $origWidth*$height/$origHeight;
  else if( $height==0 ) $height = $origHeight*$width/$origWidth;

  // load image
  switch( $format ) {
  case 'image/jpeg':
    $source = imagecreatefromjpeg($original);
    break;
  case 'image/gif':
    $source = imagecreatefromgif($original);
    break;
  case 'image/png':
    $source = imagecreatefrompng($original);
    break;
  }

  if( $source ) {
    // scale
    $dest = imagecreatetruecolor( $width,$height );
    imagealphablending( $dest, false );
    if( $WikiGallery_HighQualityResize )
      imagecopyresampled($dest, $source, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
    else
      imagecopyresized($dest, $source, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);

    // store image
    @imagejpeg($dest, $thumb);
  }
}

function WikiGalleryScaleIM( $original, $thumb, $width, $height ) {
  global $WikiGallery_ImageMagickPath, $WikiGallery_HighQualityResize;

  // get size
  if( $width==0 ) $size="x$height";
  elseif( $height==0 ) $size=$width."x";
  else $size=$width."x".$height;
 
  // call imagemagick's convert to scale
  if( $WikiGallery_HighQualityResize )
    $command = $WikiGallery_ImageMagickPath . "convert -size $size -resize $size +profile \"*\" " . 
      escapeshellarg($original) . " " . escapeshellarg($thumb);
  else
    $command = $WikiGallery_ImageMagickPath . "convert -scale $size " . 
      escapeshellarg($original) . " " . escapeshellarg($thumb);
  @system( $command );
}

function WikiGalleryScale( $original, $thumb, $width, $height ) {
  global $WikiGallery_ScaleMethod;
  if( !is_file( $thumb ) && is_file( $original ) ) {
    // which method to use?
    $im = false;
    $gd = false;
    if( $WikiGallery_ScaleMethod=="auto" ) { $im = true; $gd = true; }
    else if( $WikiGallery_ScaleMethod=="imagemagick" ) { $im = true; }
    else if( $WikiGallery_ScaleMethod=="libgd2" ) { $gd = true; }
    
    // try libgd2
    if( $im && !is_file( $thumb ) ) WikiGalleryScaleIM( $original, $thumb, $width, $height );
    if( $gd && !is_file( $thumb ) ) WikiGalleryScaleGD( $original, $thumb, $width, $height );
    
    // did one work?
    if( !is_file( $thumb ) ) {
      Abort("Unable to generate thumbnail, check whether imagemagick is installed or your PHP support libgd2.<br>");
    }
  }
}

$HandleActions["thumbnail"] = 'WikiGalleryThumbnail';
function WikiGalleryThumbnail( $pagename, $auth = "read" ) {
  global $WikiGallery_PicturesBasePath, $WikiGallery_CacheBasePath, $WikiGallery_UseAuthorization,
    $WikiGallery_DefaultGroup;

  // get filename
  if( !isset( $_GET["image"] ) ) Abort('no image given');
  if( !isset( $_GET["group"] ) ) $group = $WikiGallery_DefaultGroup;
  else $group = $_GET["group"];
  $path = WikiGallerySecurePath( urldecode($_GET["image"]) );
  $pagename = fileNameToPageName( $path );

  // exists?
  $original = $WikiGallery_PicturesBasePath . "/" . $path;
  if( !is_file( $original ) ) Abort('image doesn\'t exist');

  // check authorization
  if( $WikiGallery_UseAuthorization ) {
    $page = RetrieveAuthPage($pagename, $auth, true, READPAGE_CURRENT);
    if (!$page) Abort('?cannot read $pagename');
    PCache($pagename,$page);
  }

  // get size
  $width = intval(@$_GET["width"]);
  $height = intval(@$_GET["height"]);
  if( $width<0 || $width>1600 ) $width=0;
  if( $height<0 || $height>1200 ) $height=0;

  // resize?
  if( $width==0 && $height==0 ) {
    $filename = $original;
  } else {
    // resize
    $filename = $WikiGallery_CacheBasePath . "/" . WikiGalleryInternalThumbCacheName( $path, $width, $height );
    if( !is_file( $filename ) ) {
      // make directory
      $dir = dirname($filename); 
      mkdirp($dir);

      // call ImageMagick to scale
      WikiGalleryScale( $original, $filename, $width, $height );
    } else {
      // touch it so that it is not purged during cleanup
      touch( $filename );      
    }
  }

  // output picture
  header( "Content-type: " . WikiGalleryMimeType( $original ) );
  header("Pragma: ");
  header("Expires: " . intval(time() + 3600) );
  header("Cache-Control: max-age=3600, must-revalidate");

  print file_get_contents( $filename );
  exit;
}

// cleanup of thumbnails
$WikiGallery_CleanupTimestamp = $WikiGallery_CacheBasePath . "/.cleanup-timestamp";
if( !file_exists( $WikiGallery_CleanupTimestamp ) ) {
  touch( $WikiGallery_CleanupTimestamp );
} else {
  // clean up, but not too often
  if( time()-filemtime($WikiGallery_CleanupTimestamp )>$WikiGallery_CleanupInterval ) {
//    WikiGalleryCleanupCache();
    register_shutdown_function( 'WikiGalleryCleanupCache', getcwd() );
  }
}

function WikiGalleryCleanupCache( $dir ) {
  global $WikiGallery_CacheBasePath, $WikiGallery_CleanupDelay,
    $WikiGallery_CleanupTimestamp, $WikiGallery_FindPath;

  // mark that cleanup was run
  chdir( $dir ); // work around for php bug. In shutdown functions the path is corrupted
  touch( $WikiGallery_CleanupTimestamp );

  // delete old files
  $command = $WikiGallery_FindPath . "find " . escapeshellarg($WikiGallery_CacheBasePath) . 
    " -type f -mtime +$WikiGallery_CleanupDelay -exec rm -f {} \\;";  
  if( @system( $command )<0 ) {
    Abort( "Error during cleanup of old thumbnails" );
  }

  // delete empty directories
  $command = $WikiGallery_FindPath . "find " . escapeshellarg($WikiGallery_CacheBasePath) .
    " -depth -type d -empty -exec rmdir {} \\;";
  if( @system( $command )<0 ) {
    Abort( "Error during cleanup of old thumbnails while deleting empty directories" );
  }
}
