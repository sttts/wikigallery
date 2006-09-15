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
SDV($WikiGallery_PicturesBasePath, "pictures/"); // the base path to the galleries
SDV($WikiGallery_PhpThumb, substr($PubDirUrl,0,strlen($PubDirUrl)-strlen(strrchr($PubDirUrl,"/"))) . "/phpThumb.php"  ); // the phpthumb script url

# Misc
SDV($WikiGallery_NavThumbnailColumns, 5); // odd number
SDV($WikiGallery_SortByDate, FALSE ); // otherwise alphabetical
SDV($WikiGallery_SortBackwards, FALSE );
SDV($WikiGallery_AlbumsSortByDate, TRUE ); // otherwise alphabetical
SDV($WikiGallery_AlbumsSortBackwards, TRUE );
SDV($WikiGallery_PathDelimiter, "-" ); // must be something valid in page names

# Image sizes
SDV($WikiGallery_DefaultSize, 640);

################################################################################

# The markup:
Markup('(:gallerypicture width picture:)','><|',"/\\(:gallerypicture\\s([0-9]+)\\s([^:]*):\\)/e","WikiGalleryPicture('$1','$2')");
Markup('(:gallerypicturerandom width album:)','><|',"/\\(:gallerypicturerandom\\s([0-9]+)\\s([^:]*):\\)/e","WikiGalleryPicture('$1','$2',true)");
#Markup('(:galleryoverviewtitle:)','<inline',"/\\(:galleryoverviewtitle:\\)/e", '$page["galleryoverview"]');

# Page variables
$FmtPV['$GalleryPicture'] = '$page["gallerypicture"] ? $page["gallerypicture"] : ""';
$FmtPV['$GalleryAlbum'] = '$page["galleryalbum"] ? $page["galleryalbum"] : ""';
$FmtPV['$GalleryOverview'] = '$page["galleryoverview"] ? $page["galleryoverview"] : ""';
$FmtPV['$GallerySize'] = '$GLOBALS["WikiGallery_Size"]';

# default pages
$WikiLibDirs[] = new PageStore("$FarmD/cookbook/wikigallery/wikilib.d/\$FullName");

# which files?
$WikiGallery_ImgExts = '\.jpg$|\.jpeg$|\.jpe$|\.png$|\.bmp$';

# Replace .jpg in pagename such that using Group/Bla.jpg behaves like Group/Bla
$pagename = preg_replace ('/'.$WikiGallery_ImgExts.'$/','',$pagename );

# no trailing / 
preg_replace( "/\\/$/", '', $WikiGallery_PicturesBasePath);

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
if( $WikiGallery_Size<0 || $WikiGallery_Size>10000 )
  $WikiGallery_Size = $WikiGallery_DefaultSize;

# filename <-> pagename conversion
function fileNameToPageName( $filename ) {
  global $WikiGallery_PathDelimiter,$WikiGallery_ImgExts;
  $filename = preg_replace( array('/'.$WikiGallery_ImgExts.'$/i', "/[^a-zA-Z0-9\\/]/", "/\\//"),
			    array('', '', $WikiGallery_PathDelimiter), 
			    $filename );
  return $filename;
}

function pageNameToFileName( $basePath, $pageName ) {
  global $WikiGallery_PathDelimiter;

  // empty pageName?
  if( $pageName=="" ) return "";

  //echo "Will try to find filename for $pageName<br/>";
  $lastComponent = strrchr($pageName, $WikiGallery_PathDelimiter);
  if( $lastComponent==FALSE ) {
    $path = "";
    $pathslash = "";
    $lastComponent = $pageName;
  } else {
    $head = substr( $pageName, 0, strlen($pageName)-strLen($lastComponent) );
    $lastComponent = substr($lastComponent,1);
    $path = pageNameToFileName( $basePath, $head );
    if( $path==-1 ) return -1;
    $pathslash = "$path/";
  }

  $pwd = opendir( $basePath . "/" . $path);
  $found = "";
  while( ($file=readdir($pwd))!=false ) {
    $filePageName = fileNameToPageName( $file );
    if( $lastComponent==$filePageName ) {
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

function securePath( $path ) {
  # ignore .. and beginning / in path
  $path = preg_replace( "/\\.\\./", '', $path );
  $path = preg_replace( "/^\\//", '', $path );
  $path = preg_replace( "/\\/+/", '/', $path );
  return $path;
}

function getFilenames( $path, $albums=false ) {
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

function WikiGalleryPicture( $size, $path, $random=false ) {
  global $WikiGallery_PhpThumb, $WikiGallery_PicturesBasePath;
  $path = securePath( $path );

  // random picture?
  if( $random ) {
    // get pictures
    $pictures = getFilenames( $WikiGallery_PicturesBasePath . "/" . $path );
    if( !$pictures ) return false;

    // choose random picture
    $num = rand(0, count($pictures)-1);
    $path .= "/" . $pictures[$num];
  }
  
  // return phpthumb url
  if( $size==0 )
    return "$WikiGallery_PhpThumb?src=" . urlencode($path) . " ";
  else
    return "$WikiGallery_PhpThumb?w=" . urlencode($size) . "&src=" . urlencode($path) . " ";
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
    global $WikiGallery_PicturesBasePath, $WikiGallery_OverviewThumbnailWidth, $SiteGroup, $Now;

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
      $pictures = getFilenames( $WikiGallery_PicturesBasePath . "/" . $pagefile );
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
      $albums = getFilenames( $WikiGallery_PicturesBasePath . "/" . $pagefile, true );
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
      // is it a file?
      $name = $matches[1];
      $pagefile = $this->pagefile($name);
      if( $pagefile==-1 || is_dir($WikiGallery_PicturesBasePath . "/" . $pagefile) )
	return false;

      # split pathes into filename and path
      $slashfilename = strrchr($pagefile,"/");
      if( $slashfilename==FALSE ) {
	$filename = $pagefile;
	$path = "";
	$slashpath = "";
	$pathslash = "";
      } else {
	$path = substr($pagefile,0,strlen($pagefile)-strlen($slashfilename));
	$slashpath = "/" . $path;
	$pathslash = $path . "/";
	$filename = substr($slashfilename,1);
      }

      # find position in the album of the current picture
      $pictures = getFilenames( $WikiGallery_PicturesBasePath . $slashpath );    
      $indexed = array();
      $i = 0;
      $thisIndex = -1;
      foreach( $pictures as $k ) {
	$indexed[$i] = $k;
	//echo "$k==$filename ?<br/>";
	if( $k==$filename ) {
	  $thisIndex = $i;
	  //echo "Found $k==$filename<br/>";
	}
	$i++;
      }
      $picturesNum = $i;
    
      # found?
      if( $thisIndex==-1 ) return false;

      # add maxcolumns many pictures as thumbnails
      $page = ReadPage( 'Site.GalleryIndexTemplate' );
      global $WikiGallery_NavThumbnailColumns;
      $i = max($thisIndex - ($WikiGallery_NavThumbnailColumns-1)/2, 0);
      while( $i>=0 && $i<$picturesNum && $i<=$thisIndex + ($WikiGallery_NavThumbnailColumns-1)/2 ) {
	$page["text"] .= "* [[" . PageVar($pagename, '$Group') . "/" . fileNameToPageName($pathslash . $indexed[$i]) . "]]\n";
	$i++;
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
	  $album = substr($pagefile, 0, strlen($pagefile)-strlen(strrchr($pagefile,'/')));
	  $page['galleryoverview'] = PageVar($pagename,'$Group') . "." . fileNameToPageName( $album );
	  $page['ctime'] = filectime( $filename );
	  $page['time'] = filemtime( $filename );
	  return $page;
	}
      }
    }

    return false;
  }
}
