<?php
/**
 * ImageTransform class
 * This class deals with creating thumbnails for image uploads using the a library
 *
 * @author David Yell <neon1024@gmail.com>
 */

namespace Proffer\Lib;

use Cake\ORM\Table;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;

class ImageTransform implements ImageTransformInterface
{

    /**
     * @var \Cake\ORM\Table $table Instance of the table being used
     */
    protected $Table;

    /**
     * @var \Proffer\Lib\ProfferPathInterface $Path Instance of the path class
     */
    protected $Path;

    /**
     * @var \Intervention\Image\ImageManager Intervention image manager instance
     */
    protected $ImageManager;

    /**
     * Construct the transformation class
     *
     * @param \Cake\ORM\Table $table The table instance
     * @param \Proffer\Lib\ProfferPathInterface $path Instance of the path class
     */
    public function __construct(Table $table, ProfferPathInterface $path)
    {
        $this->Table = $table;
        $this->Path = $path;
    }

    /**
     * Take an upload fields configuration and create all the thumbnails
     *
     * @param array $config The upload fields configuration
     * @return array
     */
    public function processThumbnails(array $config)
    {
        $thumbnailPaths = [];
        if (!isset($config['thumbnailSizes'])) {
            return $thumbnailPaths;
        }

        foreach ($config['thumbnailSizes'] as $prefix => $thumbnailConfig) {
            $method = 'gd';
            if (!empty($config['thumbnailMethod'])) {
                $method = $config['thumbnailMethod'];
            }

            $this->ImageManager = new ImageManager(['driver' => $method]);

            $thumbnailPaths[] = $this->makeThumbnail($prefix, $thumbnailConfig);
        }

        return $thumbnailPaths;
    }

    /**
     * Generate and save the thumbnail
     *
     * @param string $prefix The thumbnail prefix
     * @param array $config Array of thumbnail config
     * @return string
     */
    public function makeThumbnail($prefix, array $config)
    {
        $defaultConfig = [
            'jpeg_quality' => 100
        ];
        $config = array_merge($defaultConfig, $config);

        ////////////////////////////////
        //Mantener proporciones de la imagen según el parámetro pasado.
        ////////////////////////////////
        if (is_null($config['h'])){ //Alto null
            $size = getimagesize($this->Path->fullPath());
            $config['h'] = ($config['w'] * $size[1]) / $size[0];
        }

        if (is_null($config['w'])){ //Ancho null
            $size = getimagesize($this->Path->fullPath());
            $config['w'] = ($config['h'] * $size[0]) / $size[1];
        }

        ////////////////////////////////
        $width = $config['w'];
        $height = $config['h'];

        $image = $this->ImageManager->make($this->Path->fullPath());

        ////////////////////////////////
        //Comprobamos la rotación de la imagen antes de generar el thumbnail
        ////////////////////////////////
        $exif = exif_read_data($this->Path->fullPath());
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $this->rotateImage($image, 180);
                    break;

                case 6:
                    $this->rotateImage($image, -90);
                    $width = $config['h'];
                    $height = $config['w'];
                    break;

                case 8:
                    $this->rotateImage($image, 90);
                    $width = $config['h'];
                    $height = $config['w'];
                    break;
            }
        }
        ////////////////////////////////
        ////////////////////////////////

        if (!empty($config['crop'])) {
            $image = $this->thumbnailCrop($image, $width, $height);
        } elseif (!empty($config['fit'])) {
            $image = $this->thumbnailFit($image, $width, $height);
        } else {
            $image = $this->thumbnailResize($image, $width, $height);
        }

        unset($config['crop'], $config['w'], $config['h']);

        $image->save($this->Path->fullPath($prefix), $config['jpeg_quality']);

        return $this->Path->fullPath($prefix);
    }

    /**
     * Crop an image to a certain size from the centre of the image
     *
     * @see http://image.intervention.io/api/crop
     *
     * @param \Intervention\Image\Image $image Image instance
     * @param int $width Desired width in pixels
     * @param int $height Desired height in pixels
     *
     * @return \Intervention\Image\Image
     */
    protected function thumbnailCrop(Image $image, $width, $height)
    {
        return $image->crop($width, $height);
    }

    /**
     * Resize and crop to find the best fitting aspect ratio
     *
     * @see http://image.intervention.io/api/fit
     *
     * @param \Intervention\Image\Image $image Image instance
     * @param int $width Desired width in pixels
     * @param int $height Desired height in pixels
     *
     * @return \Intervention\Image\Image
     */
    protected function thumbnailFit(Image $image, $width, $height)
    {
        return $image->fit($width, $height);
    }

    /**
     * Resize current image
     *
     * @see http://image.intervention.io/api/resize
     *
     * @param \Intervention\Image\Image $image Image instance
     * @param int $width Desired width in pixels
     * @param int $height Desired height in pixels
     *
     * @return \Intervention\Image\Image
     */
    protected function thumbnailResize(Image $image, $width, $height)
    {
        return $image->resize($width, $height);
    }


    /**
     * Rotate current image
     *
     * @see http://image.intervention.io/api/rotate
     *
     * @param \Intervention\Image\Image $image Image instance
     * @param int $rotate Angle of rotation
     *
     * @return \Intervention\Image\Image
     */
    protected function rotateImage(Image $image, $rotate){
        return $image->rotate($rotate,0);
    }
}
