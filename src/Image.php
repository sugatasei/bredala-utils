<?php

namespace Bredala\Utils;

use Imagick;
use ImagickException;

class Image extends Imagick
{
    /**
     * Background color
     *
     * @var string
     */
    private $bgColor = "#000";

    /**
     * Compression quality
     * @var int
     */
    private $quality = 75;

    /**
     * Image size used to calculate the hash
     * @var integer
     */
    private int $hashSize = 16;

    // -------------------------------------------------------------------------

    public function __construct(...$files)
    {
        parent::__construct($files ?: null);
        self::setResourceLimit(self::RESOURCETYPE_THREAD, 1);
    }

    public static function create(...$files): static
    {
        return new static(...$files);
    }

    // -------------------------------------------------------------------------

    /**
     * @return string
     */
    public function getBgColor(): string
    {
        return $this->bgColor;
    }

    /**
     * @return integer
     */
    public function getQuality(): int
    {
        return $this->quality;
    }

    /**
     * Retourne le nom de l'image sans l'extension
     *
     * @return string
     */
    public function getName(): string
    {
        return pathinfo($this->getImageFilename(), PATHINFO_FILENAME);
    }

    public function getFileSize(): int
    {
        return filesize($this->getImageFilename()) ?: 0;
    }

    /**
     * Get mime type
     *
     * @return string
     */
    public function getImageMimeType(): string
    {
        return str_replace('image/x-', 'image/', parent::getImageMimeType());
    }

    /**
     * Permets de forcer la génération de l'image avant de récuperer son poids
     *
     * @return integer
     */
    public function getImageLength(): int
    {
        $this->getImageBlob();
        return parent::getImageLength();
    }

    // -------------------------------------------------------------------------

    /**
     * @param string $color
     * @return \static
     */
    public function setBgColor(string $color)
    {
        $this->bgColor = $color;
        return $this;
    }

    /**
     * @param integer $quality
     * @return \static
     */
    public function setQuality(int $quality)
    {
        $this->quality = $quality;
        return $this;
    }

    // -------------------------------------------------------------------------

    /**
     * Prepare image after upload
     *
     * @return static
     */
    public function prepare(): static
    {
        return $this->cmynToRgb()->stripExif();
    }

    /**
     * Convert colorspace from CMYK to RGB
     *
     * @return static
     */
    public function cmynToRgb(): static
    {
        if ($this->getImageColorspace() == Imagick::COLORSPACE_CMYK) {
            $this->setImageColorspace(Imagick::COLORSPACE_CMYK);
            $this->profileImage('*', NULL);
            $this->setImageColorspace(Imagick::COLORSPACE_SRGB);
            $this->negateImage(FALSE, Imagick::CHANNEL_ALL);
        }

        return $this;
    }

    /**
     * Strip EXIF data
     *
     * @return static
     */
    public function stripExif(): static
    {
        $profiles = $this->getImageProfiles("icc", true);
        $res = $this->stripimage();
        if ($res && !empty($profiles)) {
            $this->profileImage("icc", $profiles['icc']);
        }

        return $this;
    }

    public function isInside(int $width, int $height, bool $strict_orientation = false): bool
    {
        $curWidth = $this->getImageWidth();
        $curHeight = $this->getImageHeight();

        // Orientation are not the same
        if (!$strict_orientation && ($width > $height) !== ($curWidth > $curHeight)) {
            $tmp    = $width;
            $width  = $height;
            $height = $tmp;
        }

        // Nothing to do
        return $width >= $curWidth && $height >= $curHeight;
    }

    /**
     * Resize an image
     *
     * @param integer $width
     * @param integer $height
     * @param bool $strict_orientation
     * @return static
     */
    public function fitBounds(int $width, int $height, bool $strict_orientation = false): static
    {
        $curWidth = $this->getImageWidth();
        $curHeight = $this->getImageHeight();

        // Orientation are not the same
        if (!$strict_orientation && ($width > $height) !== ($curWidth > $curHeight)) {
            $tmp    = $width;
            $width  = $height;
            $height = $tmp;
        }

        // Nothing to do
        if ($width >= $curWidth && $height >= $curHeight) {
            return $this;
        }

        $this->resizeimage($width, $height, Imagick::FILTER_LANCZOS, 1, true);

        return $this;
    }

    /**
     * Transformation d'une image au format JPEG
     *
     * @return static
     */
    public function toJpeg(bool $force = false): static
    {
        if ($force || !$this->hasFormat('jpeg')) {
            $this->setimageformat('jpeg');
            $this->setimagecompression(Imagick::COMPRESSION_JPEG);
            $this->setimagecompressionquality($this->getQuality());
        }

        return $this;
    }

    /**
     * Transformation d'une image au format PNG
     *
     * @return static
     */
    public function toPng(bool $force = false): static
    {
        if ($force || !$this->hasFormat('png')) {
            $this->setImageFormat('png');
            $this->setImageCompression(Imagick::COMPRESSION_ZIP);
        }

        return $this;
    }

    /**
     * Transformation d'une image au format WEBP
     *
     * @return static
     */
    public function toWebp(bool $force = false): static
    {
        if ($force || !$this->hasFormat('webp')) {
            $this->setImageFormat('webp');
            $this->setOption('webp:method', '6');
            $this->setOption('webp:low-memory', 'false');
            $this->setimagecompressionquality($this->getQuality());
        }

        return $this;
    }

    public function hasFormat(string $format): bool
    {
        try {
            return mb_strtolower($this->getImageFormat()) === $format;
        } catch (ImagickException $ex) {
            return false;
        }
    }

    /**
     * Crop Image
     *
     * @param array $data
     * @return static
     */
    public function crop(array $data): static
    {
        $x      = isset($data['x']) ? (float) $data['x'] : 0;
        $y      = isset($data['y']) ? (float) $data['y'] : 0;
        $width  = isset($data['width']) ? (float) $data['width'] : 0;
        $height = isset($data['height']) ? (float) $data['height'] : 0;
        $rotate = isset($data['rotate']) ? (int) $data['rotate'] : 0;
        $scaleX = isset($data['scaleX']) ? (int) $data['scaleX'] : 1;
        $scaleY = isset($data['scaleY']) ? (int) $data['scaleY'] : 1;

        // Flip
        if ($scaleX === -1) {
            $this->flipimage();
        }

        // Flop
        if ($scaleY === -1) {
            $this->flopimage();
        }

        // Rotate
        if ($rotate !== 0) {
            $this->rotateimage($this->bgColor, $rotate);
        }

        // Crop
        if ($width && $height) {
            $this->cropimage($width, $height, $x, $y);
        }

        return $this;
    }

    /**
     * Apply a watermark
     *
     * @param Image $img
     * @param int $x
     * @param int $y
     */
    public function watermark(Image $img, int $x, int $y): static
    {
        $composite = Imagick::COMPOSITE_DEFAULT;
        $this->compositeImage($img, $composite, $x, $y);
        return $this;
    }

    // -------------------------------------------------------------------------

    /**
     * Save. Return a new objet if filename changed
     *
     * @param string|null $to
     * @return static
     */
    public function save(?string $to = null): static
    {
        $from = $this->getImageFilename();
        $to = $this->to($to ?? $from);
        $this->writeImage($to);
        return $from === $to ? $this : new static($to);
    }

    /**
     * Format filename from image format
     *
     * @param string $to
     * @return string
     */
    private function to(string $to): string
    {
        $pathInfo = pathinfo($to);
        $ext = $this->getExtension();
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $ext;
    }

    /**
     * Get extension from image format
     *
     * @return string
     */
    private function getExtension(): string
    {
        $format = mb_strtolower($this->getImageFormat());
        return match ($format) {
            'jpeg' => 'jpg',
            default => $format
        };
    }

    // -------------------------------------------------------------------------
    // Comparaison d'images
    // -------------------------------------------------------------------------

    public function getHash()
    {

        $thumb = clone $this;
        $thumb->thumbnailImage($this->hashSize, $this->hashSize);
        $thumb->setImageType(Imagick::IMGTYPE_GRAYSCALE);

        $bits = $this->bits($thumb);

        return self::bits2hash($bits);
    }

    /**
     * Compare two hash
     *
     * @param string $hash1
     * @param string $hash2
     * @param float $percent
     * @return integer
     */
    public static function similarHash(string $hash1, string $hash2, float &$percent = 0): int
    {
        if ($hash1 === $hash2) {
            $percent = 100;
            return 0;
        }

        $bits1 = self::hash2bits($hash1);
        $bits2 = self::hash2bits($hash2);

        $len1 = count($bits1);
        $len2 = count($bits2);
        $len = max($len1, $len2);

        $distance = 0;
        for ($i = 0; $i < $len; $i++) {
            if (($bits1[$i] ?? null) !== ($bits2[$i] ?? null)) {
                $distance++;
            }
        }

        $percent = 100 - ($distance / $len * 100);

        return $distance;
    }

    /**
     * Returns an array with 1 and zeros. If a color is bigger than the mean value of colors it is 1
     *
     * @param Imagick $image
     * @return array
     */
    private function bits(Imagick $image): array
    {
        $colorList = $this->colorList($image);
        $colorMean = $this->colorMean($colorList);

        $bits = [];
        foreach ($colorList as $color) {
            $bits[] = ($color >= $colorMean) ? 1 : 0;
        }

        return $bits;
    }

    /**
     * Returns the color mean value of a list of colors
     *
     * @param array $colorList
     * @return float
     */
    private function colorMean(array $colorList): float
    {
        return array_sum($colorList) / ($this->hashSize * $this->hashSize);
    }

    /**
     * Returns the list of all pixel's colors
     *
     * @param Imagick $image
     */
    private function colorList(Imagick $image): array
    {
        $colorList = [];

        for ($a = 0; $a < $this->hashSize; $a++) {
            for ($b = 0; $b < $this->hashSize; $b++) {
                $colorList[] = $image
                    ->getImagePixelColor($a, $b)
                    ->getColorValue(Imagick::COLOR_RED);
            }
        }

        return $colorList;
    }

    /**
     * Convert array of bits into hexadecimal string
     *
     * @param array $bits
     * @return string
     */
    private static function bits2hash(array $bits): string
    {
        $str = join('', $bits);

        $chunk = array_chunk($bits, 4);
        $hash = '';

        foreach ($chunk as $part) {
            $str = join('', $part);
            $hex = dechex(bindec($str));
            $hash .= $hex;
        }

        return $hash;
    }

    /**
     * Convert hexadecimal hash string into array of bits
     *
     * @param string $hash
     * @return array
     */
    private static function hash2bits(string $hash): array
    {
        $bits = '';
        $len = mb_strlen($hash);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad((string)decbin(hexdec($hash[$i])), 4, STR_PAD_LEFT);
        }

        return str_split($bits);
    }

    // -------------------------------------------------------------------------
}
