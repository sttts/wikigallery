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

function WikiGallerySecurePath( $path ) {
  // ignore .. and beginning / in path
  $path = preg_replace( "/\\.\\./", '', $path );
  $path = preg_replace( "/^\\//", '', $path );
  $path = preg_replace( "/\\/+/", '/', $path );
  return $path;
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

function WikiGalleryMimeType( $file ) {
  if( preg_match("/.jpe?g$/i", $file) ) return 'image/jpeg'; 
  else if( preg_match("/.gif$/i", $file) ) return 'image/gif'; 
  else if( preg_match("/.png$/i", $file) ) return 'image/png'; 
  else return false;  
}

function WikiGalleryIsFileAndNonZero( $file ) {
    return is_file($file) && filesize($file)>0;
}
