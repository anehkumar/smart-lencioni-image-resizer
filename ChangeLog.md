# Change Log #

## 2.0 beta 4 ##
_2010-12-22_

## 2.0 beta 3 ##
_2010-02-25_

## 2.0 beta 2 ##
_2009-03-12_

  * Added front-line request cache in addition to the rendered cache
  * Added error log
  * Removed unnecessary check for HTTPS when serving the Cache-Control public header
  * Added options to the configuration file
  * Split the SLIRException class into its own file
  * Added query string stripping to the image path
  * Streamlined check for whether the source image exists
  * Optimized conditional gets

## 2.0 beta 1 ##
_2009-02-28_

  * Complete rewrite
  * New license (GNU General Public License v3)
  * Utilizes mod\_rewrite to provide shorter URLs that will be more friendly for Google Image Search
  * Preserves IPTC and EXIF data from source image
  * Improved sharpening to be more consistent
  * Improved HTTP headers should make caching work better
  * Error messages are displayed as images