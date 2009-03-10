<?php
/**
 * Class definition file for SLIR (Smart Lencioni Image Resizer)
 *
 * This file is part of SLIR (Smart Lencioni Image Resizer).
 *
 * SLIR is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * SLIR is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SLIR.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright Copyright � 2009, Joe Lencioni
 * @license http://opensource.org/licenses/gpl-3.0.html GNU General Public
 * License version 3 (GPLv3)
 * @since 2.0
 * @package SLIR
 */
 
/* $Id$ */

/**
 * SLIR (Smart Lencioni Image Resizer)
 * Resizes images, intelligently sharpens, crops based on width:height ratios,
 * color fills transparent GIFs and PNGs, and caches variations for optimal
 * performance.
 *
 * I love to hear when my work is being used, so if you decide to use this,
 * feel encouraged to send me an email. I would appreciate it if you would
 * include a link on your site back to Shifting Pixel (either the SLIR page or
 * shiftingpixel.com), but don�t worry about including a big link on each page
 * if you don�t want to�one will do just nicely. Feel free to contact me to
 * discuss any specifics (joe@shiftingpixel.com).
 *
 * REQUIREMENTS:
 *     - PHP 5.1.0+
 *     - GD
 *
 * RECOMMENDED:
 *     - mod_rewrite
 *
 * USAGE:
 * To use, place an img tag with the src pointing to the path of SLIR (typically
 * "/slir/") followed by the parameters, followed by the path to the source
 * image to resize. All parameters follow the pattern of a one-letter code and
 * then the parameter value:
 *     - Maximum width = w
 *     - Maximum height = h
 *     - Crop ratio = c
 *     - Quality = q
 *     - Background fill color = b
 *     - Progressive = p
 *
 * EXAMPLES:
 *
 * Resizing a JPEG to a max width of 100 pixels and a max height of 100 pixels:
 * <code><img src="/slir/w100-h100/path/to/image.jpg" alt="Don't forget your alt
 * text" /></code>
 *
 * Resizing and cropping a JPEG into a square:
 * <code><img src="/slir/w100-h100-c1:1/path/to/image.jpg" alt="Don't forget
 * your alt text" /></code>
 *
 * Resizing a JPEG without interlacing (for use in Flash):
 * <code><img src="/slir/w100-p0/path/to/image.jpg" alt="Don't forget your alt
 * text" /></code>
 *
 * Matting a PNG with #990000:
 * <code><img src="/slir/b900/path/to/image.png" alt="Don't forget your alt
 * text" /></code>
 *
 * Without mod_rewrite (not recommended)
 * <code><img src="/slir/?w=100&amp;h=100&amp;c=1:1&amp;image=/path/to/image.jpg"
 * alt="Don't forget your alt text" /></code>
 *
 * @author Joe Lencioni <joe@shiftingpixel.com>
 * @date $Date$
 * @version $Revision$
 * @package SLIR
 *
 * @uses PEL
 * 
 * @todo lock files when writing?
 * @todo Prevent SLIR from calling itself
 * @todo Percentage resizing?
 * @todo Animated GIF resizing?
 * @todo Smart cropping with detail detection or seam carving?
 * @todo Crop zoom?
 * @todo Crop offsets?
 * @todo Periodic cache clearing?
 * @todo Remote image fetching?
 * @todo Alternative support for ImageMagick?
 * @todo Prevent files in cache from being read directly?
 */

class SLIR
{
	/**
	 * @since 2.0
	 * @var string
	 */
	const VERSION	= '2.0b2';

	/**
	 * Path to source image
	 *
	 * @since 2.0
	 * @var string
	 */
	private $imagePath;

	/**
	 * Maximum width for resized image, in pixels
	 *
	 * @since 2.0
	 * @var integer
	 */
	private $maxWidth;

	/**
	 * Maximum height for resized image, in pixels
	 *
	 * @since 2.0
	 * @var integer
	 */
	private $maxHeight;

	/**
	 * Quality setting for resized image. Ranges from 0 (worst quality, smaller
	 * file) to 100 (best quality, biggest file). If not specified, will default
	 * to SLIR_DEFAULT_QUALITY setting in slir-config.php
	 *
	 * @since 2.0
	 * @var integer
	 */
	private $quality;

	/**
	 * Ratio of width:height to crop image to.
	 *
	 * For example, if a square shape is desired, the crop ratio should be "1:1"
	 * or if a long rectangle is desired, the crop ratio could be "4:1". Stored
	 * as an associative array with keys being 'width' and 'height'.
	 *
	 * @since 2.0
	 * @var array
	 */
	private $cropRatio;

	/**
	 * Whether JPEG should be a progressive JPEG (interlaced) or not. If not
	 * specified, will default to SLIR_DEFAULT_PROGRESSIVE_JPEG setting in
	 * slir-config.php
	 *
	 * @since 2.0
	 * @var bool
	 */
	private $progressiveJPEGs;

	/**
	 * A color, in hexadecimal format (RRGGBB), to fill in as the background
	 * color for PNG images
	 *
	 * Longhand values (e.g. "FF0000") and shorthand values (e.g. "F00") are
	 * both acceptable
	 *
	 * @since 2.0
	 * @var string
	 */
	private $backgroundFillColor;

	/**
	 * Information about the source image
	 *
	 * Generated in part by {@link http://us2.php.net/getimagesize getimagesize()}
	 *
	 * @since 2.0
	 * @var array
	 */
	private $source;

	/**
	 * Information about the rendered image
	 *
	 * @since 2.0
	 * @var string
	 */
	private $rendered;

	/**
	 * Whether or not the cache has already been initialized
	 *
	 * @since 2.0
	 * @var boolean
	 */
	private $isCacheInitialized	= FALSE;

	/**
	 * The magic starts here
	 *
	 * @since 2.0
	 */
	public function __construct()
	{
		$this->getConfig();

		// Check the cache based on the request URI
		if (SLIR_USE_REQUEST_CACHE && $this->isRequestCached())
			$this->serveRequestCachedImage();

		// Set all parameters for resizing
		$this->setParameters($this->getParameters());

		// See if there is anything we actually need to do
		if ($this->isSourceImageDesired())
			$this->serveSourceImage();

		// Determine rendered dimensions
		$this->getRenderProperties();

		// Check the cache based on the properties of the rendered image
		if (!$this->isRenderedCached() || !$this->serveRenderedCachedImage())
		{
			// Image is not cached in any way, so we need to render the image,
			// cache it, and serve it up to the client
			$this->render();
			$this->serveRenderedImage();
		} // if

	} // __construct()

	/**
	 * Helps control the parameters as they are set
	 *
	 * @since 2.0
	 * @param string $name
	 * @param mixed $value
	 * @todo Prevent SLIR from calling itself
	 */
	private function __set($name, $value)
	{
		switch($name)
		{
			case 'i':
			case 'image':
			case 'imagePath':
				// Images must be local files, so for convenience we strip the
				// domain if it's there
				$this->imagePath	= '/' . trim(preg_replace('/^(s?f|ht)tps?:\/\/[^\/]+/i', '', (string) urldecode($value)), '/');
				
				// Strip query string from the image path
				$this->imagePath	= preg_replace('/\?.*/', '', $this->imagePath);

				// Make sure the image path is secure
				if (!$this->isPathSecure($this->imagePath))
				{
					header('HTTP/1.1 400 Bad Request');
					throw new SLIRException('Image path may not contain ":", "..", "<", or ">"');
				}
				// Make sure the path is a file
				else if (!is_file(SLIR_DOCUMENT_ROOT . $this->imagePath))
				{
					header('HTTP/1.1 400 Bad Request');
					throw new SLIRException('Image path is not a file: ' . SLIR_DOCUMENT_ROOT . $this->imagePath);
				}
				// Make sure the image file exists
				else if (!$this->imageExists())
				{
					header('HTTP/1.1 404 Not Found');
					throw new SLIRException('Image does not exist: ' . SLIR_DOCUMENT_ROOT . $this->imagePath);
				}
				// Everything seems to check out just fine, proceeding normally
				else
				{
					// Set the image info (width, height, mime type, etc.)
					$this->source	= $this->getImageInfo();

					// Make sure the file is actually an image
					if (!$this->isImage())
					{
						header('HTTP/1.1 400 Bad Request');
						throw new SLIRException('Requested file is not an accepted image type: ' . SLIR_DOCUMENT_ROOT . $this->imagePath);
					} // if
				} // if
			break;

			case 'w':
			case 'width':
			case 'maxWidth':
				$this->maxWidth		= (int) $value;
			break;

			case 'h':
			case 'height':
			case 'maxHeight':
				$this->maxHeight	= (int) $value;
			break;

			case 'q':
			case 'quality':
				$this->quality		= (int) $value;

				if ($this->quality < 0 || $this->quality > 100)
				{
					header('HTTP/1.1 400 Bad Request');
					throw new SLIRException('Quality must be between 0 and 100: '
						. $this->quality);
				}
			break;

			case 'p':
			case 'progressive':
				$this->progressiveJPEGs	= (bool) $value;
			break;

			case 'c':
			case 'cropRatio':
				$ratio				= explode(':', (string) $value);
				if (count($ratio) >= 2)
				{
					$this->cropRatio	= array(
						'width'		=> (float) $ratio[0],
						'height'	=> (float) $ratio[1],
						'ratio'		=> (float) $ratio[0] / (float) $ratio[1]
					);
				}
				else
				{
					header('HTTP/1.1 400 Bad Request');
					throw new SLIRException('Crop ratio must be in width:height '
						. 'format: ' . (string) $value);
				}
			break;

			case 'b';
			case 'backgroundFillColor':
				$this->backgroundFillColor	= preg_replace('/[^0-9a-fA-F]/', '',
					(string) $value);

				$bglen	= strlen($this->backgroundFillColor);
				if($bglen == 3)
				{
					$this->backgroundFillColor = $this->backgroundFillColor[0]
						.$this->backgroundFillColor[0]
						.$this->backgroundFillColor[1]
						.$this->backgroundFillColor[1]
						.$this->backgroundFillColor[2]
						.$this->backgroundFillColor[2];
				}
				else if ($bglen != 6)
				{
					header('HTTP/1.1 400 Bad Request');
					throw new SLIRException('Background fill color must be in '
						.'hexadecimal format, longhand or shorthand: '
						. $this->backgroundFillColor);
				}
			break;
		} // switch
	} // __set()

	/**
	 * Includes the configuration file
	 *
	 * @since 2.0
	 */
	private function getConfig()
	{
		if (file_exists('slir-config.php'))
		{
			require 'slir-config.php';
		}
		else if (file_exists('slir-config-sample.php'))
		{
			if (copy('slir-config-sample.php', 'slir-config.php'))
				require 'slir-config.php';
			else
				throw new SLIRException('Could not load configuration file. '
					. 'Please copy "slir-config-sample.php" to '
					. '"slir-config.php".');
		}
		else
		{
			throw new SLIRException('Could not find "slir-config.php" or '
				. '"slir-config-sample.php"');
		} // if
	} // getConfig()

	/**
	 * Renders specified changes to the image
	 *
	 * @since 2.0
	 */
	private function render()
	{
		// We don't want to run out of memory
		ini_set('memory_limit', SLIR_MEMORY_TO_ALLOCATE);

		// Set up a blank canvas for our rendered image (destination)
		$this->rendered['image']	= imagecreatetruecolor(
										$this->rendered['width'],
										$this->rendered['height']
									);

		// Read in the original image
		$this->source['image']		= $this->rendered['functions']['create'](SLIR_DOCUMENT_ROOT . $this->imagePath);

		// GIF/PNG transparency and background color
		$this->background();

		// Resample the original image into the resized canvas we set up earlier
		ImageCopyResampled(
			$this->rendered['image'],
			$this->source['image'],
			0,
			0,
			$this->rendered['offset']['left'],
			$this->rendered['offset']['top'],
			$this->rendered['width'],
			$this->rendered['height'],
			$this->source['width'],
			$this->source['height']
		);

		// Sharpen
		if ($this->rendered['sharpen'])
			$this->sharpen();

		// Set interlacing
		if ($this->rendered['progressive'])
			imageinterlace($this->rendered['image'], 1);

	} // render()

	/**
	 * Turns on transparency for rendered image if no background fill color is
	 * specified, otherwise, fills background with specified color
	 *
	 * @since 2.0
	 */
	private function background()
	{
		if (!$this->isBackgroundFillOn())
		{
			// If this is a GIF or a PNG, we need to set up transparency
			imagealphablending($this->rendered['image'], FALSE);
			imagesavealpha($this->rendered['image'], TRUE);
		}
		else
		{
			// Fill the background with the specified color for matting purposes
			$background	= imagecolorallocate(
				$this->rendered['image'],
				hexdec($this->backgroundFillColor[0].$this->backgroundFillColor[1]),
				hexdec($this->backgroundFillColor[2].$this->backgroundFillColor[3]),
				hexdec($this->backgroundFillColor[4].$this->backgroundFillColor[5])
			);

			imagefill($this->rendered['image'], 0, 0, $background);
		} // if
	} // background()

	/**
	 * Sharpens the image based on two things:
	 *   (1) the difference between the original size and the final size
	 *   (2) the final size
	 *
	 * @since 2.0
	 */
	private function sharpen()
	{
		$sharpness	= $this->calculateSharpnessFactor(
			$this->source['width'] * $this->source['height'],
			$this->rendered['width'] * $this->rendered['height']
		);

		$sharpenMatrix	= array(
			array(-1, -2, -1),
			array(-2, $sharpness + 12, -2),
			array(-1, -2, -1)
		);

		$divisor	= $sharpness;
		$offset		= 0;

		imageconvolution(
			$this->rendered['image'],
			$sharpenMatrix,
			$divisor,
			$offset
		);
	} // sharpen()

	/**
	 * Calculates sharpness factor to be used to sharpen an image based on the
	 * area of the source image and the area of the destination image
	 *
	 * @since 2.0
	 * @author Ryan Rud
	 * @link http://adryrun.com
	 *
	 * @param integer $sourceArea Area of source image
	 * @param integer $destinationArea Area of destination image
	 * @return integer Sharpness factor
	 */
	private function calculateSharpnessFactor($sourceArea, $destinationArea)
	{
		$final	= $destinationArea * (750.0 / $sourceArea);
		$a		= 52;
		$b		= -0.27810650887573124;
		$c		= .00047337278106508946;

		$result = $a + $b * $final + $c * $final * $final;

		return max(round($result), 0);
	} // calculateSharpnessFactor()

	/**
	 * @since 2.0
	 * @param string $cacheFilePath
	 * @return string Contents of the image
	 */
	private function copyIPTC($cacheFilePath)
	{
		$data	= '';

		$iptc	= $this->source['iptc'];

		// Originating program
		$iptc['2#065']	= array('Smart Lencioni Image Resizer');

		// Program version
		$iptc['2#070']	= array(SLIR::VERSION);

		foreach($iptc as $tag => $iptcData)
		{
			$tag	= substr($tag, 2);
			$data	.= $this->makeIPTCTag(2, $tag, $iptcData[0]);
		}

		// Embed the IPTC data
		return iptcembed($data, $cacheFilePath);
	} // copyIPTC()

	/**
	 * @since 2.0
	 * @author Thies C. Arntzen
	 */
	function makeIPTCTag($rec, $data, $value)
	{
		$length = strlen($value);
		$retval = chr(0x1C) . chr($rec) . chr($data);

		if($length < 0x8000)
		{
			$retval .= chr($length >> 8) .  chr($length & 0xFF);
		}
		else
		{
			$retval .= chr(0x80) .
					   chr(0x04) .
					   chr(($length >> 24) & 0xFF) .
					   chr(($length >> 16) & 0xFF) .
					   chr(($length >> 8) & 0xFF) .
					   chr($length & 0xFF);
		}

		return $retval . $value;
	} // makeIPTCTag()

	/**
	 * Determines the parameters to use for resizing
	 *
	 * @since 2.0
	 */
	private function getParameters()
	{
		if (!$this->isUsingQueryString()) // Using the mod_rewrite version
			return $this->getParametersFromPath();
		else // Using the query string version
			return $_GET;
	} // getParameters()

	/**
	 * For requests that are using the mod_rewrite syntax
	 *
	 * @since 2.0
	 */
	private function getParametersFromPath()
	{
		$params	= array();

		// The parameters should be the first set of characters after the
		// SLIR path
		$request	= str_replace(SLIR_DIR, '', (string) $_SERVER['REQUEST_URI']);
		$request	= explode('/', trim($request, '/'));

		if (count($request) < 2)
		{
			header('HTTP/1.1 400 Bad Request');
			throw new SLIRException('Not enough parameters were given.', 'Available parameters:
w = Maximum width
h = Maximum height
c = Crop ratio (width:height)
q = Quality (0-100)
b = Background fill color (RRGGBB or RGB)
p = Progressive (0 or 1)

Example usage:
<img src="' . SLIR_DIR . '/w300-h300-c1:1/path/to/image.jpg" alt="Don\'t forget '
.'your alt text!" />'
			);

		} // if

		// The parameters are separated by hyphens
		$rawParams	= array_filter(explode('-', array_shift($request)));

		// The image path should be all of the remaining values in the array
		$params['i']	= implode('/', $request);

		foreach ($rawParams as $rawParam)
		{
			// The name of each parameter should be the first character of the
			// parameter string
			$name	= $rawParam[0];
			// The value of each parameter should be the remaining characters of
			// the parameter string
			$value	= substr($rawParam, 1, strlen($rawParam) - 1);

			$params[$name]	= $value;
		} // foreach

		$params	= array_filter($params);

		return $params;
	} // getParametersFromPath()

	/**
	 * Sets up parameters for image resizing
	 *
	 * @since 2.0
	 * @param array $params Associative array of parameters
	 */
	private function setParameters($params)
	{
		// Set image path first
		if (isset($params['i']) && $params['i'] != '' && $params['i'] != '/')
		{
			$this->__set('i', $params['i']);
			unset($params['i']);
		}
		else
		{
			header('HTTP/1.1 400 Bad Request');
			throw new SLIRException('Source image was not specified.');
		} // if

		// Set the rest of the parameters
		foreach($params as $name => $value)
		{
			$this->__set($name, $value);
		} // foreach

		// If either a max width or max height are not specified or larger than
		// the source image we default to the dimension of the source image so
		// they do not become constraints on our resized image.
		if (!$this->maxWidth || $this->maxWidth > $this->source['width'])
			$this->maxWidth		= $this->source['width'];

		if (!$this->maxHeight ||  $this->maxHeight > $this->source['height'])
			$this->maxHeight	= $this->source['height'];

	} // setParameters()

	/**
	 * Determines if the request is using the mod_rewrite version or the query
	 * string version
	 *
	 * @since 2.0
	 * @return bool
	 */
	private function isUsingQueryString()
	{
		if (isset($_SERVER['QUERY_STRING'])
			&& trim($_SERVER['QUERY_STRING']) != ''
			&& count(array_intersect(array('i', 'w', 'h', 'q', 'c', 'b'), array_keys($_GET)))
			)
			return TRUE;
		else
			return FALSE;
	} // isUsingQueryString()

	/**
	 * Checks to see if the image path is secure
	 *
	 * For security, directories may not contain ':' and images may not contain
	 * '..', '<', or '>'.
	 *
	 * @since 2.0
	 * @param string $path
	 * @return bool
	 */
	private function isPathSecure($path)
	{
		if (strpos(dirname($path), ':') || preg_match('/(\.\.|<|>)/', $path))
			return FALSE;
		else
			return TRUE;
	} // isImagePathSecure()

	/**
	 * Determines if the source image exists
	 *
	 * @since 2.0
	 * @return bool
	 */
	private function imageExists()
	{
		return file_exists(SLIR_DOCUMENT_ROOT . $this->imagePath);
	} // imageExists()

	/**
	 * Retrieves information about the source image such as width and height
	 *
	 * @since 2.0
	 * @return array
	 */
	private function getImageInfo()
	{
		$info			= getimagesize(SLIR_DOCUMENT_ROOT . $this->imagePath, $extraInfo);

		if ($info == FALSE)
		{
			header('HTTP/1.1 400 Bad Request');
			throw new SLIRException('getimagesize failed (source file may not '
				. 'be an image): ' . SLIR_DOCUMENT_ROOT . $this->imagePath);
		}

		$info['width']	=& $info[0];
		$info['height']	=& $info[1];
		$info['ratio']	= $info['width']/$info['height'];

		// IPTC
		if(is_array($extraInfo) && isset($extraInfo['APP13']))
				$info['iptc']	= iptcparse($extraInfo['APP13']);

		return $info;
	} // getImageInfo()

	/**
	 * Checks the image info and image's mime type to see if it is an image
	 *
	 * @since 2.0
	 * @return bool
	 */
	private function isImage()
	{
		if ($this->source !== FALSE
			|| substr($this->source['mime'], 0, 6) == 'image/')
			return TRUE;
		else
			return FALSE;
	} // isImage()

	/**
	 * Checks parameters against the image's attributes and determines whether
	 * anything needs to be changed or if we simply need to serve up the source
	 * image
	 *
	 * @since 2.0
	 * @return bool
	 * @todo Add check for JPEGs and progressiveness
	 */
	private function isSourceImageDesired()
	{
		if ($this->isWidthDifferent()
			|| $this->isHeightDifferent()
			|| $this->isBackgroundFillOn()
			|| $this->isQualityOn()
			|| $this->isCroppingNeeded()
			)
			return FALSE;
		else
			return TRUE;

	} // isSourceImageDesired()

	/**
	 * @since 2.0
	 * @return bool
	 */
	private function isWidthDifferent()
	{
		if ($this->maxWidth !== NULL
			&& $this->maxWidth < $this->source['width']
			)
			return TRUE;
		else
			return FALSE;
	} // isWidthDifferent()

	/**
	 * @since 2.0
	 * @return bool
	 */
	private function isHeightDifferent()
	{
		if ($this->maxHeight !== NULL
			&& $this->maxHeight < $this->source['height']
			)
			return TRUE;
		else
			return FALSE;
	} // isHeightDifferent()

	/**
	 * @since 2.0
	 * @return bool
	 */
	private function isBackgroundFillOn()
	{
		if ($this->backgroundFillColor !== NULL
			&& ($this->isSourceGIF() || $this->isSourcePNG())
			)
			return TRUE;
		else
			return FALSE;
	} // isBackgroundFillOn()

	/**
	 * @since 2.0
	 * @return boolean
	 */
	private function isQualityOn()
	{
		if ($this->quality !== NULL)
			return TRUE;
		else
			return FALSE;
	} // isQualityOn()

	/**
	 * @since 2.0
	 * @return boolean
	 */
	private function isCroppingNeeded()
	{
		if ($this->cropRatio['width'] !== NULL
			&& $this->cropRatio['height'] !== NULL
			&& $this->cropRatio['ratio'] != $this->source['ratio']
			)
			return TRUE;
		else
			return FALSE;
	} // isCroppingNeeded()

	/**
	 * @since 2.0
	 * @parram array $imageArray
	 * @param string $type Can be 'JPEG', 'GIF', or 'PNG'
	 * @return boolean
	 */
	private function isImageOfType($imageArray, $type = 'JPEG')
	{
		$method	= "is$type";
		if (method_exists($this, $method) && isset($imageArray['mime']))
			return $this->$method($imageArray['mime']);
	} // isImageOfType()

	/**
	 * @since 2.0
	 * @param string $mimeType
	 * @return boolean
	 */
	private function isJPEG($mimeType)
	{
		if ($mimeType == 'image/jpeg')
			return TRUE;
		else
			return FALSE;
	} // isJPEG()

	/**
	 * @since 2.0
	 * @param string $mimeType
	 * @return boolean
	 */
	private function isGIF($mimeType)
	{
		if ($mimeType == 'image/gif')
			return TRUE;
		else
			return FALSE;
	} // isGIF()

	/**
	 * @since 2.0
	 * @param string $mimeType
	 * @return boolean
	 */
	private function isPNG($mimeType)
	{
		if (in_array($mimeType, array('image/png', 'image/x-png')))
			return TRUE;
		else
			return FALSE;
	} // isPNG()

	/**
	 * @since 2.0
	 * @return boolean
	 */
	private function isSourceJPEG()
	{
		return $this->isImageOfType($this->source, 'JPEG');
	} // isSourceJPEG()

	/**
	 * @since 2.0
	 * @return boolean
	 */
	private function isRenderedJPEG()
	{
		return $this->isImageOfType($this->rendered, 'JPEG');
	} // isRenderedJPEG()

	/**
	 * @since 2.0
	 * @return boolean
	 */
	private function isSourceGIF()
	{
		return $this->isImageOfType($this->source, 'GIF');
	} // isSourceGIF()

	/**
	 * @since 2.0
	 * @return boolean
	 */
	private function isRenderedGIF()
	{
		return $this->isImageOfType($this->rendered, 'GIF');
	} // isRenderedGIF()

	/**
	 * @since 2.0
	 * @return boolean
	 */
	private function isSourcePNG()
	{
		return $this->isImageOfType($this->source, 'PNG');
	} // isSourcePNG()

	/**
	 * @since 2.0
	 * @return boolean
	 */
	private function isRenderedPNG()
	{
		return $this->isImageOfType($this->rendered, 'PNG');
	} // isRenderedPNG()

	/**
	 * Computes and sets properties of the rendered image, such as the actual
	 * width, height, and quality
	 *
	 * @since 2.0
	 */
	private function getRenderProperties()
	{
		// Set default properties
		$this->rendered	= array(
			'width'		=> $this->source['width'],
			'height'	=> $this->source['height'],
			'quality'	=> 0,
			'offset'	=> array(
				'left'		=> 0,
				'top'		=> 0
			),
			'mime'				=> $this->source['mime'],
			'functions'	=> array(
				'create'	=> 'ImageCreateFromJpeg',
				'output'	=> 'ImageJpeg'
			),
			'sharpen'		=> TRUE,
			'progressive'	=> $this->progressiveJPEGs,
			'background'	=> $this->backgroundFillColor
		);

		// Cropping
		if ($this->isCroppingNeeded())
		{
			if ($this->cropRatio['ratio'] > $this->source['ratio'])
			{ // Image is too tall so we will crop the top and bottom
				$originalHeight						= $this->source['height'];
				$this->source['height']				= $this->source['width'] / $this->cropRatio['ratio'];
				$this->maxHeight					= min($this->maxHeight, $this->source['height']);
				$this->rendered['offset']['top']	= ($originalHeight - $this->source['height']) / 2;
			}
			else if ($this->cropRatio['ratio'] < $this->source['ratio'])
			{ // Image is too wide so we will crop off the left and right sides
				$originalWidth						= $this->source['width'];
				$this->source['width']				= $this->source['height'] * $this->cropRatio['ratio'];
				$this->maxWidth						= min($this->maxWidth, $this->source['width']);
				$this->rendered['offset']['left']	= ($originalWidth - $this->source['width']) / 2;
			}
		} // if

		// Setting up the ratios needed for resizing. We will compare these
		// below to determine how to resize the image (based on height or based
		// on width)
		$widthRatio		= $this->maxWidth / $this->source['width'];
		$heightRatio	= $this->maxHeight / $this->source['height'];

		if ($widthRatio * $this->source['height'] < $this->maxHeight)
		{ // Resize the image based on width
			$this->rendered['height']	= ceil($widthRatio * $this->source['height']);
			$this->rendered['width']	= $this->maxWidth;
		}
		else // Resize the image based on height
		{
			$this->rendered['width']	= ceil($heightRatio * $this->source['width']);
			$this->rendered['height']	= $this->maxHeight;
		} // if

		// Determine the quality of the output image
		$this->rendered['quality']		= ($this->quality !== NULL) ? $this->quality : SLIR_DEFAULT_QUALITY;

		// Set up the appropriate image handling functions based on the original
		// image's mime type
		switch ($this->source['mime'])
		{
			case 'image/gif':
				// We will be converting GIFs to PNGs to avoid transparency
				// issues when resizing GIFs
				// This is maybe not the ideal solution, but IE6 can suck it
				$this->rendered['functions']['create']	= 'ImageCreateFromGif';
				$this->rendered['functions']['output']	= 'ImagePng';
				// We need to convert GIFs to PNGs
				$this->rendered['mime']					= 'image/png';
				$this->rendered['sharpen']				= FALSE;
				$this->rendered['progressive']			= FALSE;

				// We are converting the GIF to a PNG, and PNG needs a
				// compression level of 0 (no compression) through 9
				$this->rendered['quality']				= round(10 - ($this->rendered['quality'] / 10));
			break;

			case 'image/x-png':
			case 'image/png':
				$this->rendered['functions']['create']	= 'ImageCreateFromPng';
				$this->rendered['functions']['output']	= 'ImagePng';
				$this->rendered['mime']					= $this->source['mime'];
				$this->rendered['sharpen']				= FALSE;
				$this->rendered['progressive']			= FALSE;

				// PNG needs a compression level of 0 (no compression) through 9
				$this->rendered['quality']				= round(10 - ($this->rendered['quality'] / 10));
			break;

			default:
				$this->rendered['functions']['create']	= 'ImageCreateFromJpeg';
				$this->rendered['functions']['output']	= 'ImageJpeg';
				$this->rendered['mime']					= $this->source['mime'];
				$this->rendered['progressive']			= ($this->progressiveJPEGs !== NULL) ? $this->progressiveJPEGs : SLIR_DEFAULT_QUALITY;
				$this->rendered['background']			= NULL;
			break;
		} // switch

	} // getRenderProperties()

	/**
	 * @since 2.0
	 * @return boolean
	 */
	private function isRenderedCached()
	{
		return $this->isCached($this->renderedCacheFilePath());
	} // isRenderedCached()

	/**
	 * @since 2.0
	 * @return boolean
	 */
	private function isRequestCached()
	{
		return $this->isCached($this->requestCacheFilePath());
	} // isRequestCached()

	/**
	 * @since 2.0
	 * @param string $cacheFilePath
	 * @return boolean
	 */
	private function isCached($cacheFilePath)
	{
		if (!file_exists($cacheFilePath))
			return FALSE;

		$cacheModified	= filemtime($cacheFilePath);

		if (!$cacheModified)
			return FALSE;

		$imageModified	= filemtime(SLIR_DOCUMENT_ROOT . $this->imagePath);

		if ($imageModified >= $cacheModified)
			return FALSE;
		else
			return TRUE;
	} // isCached()

	/**
	 * @since 2.0
	 * @return string
	 */
	private function renderedCacheFilename()
	{
		$cacheParams	= $this->rendered;
		if (isset($cacheParams['image']))
			unset($cacheParams['image']);

		return '/' . md5($this->imagePath . serialize($cacheParams));
	} // renderedCacheFilename()

	/**
	 * @since 2.0
	 * @return string
	 */
	private function renderedCacheFilePath()
	{
		return SLIR_CACHE_DIR . '/rendered' . $this->renderedCacheFilename();
	} // renderedCacheFilePath()

	/**
	 * @since 2.0
	 * @return string
	 */
	private function requestCacheFilename()
	{
		$cacheParams	= $_SERVER['REQUEST_URI'];
		return '/' . md5($cacheParams);
	} // requestCacheFilename()

	/**
	 * @since 2.0
	 * @return string
	 */
	private function requestCacheFilePath()
	{
		return SLIR_CACHE_DIR . '/request' . $this->requestCacheFilename();
	} // requestCacheFilePath()

	/**
	 * Write an image to the cache
	 *
	 * @since 2.0
	 * @param string $imageData
	 * @return boolean
	 */
	private function cache($imageData)
	{
		$imageData	= $this->cacheRendered($imageData);
		
		if (SLIR_USE_REQUEST_CACHE)
			return $this->cacheRequest($imageData, FALSE);
		else
			return $imageData;
	} // cache()

	/**
	 * Write an image to the cache based on the properties of the rendered image
	 *
	 * @since 2.0
	 * @param string $imageData
	 * @param boolean $copyEXIF
	 * @return string
	 */
	private function cacheRendered($imageData, $copyEXIF = TRUE)
	{
		return $this->cacheFile($this->renderedCacheFilePath(), $imageData, $copyEXIF);
	} // cacheRendered()

	/**
	 * Write an image to the cache based on the request URI
	 *
	 * @since 2.0
	 * @param string $imageData
	 * @param boolean $copyEXIF
	 * @return string
	 */
	private function cacheRequest($imageData, $copyEXIF = TRUE)
	{
		return $this->cacheFile($this->requestCacheFilePath(), $imageData, $copyEXIF, $this->renderedCacheFilePath());
	} // cacheRequest()

	/**
	 * Write an image to the cache based on the properties of the rendered image
	 *
	 * @since 2.0
	 * @param string $cacheFilePath
	 * @param string $imageData
	 * @param boolean $copyEXIF
	 * @param string $symlinkToPath
	 * @return string
	 */
	private function cacheFile($cacheFilePath, $imageData, $copyEXIF = TRUE, $symlinkToPath = NULL)
	{
		$this->initializeCache();

		// Try to create just a symlink to minimize disk space
		if ($symlinkToPath && @symlink($symlinkToPath, $cacheFilePath))
			return $imageData;

		// Create the file
		file_put_contents($cacheFilePath, $imageData);

		if (SLIR_COPY_EXIF && $copyEXIF && $this->isSourceJPEG())
		{
			// Copy IPTC data
			if (isset($this->source['iptc']))
			{
				$imageData	= $this->copyIPTC($cacheFilePath);
				file_put_contents($cacheFilePath, $imageData);
			} // if

			// Copy EXIF data
			$this->copyEXIF($cacheFilePath);
		} // if

		return $imageData;
	} // cacheFile()

	/**
	 * Copy the source image's EXIF information to the new file in the cache
	 *
	 * @since 2.0
	 * @uses PEL
	 * @param string $cacheFilePath
	 */
	private function copyEXIF($cacheFilePath)
	{
		require_once('./pel-0.9.1/PelJpeg.php');
		$jpeg	= new PelJpeg(SLIR_DOCUMENT_ROOT . $this->imagePath);
		$exif	= $jpeg->getExif();
		if ($exif)
		{
			$jpeg	= new PelJpeg($cacheFilePath);
			$jpeg->setExif($exif);
			file_put_contents($cacheFilePath, $jpeg->getBytes());
		} // if
	} // copyEXIF()

	/**
	 * Makes sure the cache directory exists, is readable, and is writable
	 *
	 * @since 2.0
	 * @return boolean
	 */
	private function initializeCache()
	{
		if ($this->isCacheInitialized)
			return TRUE;

		$this->initializeDirectory(SLIR_CACHE_DIR);
		$this->initializeDirectory(SLIR_CACHE_DIR . '/rendered', FALSE);
		$this->initializeDirectory(SLIR_CACHE_DIR . '/request', FALSE);

		$this->isCacheInitialized	= TRUE;
		return TRUE;
	} // initializeCache()

	/**
	 * @since 2.0
	 * @param string $path Directory to initialize
	 * @param boolean $verifyReadWriteability
	 * @return boolean
	 */
	private function initializeDirectory($path, $verifyReadWriteability = TRUE)
	{
		if (!file_exists($path))
			mkdir($path, 0755);

		if (!$verifyReadWriteability)
			return TRUE;

		// Make sure we can read and write the cache directory
		if (!is_readable($path))
		{
			header('HTTP/1.1 500 Internal Server Error');
			throw new SLIRException("Directory ($path) is not readable");
		}
		else if (!is_writable($path))
		{
			header('HTTP/1.1 500 Internal Server Error');
			throw new SLIRException("Directory ($path) is not writable");
		}

		return TRUE;
	} // initializeDirectory()

	/**
	 * Serves the unmodified source image
	 *
	 * @since 2.0
	 */
	private function serveSourceImage()
	{
		$data			= file_get_contents(SLIR_DOCUMENT_ROOT . $this->imagePath);
		$lastModified	= filemtime(SLIR_DOCUMENT_ROOT . $this->imagePath);
		$this->serveFile($data, $lastModified, $this->source['mime'], 'source');
		exit();
	} // serveSourceImage()

	/**
	 * Serves the image from the cache based on the properties of the rendered
	 * image
	 *
	 * @since 2.0
	 */
	private function serveRenderedCachedImage()
	{
		return $this->serveCachedImage($this->renderedCacheFilePath(), 'rendered');
	} // serveRenderedCachedImage()

	/**
	 * Serves the image from the cache based on the request URI
	 *
	 * @since 2.0
	 */
	private function serveRequestCachedImage()
	{
		return $this->serveCachedImage($this->requestCacheFilePath(), 'request');
	} // serveRequestCachedImage()

	/**
	 * Serves the image from the cache
	 *
	 * @since 2.0
	 * @param string $cacheFilePath
	 * @param string $cacheType Can be 'request' or 'image'
	 */
	private function serveCachedImage($cacheFilePath, $cacheType)
	{
		$data			= @file_get_contents($cacheFilePath);

		// This will prevent an error from being thrown if the result of our
		// file_exists() when checking the cache was cached and incorrect
		if (!$data)
			return FALSE;

		$lastModified	= filemtime($cacheFilePath);
		$this->serveFile($data, $lastModified, $this->rendered['mime'], "$cacheType cache");

		if ($cacheType != 'request')
			$this->cacheRequest($data, FALSE);

		exit();
	} // serveCachedImage()

	/**
	 * Serves the rendered image
	 *
	 * @since 2.0
	 */
	private function serveRenderedImage()
	{
		// Put the data of the resized image into a variable
		ob_start();
		$this->rendered['functions']['output'](
			$this->rendered['image'],
			NULL,
			$this->rendered['quality']
		);
		$imageData	= ob_get_contents();
		ob_end_clean();

		// Cache the image
		$imageData	= $this->cache($imageData);

		// Serve the file
		$this->serveFile($imageData, gmdate('U'), $this->rendered['mime'], 'rendered');

		// Clean up memory
		ImageDestroy($this->source['image']);
		ImageDestroy($this->rendered['image']);

		exit();
	} // serveRenderedImage()

	/**
	 * Serves a file
	 *
	 * @since 2.0
	 * @param string $data Data of file to serve
	 * @param integer $lastModified Timestamp of when the file was last modified
	 * @param string $mimeType
	 * @param string $SLIRheader
	 */
	private function serveFile($data, $lastModified, $mimeType, $SLIRHeader)
	{
		$length	= strlen($data);

		$this->serveHeaders(
			$this->lastModified($lastModified),
			$mimeType,
			$length,
			$SLIRHeader
		);

		//  Send the image to the browser in bite-sized chunks
		$chunkSize	= 1024 * 8;
		$fp			= fopen('php://memory', 'r+b');
		fwrite($fp, $data);
		rewind($fp);
		while (!feof($fp))
		{
			echo fread($fp, $chunkSize);
			flush();
		} // while
		fclose($fp);
	} // serveFile()

	/**
	 * Serves headers for file for optimal browser caching
	 *
	 * @since 2.0
	 * @param string $lastModified Time when file was last modified in 'D, d M Y H:i:s' format
	 * @param string $mimeType
	 * @param integer $fileSize
	 * @param string $SLIRHeader
	 */
	private function serveHeaders($lastModified, $mimeType, $fileSize, $SLIRHeader)
	{
		header("Last-Modified: $lastModified");
		header("Content-Type: $mimeType");
		header("Content-Length: $fileSize");

		// Lets us easily know whether the image was rendered from scratch,
		// from the cache, or served directly from the source image
		header("Content-SLIR: $SLIRHeader");

		// Keep in browser cache how long?
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + SLIR_BROWSER_CACHE_EXPIRES_AFTER_SECONDS) . ' GMT');

		// Public in the Cache-Control lets proxies know that it is okay to
		// cache this content. If this is being served over HTTPS, there may be
		// sensitive content and therefore should probably not be cached by
		// proxy servers.
		header('Cache-Control: max-age=' . SLIR_BROWSER_CACHE_EXPIRES_AFTER_SECONDS . ', public');

		$this->doConditionalGet($lastModified);

		// The "Connection: close" header allows us to serve the file and let
		// the browser finish processing the script so we can do extra work
		// without making the user wait. This header must come last or the file
		// size will not properly work for images in the browser's cache
		header('Connection: close');
	} // serveHeaders()

	/**
	 * Converts a UNIX timestamp into the format needed for the Last-Modified
	 * header
	 *
	 * @since 2.0
	 * @param integer $timestamp
	 * @return string
	 */
	private function lastModified($timestamp)
	{
		return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
	} // lastModified()

	/**
	 * Checks the to see if the file is different than the browser's cache
	 *
	 * @since 2.0
	 * @param string $lastModified
	 */
	private function doConditionalGet($lastModified)
	{
		$ifModifiedSince = (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) ?
			stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']) :
			FALSE;

		if (!$ifModifiedSince || $ifModifiedSince != $lastModified)
			return;

		// Nothing has changed since their last request - serve a 304 and exit
		header('HTTP/1.1 304 Not Modified');

		// Serve a "Connection: close" header here in case there are any
		// shutdown functions that have been registered with
		// register_shutdown_function()
		header('Connection: close');

		exit();
	} // doConditionalGet()

} // class SLIR

require 'slirexception.class.php';
set_error_handler(array('SLIRException', 'error'));

// old pond
// a frog jumps
// the sound of water

// �Matsuo Basho
?>