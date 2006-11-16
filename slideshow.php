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

// slideshow?
$HandleActions["slideshow"] = "WikiGallerySlideshow";
function WikiGallerySlideshow( $pagename, $auth = 'read') {
  global $WikiGallery_DefaultSlideshowDelay, $HTMLHeaderFmt, $WikiGallery_Register;

  // get delay from url
  if( isset($_Get["delay"]) )
    $delay = intval($_GET["delay"]);
  else
    $delay = $WikiGallery_DefaultSlideshowDelay;

  // find following picture
  $group = PageVar($pagename, '$Group');
  $next = $WikiGallery_Register[$group]->neighbourPicture( PageVar($pagename, '$Name'), 1 );
  $nextpage = "$group.$next";

  // exists?
  if( $next && PageExists($nextpage) ) {
    // add refresh header 
    $url = MakeLink( $nextpage, $nextpage, NULL, NULL, "\$LinkUrl" );
    array_unshift( $HTMLHeaderFmt, "<meta http-equiv=\"refresh\" content=\"$delay; URL=$url?action=slideshow&delay=$delay\" />" );
  }
  
  return HandleBrowse( $pagename, $auth );
}
