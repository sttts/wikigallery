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

# Paths
SDV($WikiGallery_PicturesWebPath, "pictures/"); // the path to the galleries (relative to the host url http://foo.com/)
SDV($WikiGallery_PicturesBasePath, $WikiGallery_PicturesWebPath); // the path to the galleries (on the filesystem, relative to pmwiki.php)

SDV($WikiGallery_CacheWebPath, "cache/"); // the path to the thumbnail cache (relative to the host url http://foo.com/)
SDV($WikiGallery_CacheBasePath, $WikiGallery_CacheWebPath); // the path to the thumbnail cache (on the filesystem, relative to pmwiki.php)

SDV($WikiGallery_ImageMagickPath, "/usr/bin/"); // absolute path to the ImageMagick binaries (mogrify, convert, ...)
SDV($WikiGallery_FindPath, ""); // path to the find command, usually /usr/bin/
SDV($WikiGallery_PhpThumb, WikiGalleryLeadingComponents($PubDirUrl) . "/phpThumb.php"  ); // the phpthumb script url

# How the wiki pages look like
SDV($WikiGallery_NavThumbnailColumns, 5); // odd number
SDV($WikiGallery_SortByDate, FALSE ); // otherwise alphabetical
SDV($WikiGallery_SortBackwards, FALSE );
SDV($WikiGallery_AlbumsSortByDate, TRUE ); // otherwise alphabetical
SDV($WikiGallery_AlbumsSortBackwards, TRUE );
SDV($WikiGallery_DefaultSlideshowDelay, 5 );

# Thumbnail generation
SDV($WikiGallery_ThumbFunction, 'WikiGalleryInternalThumb');  // use internal thumbnail routine. Set to 'WikiGalleryPhpThumb' for phpthumb
SDV($WikiGallery_ScaleMethod, "auto"); // either "auto", "imagemagick" or "libgd2"; "auto" means first imagemagick, then libgd2
SDV($WikiGallery_HighQualityResize, true); // use better quality (but slower) resize algorithms?
SDV($WikiGallery_UseAuthorization, true); // try to authorize for the page the picture/thumbnail is belonging to

# Clean up of thumbnail cache
SDV($WikiGallery_CleanupDelay, 7); // if nobody accessed a thumbnail for a week, purge it
SDV($WikiGallery_CleanupInterval, 3600); // cleanup once an hour

# Misc
SDV($WikiGallery_PathDelimiter, "-" ); // must be something valid in page names
SDV($WikiGallery_DefaultSize, 640);

################################################################################

# The markup:
Markup('(:gallerypicture width picture:)','><|',"/\\(:gallerypicture\\s([0-9]+)\\s([^:]*):\\)/e","WikiGalleryPicture('$1','$2')");
Markup('(:gallerypicturerandom width album:)','><|',"/\\(:gallerypicturerandom\\s([0-9]+)\\s([^:]*):\\)/e","WikiGalleryPicture('$1','$2',true)");

# Page variables
$FmtPV['$GalleryPicture'] = '$page["gallerypicture"] ? $page["gallerypicture"] : ""';
$FmtPV['$GalleryAlbum'] = '$page["galleryalbum"] ? $page["galleryalbum"] : ""';
$FmtPV['$GalleryOverview'] = '$page["galleryoverview"] ? $page["galleryoverview"] : ""';
$FmtPV['$GallerySize'] = '$GLOBALS["WikiGallery_Size"]';
$FmtPV['$GalleryParent'] = 'WikiGalleryParent("$name")';
$FmtPV['$GalleryNext'] = 'WikiGalleryNeightbourPicture("$name",1)';
$FmtPV['$GalleryNextNext'] = 'WikiGalleryNeightbourPicture("$name",2)';
$FmtPV['$GalleryPrev'] = 'WikiGalleryNeightbourPicture("$name",-1)';
$FmtPV['$GalleryPrevPrev'] = 'WikiGalleryNeightbourPicture("$name",-2)';

# default pages
$WikiLibDirs[] = new PageStore("$FarmD/cookbook/wikigallery/wikilib.d/\$FullName");

# which files?
$WikiGallery_ImgExts = '\.jpg$|\.jpeg$|\.jpe$|\.png$|\.bmp$';

# Replace .jpg in pagename such that using Group/Bla.jpg behaves like Group/Bla
$pagename = preg_replace ('/'.$WikiGallery_ImgExts.'$/','',$pagename );

# no trailing / 
$WikiGallery_PicturesBasePath = preg_replace( "/\\/$/", '', $WikiGallery_PicturesBasePath);
$WikiGallery_CacheBasePath = preg_replace( "/\\/$/", '', $WikiGallery_CacheBasePath);

# add Site.GalleryListTemplates to the list of fmt=#xyz options for pagelists
$FPLTemplatePageFmt[] = '{$SiteGroup}.GalleryListTemplates';

# new picture size?
$WikiGallery_Size = $WikiGallery_DefaultSize;
if( isset($_COOKIE["gallerysize"]) ) {
  $WikiGallery_Size = intval($_COOKIE["gallerysize"]);
}
if( isset($_GET["gallerysize"]) ) {
  $WikiGallery_Size = intval($_GET["gallerysize"]);
  setcookie("gallerysize", $WikiGallery_Size, time()+3600);
}
if( $WikiGallery_Size<0 || $WikiGallery_Size>10000 ) {
  $WikiGallery_Size = $WikiGallery_DefaultSize;
}
     
# slideshow?
$HandleActions["slideshow"] = "WikiGallerySlideshow";
function WikiGallerySlideshow( $pagename, $auth = 'read') {
  global $WikiGallery_DefaultSlideshowDelay, $HTMLHeaderFmt;

  # get delay from url
  if( isset($_Get["delay"]) )
    $delay = intval($_GET["delay"]);
  else
    $delay = $WikiGallery_DefaultSlideshowDelay;

  # find following picture
  $next = WikiGalleryNeightbourPicture( PageVar($pagename, '$Name'), 1 );
  $group = PageVar($pagename, '$Group');
  $nextpage = "$group.$next";

  # exists?
  if( $next && PageExists($nextpage) ) {
    # add refresh header 
    $url = MakeLink( $nextpage, $nextpage, NULL, NULL, "\$LinkUrl" );
    array_unshift( $HTMLHeaderFmt, "<meta http-equiv=\"refresh\" content=\"$delay; URL=$url?action=slideshow&delay=$delay\" />" );
  }
  
  return HandleBrowse( $pagename, $auth );
}

# filename <-> pagename conversion
function fileNameToPageName( $filename ) {
  global $WikiGallery_PathDelimiter,$WikiGallery_ImgExts;
  $pagename = preg_replace( array('/'.$WikiGallery_ImgExts.'$/i', "/[^a-zA-Z0-9\\/]/", "/\\//"),
			    array('', '', $WikiGallery_PathDelimiter), 
			    $filename );
  
  return ucfirst($pagename);
}

function pageNameToFileName( $basePath, $pageName ) {
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
    $path = pageNameToFileName( $basePath, $head );
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

function WikiGallerySecurePath( $path ) {
  # ignore .. and beginning / in path
  $path = preg_replace( "/\\.\\./", '', $path );
  $path = preg_replace( "/^\\//", '', $path );
  $path = preg_replace( "/\\/+/", '/', $path );
  return $path;
}

function WikiGalleryGetFilenames( $path, $albums=false ) {
  global $WikiGallery_SortByDate, $WikiGallery_SortBackwards,
    $WikiGallery_AlbumsSortByDate, $WikiGallery_AlbumsSortBackwards,
    $WikiGallery_ImgExts;

  # normalize path
  $path = preg_replace( "/\\/$/", '', $path );
  if( $path=="" )
    $pathslash = "";
  else
    $pathslash = "$path/";
    
  # dir?
  if( !is_dir( $path ) ) {
    //echo "$path is no directory or doesn't exist";
    return;
  }

  # iterate over files
  $pwd_handle = opendir($path);
  $i = 100;
  while ( ($file = readdir($pwd_handle))!=false ) {

    # ignore some of them
    if( $file=='.' || $file=='..' ) { continue; }
    if( is_file($pathslash.$file) ) {
      if (strpos(stripslashes(rawurldecode($file)), '..')
	  || ($file[0] == '.' && $file[1] == '.')) {
	//echo 'Updir ("..") is not allowed in a filename.';
	return;
      }
    }

    if( $file[0] == '.') { continue; }

    # and include images
    if( is_readable($pathslash.$file) &&
	(($albums==false && is_file($pathslash.$file) && eregi($WikiGallery_ImgExts, $file)) ||
	 ($albums==true && is_dir($pathslash.$file)))) {
	$mod_date = filemtime($pathslash.$file).$i;
	$img_files[$mod_date] = $file;
	$i++;
    } else {
      //     echo "ignoring $file";
    }
  }
  closedir($pwd_handle);

  # sort them
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

function WikiGalleryNeightbourPicture( $name, $delta, $count=-1 ) {
  global $WikiGallery_PicturesBasePath;

  // is it a file?
  $pagefile = pageNameToFileName( $WikiGallery_PicturesBasePath, $name );
  if( $pagefile==-1 || is_dir($WikiGallery_PicturesBasePath . "/" . $pagefile) )
    return false;

  # split pathes into filename and path
  $filename = WikiGalleryLastComponent( $pagefile );
  $path = WikiGalleryLeadingComponents( $pagefile );
  if( !$path ) {
    $slashpath = "";
    $pathslash = "";
  } else {
    $slashpath = "/" . $path;
    $pathslash = $path . "/";
  }

  # find position in the album of the current picture
  $pictures = WikiGalleryGetFilenames( $WikiGallery_PicturesBasePath . $slashpath );    
  $indexed = array();
  $i = 0;
  $thisIndex = -1;
  foreach( $pictures as $k ) {
    $indexed[$i] = $k;    
    if( $k==$filename ) {
      $thisIndex = $i;
    }
    $i++;
  }
  $picturesNum = $i;
    
  # found?
  if( $thisIndex==-1 ) return false;

  # get $count many neighbours from $thisIndex-$delta  
  if( $count==-1 ) {
    $i = $thisIndex+$delta;
    if( $i>=0 && $i<$picturesNum )
      return fileNameToPageName($pathslash . $indexed[$i]);
    else
      return "";
  } else {
    $i = max($thisIndex+$delta, 0);
    $ret = array();

    while( $i<$picturesNum && $i<$thisIndex+$delta+$count ) {
      $ret[] = fileNameToPageName($pathslash . $indexed[$i]);
      $i++;
    }
    
    return $ret;
  }
}

function WikiGalleryParent( $name ) {
  global $WikiGallery_PicturesBasePath;

  // is it a directory?
  $pagefile = pageNameToFileName( $WikiGallery_PicturesBasePath, $name );
  if( $pagefile==-1 && ! is_dir($WikiGallery_PicturesBasePath . "/" . $pagefile) )
    return false;

  # return parent page
  $path = WikiGalleryLeadingComponents($pagefile);
  if( $path ) return fileNameToPageName( $path );
  return "";
}

function WikiGalleryPicture( $size, $path, $random=false ) {
  global $WikiGallery_PicturesBasePath, $WikiGallery_ThumbFunction;
  $path = WikiGallerySecurePath( $path );  

  // random picture?
  if( $random ) {
    // get pictures
    $pictures = WikiGalleryGetFilenames( $WikiGallery_PicturesBasePath . "/" . $path );
    if( !$pictures ) return false;

    // choose random picture
    $num = rand(0, count($pictures)-1);
    $path .= "/" . $pictures[$num];
  }
  
  // return phpthumb url
  return $WikiGallery_ThumbFunction( $path, $size );
}

function WikiGalleryLastComponent( $path, $delimiter="/" ) {
  $slash = strrchr($path,$delimiter);
  if( $slash )
    return substr($slash, 1);
  else
    return $path;
}
	
function WikiGalleryLeadingComponents( $path, $delimiter="/" ) {
  $slash = strrchr($path,$delimiter);
  if( $slash )
    return substr($path, 0, strlen($path)-strlen($slash));
  else
    return "";
}

class GalleryPageStore extends PageStore {
  var $galleryGroup;
  var $dirfmt;

  function GalleryPageStore( $galleryGroup ) { 
    global $WikiGallery_PicturesBasePath;
    $this->PageStore( $WikiGallery_PicturesBasePath );
    $this->galleryGroup = $galleryGroup;
  }

  function pagefile($name) {
    global $WikiGallery_PicturesBasePath;
    return pageNameToFileName( $WikiGallery_PicturesBasePath, $name );
  }

  function exists($pagename) {
    // In gallery group?
    if( PageVar($pagename, '$Group')!=$this->galleryGroup )
      return false;

    // get page name
    $name = PageVar($pagename, '$Name');    

    // trail index, album or navigation page?
    if( preg_match( '/^(.*)(Index|Albums|Navigation)$/', $name, $matches ) ) {
      $name = $matches[1];
    }

    // does picture or directory exist?
    $pagefile = $this->pagefile($name);
    if( $pagefile!=-1 )
      return true;

    return false;
  }

  function read($pagename, $since=0) {
    global $WikiGallery_PicturesBasePath, $WikiGallery_OverviewThumbnailWidth, 
      $SiteGroup, $Now, $WikiGallery_NavThumbnailColumns;

    // In gallery group?
    if( PageVar($pagename, '$Group')!=$this->galleryGroup )
      return false;

    // get page name
    $name = PageVar($pagename, '$Name');

    // Trail index page?
    if( preg_match( '/^(.*)Index$/', $name, $matches ) ) {
      // is it a directory?
      $name = $matches[1];
      $pagefile = $this->pagefile($name);
      if( $pagefile==-1 || !is_dir($WikiGallery_PicturesBasePath . "/" . $pagefile) )
	return false;
      if( $pagefile=="" )
	$pagefileslash = "";
      else
	$pagefileslash = "$pagefile/";

      // create trail of pictures
      $pictures = WikiGalleryGetFilenames( $WikiGallery_PicturesBasePath . "/" . $pagefile );
      if( !$pictures ) return false;

      $page = ReadPage( 'Site.GalleryIndexTemplate' );
      $link = $this->galleryGroup . "." . fileNameToPageName( $pagefile );
      #$page["text"] .= "\n* [[$link|(:gallerypicture $WikiGallery_OverviewThumbnailWidth " . $this->galleryslash . $pagefile . "/" . $pictures[0] . ":)]]\n";
      #$page["targets"] .= ",$link";
      foreach( $pictures as $k ) {
	$link = $this->galleryGroup . "." . fileNameToPageName( "$pagefileslash$k" );
#	$page["text"] .= "* [[$link]]\n";
	$page["text"] .= "* [[$link]]\n";
	$page["targets"] .= ",$link";
      }
      $page['name'] = $pagename;
      
      return $page;
    }

    // album index page?
    if( preg_match( '/^(.*)Albums$/', $name, $matches ) ) {
      // is it a directory?
      $name = $matches[1];
      $pagefile = $this->pagefile($name);
      if( $pagefile==-1 || !is_dir($WikiGallery_PicturesBasePath . "/" . $pagefile) )
	return false;
      if( $pagefile=="" )
	$pagefileslash = "";
      else
	$pagefileslash = "$pagefile/";

      // create trail of directories
      $albums = WikiGalleryGetFilenames( $WikiGallery_PicturesBasePath . "/" . $pagefile, true );
      if( !$albums ) return false;

      $page = ReadPage( 'Site.GalleryIndexTemplate' );
      $link = $this->galleryGroup . "." . fileNameToPageName( $pagefile );
      #$page["text"] .= "\n* [[$link|(:gallerypicture $WikiGallery_OverviewThumbnailWidth " . $this->galleryslash . $pagefile . "/" . $albums[0] . ":)]]\n";
      #$page["targets"] .= ",$link";
      foreach( $albums as $k ) {
	$link = $this->galleryGroup . "." . fileNameToPageName( "$pagefileslash$k" );
#	$page["text"] .= "* [[$link]]\n";
	$page["text"] .= "* [[$link]]\n";
	$page["targets"] .= ",$link";
      }
      $page['name'] = $pagename;
      
      return $page;
    }

    // navigation trail index page?
    if( preg_match( '/^(.*)Navigation$/', $name, $matches ) ) { 
      $name = $matches[1];

      // get neighbour pictures
      $neighbours = WikiGalleryNeightbourPicture( $name, -($WikiGallery_NavThumbnailColumns-1)/2, $WikiGallery_NavThumbnailColumns );

      // create trail page
      $page = ReadPage( 'Site.GalleryIndexTemplate' );
      foreach( $neighbours as $pic ) {
	$page["text"] .= "* [[" . PageVar($pagename, '$Group') . "/" . $pic . "]]\n";
      }

      return $page;
    }
    
    // a gallery page?
    $pagefile = $this->pagefile($name);
    if( $pagefile!=-1 ) {
      // overview or picture?
      $filename = $WikiGallery_PicturesBasePath . $this->slashgallery . "/" . $pagefile;
      if( is_dir($filename) ) {
	// overview
	if( PageExists( $this->galleryGroup . ".GalleryPictureTemplate" ) )
	  $page = ReadPage( $this->galleryGroup . ".GalleryOverviewTemplate" );
	else
	  $page = ReadPage( "$SiteGroup.GalleryOverviewTemplate" );
	if( @$page ) {
	  $title = WikiGalleryLastComponent($pagefile);
	  $page['title'] = $title;
	  $page['text'] = preg_replace( '/\(:title\s[^:]*:\)/', "(:title $title:)", $page['text'] );
	  $page['galleryalbum'] = $this->galleryslash . $pagefile;
	  $page['ctime'] = filectime( $filename );
	  $page['time'] = filemtime( $filename );
	  return $page;
	}
      } else {
	// picture
	if( PageExists( $this->galleryGroup . ".GalleryPictureTemplate" ) )
	  $page = ReadPage( $this->galleryGroup . ".GalleryPictureTemplate" );
	else
	  $page = ReadPage( "$SiteGroup.GalleryPictureTemplate" );
	if( @$page ) {
	  $page['gallerypicture'] = $this->galleryslash . $pagefile;
	  
	  $pictureWithExt = WikiGalleryLastComponent($pagefile);
	  $picture = WikiGalleryLeadingComponents($pictureWithExt,".");
	  $album = WikiGalleryLeadingComponents($pagefile);
	  if( $album ) 
	    $albumPage = fileNameToPageName( $album );
	  else
	    $albumPage = "HomePage";

	  $title = PageVar($this->galleryGroup . ".$albumPage", '$Title') . " - " . $picture;
	  $page['title'] = $title;
	  $page['text'] = preg_replace( '/\(:title\s[^:]*:\)/', "(:title $title:)", $page['text'] );
	  $page['galleryoverview'] = PageVar($pagename,'$Group') . "." . $albumPage;
	  $page['ctime'] = filectime( $filename );
	  $page['time'] = filemtime( $filename );
	  return $page;
	}
      }
    }

    return false;
  }
}

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

function WikiGalleryMimeType( $file ) {
  if( preg_match("/.jpe?g$/i", $file) ) $format='image/jpeg'; 
  else if( preg_match("/.gif$/i", $file) ) $format='image/gif'; 
  else if( preg_match("/.png$/i", $file) ) $format='image/png'; 
  else return false;
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
  global $WikiGallery_PicturesBasePath, $WikiGallery_CacheBasePath, $WikiGallery_UseAuthorization;

  // get filename
  if( !isset( $_GET["image"] ) ) Abort('no image given');
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
#    WikiGalleryCleanupCache();
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
