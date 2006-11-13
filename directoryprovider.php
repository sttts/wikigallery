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

require_once( "thumb.php" );

class GalleryDirectoryProvider extends GalleryProvider {
  var $directoryBasePath;
  var $thumbProvider;

  function GalleryDirectoryProvider( $group, $basePath, $webPath, $thumbProvider=false ) {
    global $WikiGallery_CacheBasePath, $WikiGallery_CacheWebPath, 
      $WikiGallery_ScaleMethod;
				
    $this->GalleryProvider( $group );

    $this->directoryBasePath = $basePath;
    $this->directoryWebPath = $webPath;
    if( $thumbProvider )
      $this->thumbProvider =& $thumbProvider;
    else
      $this->thumbProvider =& 
	new InternalThumbProvider( $group, 
				   $WikiGallery_CacheBasePath, 
				   $WikiGallery_CacheWebPath, 
				   $basePath,
				   $webPath,
				   $WikiGallery_ScaleMethod 
				   );
  }

  function pageNameToFileNameImpl( $basePath, $pageName ) {
    global $WikiGallery_PathDelimiter;

    // empty pageName?
    if( $pageName=="" ) return "";

    //echo "Will try to find filename for $pageName<br/>";
    $lastComponent = WikiGalleryLastComponent( $pageName, $WikiGallery_PathDelimiter );
    $head = WikiGalleryLeadingComponents( $pageName, $WikiGallery_PathDelimiter );
    if( !$head ) {
      $path = "";
      $pathslash = "";
    } else {
      $path = $this->pageNameToFileNameImpl( $basePath, $head );
      if( $path==-1 ) return -1;
      $pathslash = "$path/";
    }

    if ( !is_dir( $basePath . "/" . $path) ) return -1;
    $pwd = opendir( $basePath . "/" . $path);
    $found = "";
    while( ($file=readdir($pwd))!=false ) {
      $filePageName = fileNameToPageName( $file );
      if( ucfirst($lastComponent)==$filePageName ) {
	//echo "Found $file==$lastComponent<br/>";
	$found = $file;
	break;
      } else {
	//echo "$file => $filePageName!=$lastComponent<br/>";
      }
    }
    closedir($pwd);

    if( $found=="" )
      return -1;
    else
      return $pathslash . $found;
  }

  function pageNameToFileName( $pageName ) {
    return $this->pageNameToFileNameImpl( $this->directoryBasePath, $pageName );
  }

  function getFilenames( $path, $albums=false ) {
    global $WikiGallery_SortByDate, $WikiGallery_SortBackwards,
      $WikiGallery_AlbumsSortByDate, $WikiGallery_AlbumsSortBackwards,
      $WikiGallery_ImgExts;

    // prepend directory
    $path = $this->directoryBasePath . $path;

    // normalize path
    $path = preg_replace( "/\\/$/", '', $path );
    if( $path=="" )
      $pathslash = "";
    else
      $pathslash = "$path/";
    
    // dir?
    if( !is_dir( $path ) ) {
      #echo "$path is no directory or doesn't exist";
      return;
    }
    
    // iterate over files
    $pwd_handle = opendir($path);
    $i = 100;
    while ( ($file = readdir($pwd_handle))!=false ) {
      
      // ignore some of them
      if( $file=='.' || $file=='..' ) { continue; }
      if( is_file($pathslash.$file) ) {
	if (strpos(stripslashes(rawurldecode($file)), '..')
	    || ($file[0] == '.' && $file[1] == '.')) {
	  //echo 'Updir ("..") is not allowed in a filename.';
	  return;
	}
      }
      
      if( $file[0] == '.') { continue; }
      
      // and include images
      if( is_readable($pathslash.$file) &&
	  (($albums==false && is_file($pathslash.$file) && eregi($WikiGallery_ImgExts, $file)) ||
	   ($albums==true && is_dir($pathslash.$file)))) {
	$mod_date = filemtime($pathslash.$file).$i;
	$img_files[$mod_date] = $file;
	$i++;
      } else {
	//echo "ignoring $file";
      }
    }
    closedir($pwd_handle);
    
    // sort them
    if( isset($img_files) ) {
      if( ($albums==false && $WikiGallery_SortByDate==TRUE) ||
	  ($albums==true && $WikiGallery_AlbumsSortByDate==TRUE) ) {
	ksort($img_files);
      } else {
	natcasesort($img_files);
      }
      foreach($img_files as $img) {
	$sorted_files[]=$img;
      }
      if ( ($albums==false && $WikiGallery_SortBackwards==TRUE) ||
	   ($albums==true && $WikiGallery_AlbumsSortBackwards==TRUE) ) {
	return (array_reverse($sorted_files));
      } else {
	return $sorted_files;
      }
    } 
  }

  function isAlbum( $path ) {
    return is_dir( $this->directoryBasePath . $path );
  }

  function isPicture( $path ) {
    return is_file( $this->directoryBasePath . $path );
  }

  function pictureTime( $path ) {
    return filemtime( $this->directoryBasePath . $path );
  }

  function thumb( $path, $size ) {
    return $this->thumbProvider->thumbUrl( $path, $size );
  }
}
