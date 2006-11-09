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

require_once( "tools.php" );

// Paths
SDV($WikiGallery_PicturesWebPath, "pictures/"); // the path to the galleries (relative to the host url http://foo.com/)
SDV($WikiGallery_PicturesBasePath, $WikiGallery_PicturesWebPath); // the path to the galleries (on the filesystem, relative to pmwiki.php)

SDV($WikiGallery_CacheWebPath, "cache/"); // the path to the thumbnail cache (relative to the host url http://foo.com/)
SDV($WikiGallery_CacheBasePath, $WikiGallery_CacheWebPath); // the path to the thumbnail cache (on the filesystem, relative to pmwiki.php)

SDV($WikiGallery_ImageMagickPath, "/usr/bin/"); // absolute path to the ImageMagick binaries (mogrify, convert, ...)
SDV($WikiGallery_FindPath, ""); // path to the find command, usually /usr/bin/
SDV($WikiGallery_PhpThumb, WikiGalleryLeadingComponents($PubDirUrl) . "/phpThumb.php"  ); // the phpthumb script url

// How the wiki pages look like
SDV($WikiGallery_NavThumbnailColumns, 5); // odd number
SDV($WikiGallery_SortByDate, FALSE ); // otherwise alphabetical
SDV($WikiGallery_SortBackwards, FALSE );
SDV($WikiGallery_AlbumsSortByDate, TRUE ); // otherwise alphabetical
SDV($WikiGallery_AlbumsSortBackwards, TRUE );
SDV($WikiGallery_DefaultSlideshowDelay, 5 );

// Thumbnail generation
SDV($WikiGallery_ThumbFunction, 'WikiGalleryInternalThumb');  // use internal thumbnail routine. Set to 'WikiGalleryPhpThumb' for phpthumb
SDV($WikiGallery_ScaleMethod, "auto"); // either "auto", "imagemagick" or "libgd2"; "auto" means first imagemagick, then libgd2
SDV($WikiGallery_HighQualityResize, true); // use better quality (but slower) resize algorithms?
SDV($WikiGallery_UseAuthorization, true); // try to authorize for the page the picture/thumbnail is belonging to

// Clean up of thumbnail cache
SDV($WikiGallery_CleanupDelay, 7); // if nobody accessed a thumbnail for a week, purge it
SDV($WikiGallery_CleanupInterval, 3600); // cleanup once an hour

// Misc
SDV($WikiGallery_PathDelimiter, "-" ); // must be something valid in page names
SDV($WikiGallery_DefaultSize, 640);

################################################################################

// The markup:
Markup('(:gallerypicture width picture:)','><|',"/\\(:gallerypicture\\s([0-9]+)\\s([^:]*):\\)/e",'WikiGalleryDefaultPageStore()->picture(\'$1\',\'$2\')');
Markup('(:gallerypicturerandom width album:)','><|',"/\\(:gallerypicturerandom\\s([0-9]+)\\s([^:]*):\\)/e",'WikiGalleryDefaultPageStore()->picture(\'$1\',\'$2\',true)');

// Page variables
$FmtPV['$GalleryPicture'] = '$page["gallerypicture"] ? $page["gallerypicture"] : ""';
$FmtPV['$GalleryAlbum'] = '$page["galleryalbum"] ? $page["galleryalbum"] : ""';
$FmtPV['$GalleryOverview'] = '$page["galleryoverview"] ? $page["galleryoverview"] : ""';
$FmtPV['$GallerySize'] = '$GLOBALS["WikiGallery_Size"]';
$FmtPV['$GalleryParent'] = 'WikiGalleryPageStore("$group")->parent("$name")';
$FmtPV['$GalleryNext'] = 'WikiGalleryPageStore("$group")->neightbourPicture("$name",1)';
$FmtPV['$GalleryNextNext'] = 'WikiGalleryPageStore("$group")->neightbourPicture("$name",2)';
$FmtPV['$GalleryPrev'] = 'WikiGalleryPageStore("$group")->neightbourPicture("$name",-1)';
$FmtPV['$GalleryPrevPrev'] = 'WikiGalleryPageStore("$group")->neightbourPicture("$name",-2)';

#################################################################################

// group register for the pagestores
$WikiGallery_Register = array();
$WikiGallery_DefaultGroup = false;

function WikiGalleryPageStore( $group ) {
  global $WikiGallery_Register;
  if( @$WikiGallery_Register[$group] )
    return $WikiGallery_Register[$group];
  else
    return WikiGalleryDefaultPageStore();
}

function WikiGalleryDefaultPageStore() {
  global $WikiGallery_Register, $WikiGallery_DefaultGroup;
  if( !@$WikiGallery_Register[$WikiGallery_DefaultGroup] ) Abort("No gallery group defined");
  return $WikiGallery_Register[$WikiGallery_DefaultGroup];
}

// default pages
$WikiLibDirs[] = new PageStore("$FarmD/cookbook/wikigallery/wikilib.d/\$FullName");

// which files?
$WikiGallery_ImgExts = '\.jpg$|\.jpeg$|\.jpe$|\.png$|\.bmp$';

// Replace .jpg in pagename such that using Group/Bla.jpg behaves like Group/Bla
$pagename = preg_replace ('/'.$WikiGallery_ImgExts.'$/','',$pagename );

// no trailing / 
$WikiGallery_PicturesBasePath = preg_replace( "/\\/$/", '', $WikiGallery_PicturesBasePath);
$WikiGallery_CacheBasePath = preg_replace( "/\\/$/", '', $WikiGallery_CacheBasePath);

// add Site.GalleryListTemplates to the list of fmt=#xyz options for pagelists
$FPLTemplatePageFmt[] = '{$SiteGroup}.GalleryListTemplates';

// new picture size?
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

require_once( "slideshow.php" );     

// filename <-> pagename conversion
function fileNameToPageName( $filename ) {
  global $WikiGallery_PathDelimiter,$WikiGallery_ImgExts;
  $pagename = preg_replace( array('/'.$WikiGallery_ImgExts.'$/i', "/[^a-zA-Z0-9\\/]/", "/\\//"),
			    array('', '', $WikiGallery_PathDelimiter), 
			    $filename );
  
  return ucfirst($pagename);
}

class GalleryProvider {
  function pageNameToFileName( $pageName ) {
    return false;
  }

  function getFilenames( $path, $albums=false ) {
    return false;
  }

  function isAlbum( $path ) {
    return false;
  }

  function isPicture( $path ) {
    return false;
  }

  function pictureTime( $path ) {
    global $Now;
    return $Now;
  }

  function thumb( $path, $size ) {
    return false;
  }
}

require_once( "directoryprovider.php" );

class GalleryPageStore extends PageStore {
  var $galleryGroup;
  var $dirfmt;
  var $provider;

  function GalleryPageStore( $galleryGroup, $provider=false ) {
    global $WikiGallery_PicturesBasePath, $WikiGallery_Register, $WikiGallery_DefaultGroup, $WikiGallery_PicturesWebPath;
    $WikiGallery_Register[$galleryGroup] = $this;
    $WikiGallery_DefaultGroup = $galleryGroup;
    $this->PageStore( $WikiGallery_PicturesBasePath );
    $this->galleryGroup = $galleryGroup;
    if( $provider ) 
      $this->provider = $provider; 
    else
      $this->provider = new GalleryDirectoryProvider( $WikiGallery_PicturesBasePath, $WikiGallery_PicturesWebPath );
  }

  function pagefile($name) {
    return $this->provider->pageNameToFileName( $name );
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
    global $WikiGallery_OverviewThumbnailWidth, $SiteGroup, $Now, $WikiGallery_NavThumbnailColumns;

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
      if( $pagefile==-1 || !$this->provider->isAlbum("/" . $pagefile) )
	return false;
      if( $pagefile=="" )
	$pagefileslash = "";
      else
	$pagefileslash = "$pagefile/";

      // create trail of pictures
      $pictures = $this->provider->getFilenames( "/" . $pagefile );
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
      if( $pagefile==-1 || !$this->provider->isAlbum("/" . $pagefile) )
	return false;
      if( $pagefile=="" )
	$pagefileslash = "";
      else
	$pagefileslash = "$pagefile/";

      // create trail of directories
      $albums = $this->provider->getFilenames( "/" . $pagefile, true );
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
      $neighbours = $this->neightbourPicture( $name, -($WikiGallery_NavThumbnailColumns-1)/2, $WikiGallery_NavThumbnailColumns );

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
      $filename = $this->slashgallery . "/" . $pagefile;
      if( $this->provider->isAlbum($filename) ) {    
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
	  $page['ctime'] = $this->provider->pictureTime($filename);
	  $page['time'] = $page['ctime']; //filemtime( $filename );
	  return $page;
	}
      } elseif( $this->provider->isPicture($filename) ) {    
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
	  $page['ctime'] = $this->provider->pictureTime( $filename );
	  $page['time'] = $page['ctime'];
	  return $page;
	}
      }
    }

    return false;
  }

  function parent( $name ) {
    // is it a directory?
    $pagefile = $this->provider->pageNameToFileName( $name );
    if( $pagefile==-1 && !$this->provider->isAlbum("/" . $pagefile) )
    return false;

    // return parent page
    $path = WikiGalleryLeadingComponents($pagefile);
    if( $path ) return fileNameToPageName( $path );
    return "";
  }

  function neightbourPicture( $name, $delta, $count=-1 ) {
    // is it a file?
    $pagefile = $this->provider->pageNameToFileName( $name );
    if( $pagefile==-1 || $this->provider->isAlbum("/" . $pagefile) )
    return false;

    // split pathes into filename and path
    $filename = WikiGalleryLastComponent( $pagefile );
    $path = WikiGalleryLeadingComponents( $pagefile );
    if( !$path ) {
      $slashpath = "";
      $pathslash = "";
    } else {
      $slashpath = "/" . $path;
      $pathslash = $path . "/";
    }
    
    // find position in the album of the current picture
    $pictures = $this->provider->getFilenames( $slashpath );    
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
    
    // found?
    if( $thisIndex==-1 ) return false;

    // get $count many neighbours from $thisIndex-$delta  
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

  function picture( $size, $path, $random=false ) {
    $path = WikiGallerySecurePath( $path );  

    // random picture?
    if( $random ) {
      // get pictures
      $pictures = $this->provider->getFilenames( "/" . $path );
      if( !$pictures ) return false;
      
      // choose random picture
      $num = rand(0, count($pictures)-1);
      $path .= "/" . $pictures[$num];
    }
  
    // return phpthumb url
    return $this->provider->thumb( $path, $size );
  }
}
