<?php

namespace apolle\yii2\galleryManager;

class GalleryImage
{
    public $label;
    public $content;
    public $id;
    public $position;
    public $link;
    /**
     * @var GalleryBehavior
     */
    protected $galleryBehavior;

    /**
     * @param GalleryBehavior $galleryBehavior
     * @param array           $props
     */
    function __construct(GalleryBehavior $galleryBehavior, array $props)
    {

        $this->galleryBehavior = $galleryBehavior;

        $this->label = isset($props['label']) ? $props['label'] : '';
        $this->content = isset($props['content']) ? $props['content'] : '';
        $this->id = isset($props['id']) ? $props['id'] : '';
        $this->position = isset($props['position']) ? $props['position'] : '';
        $this->link = isset($props['link']) ? $props['link'] : '';

    }

    /**
     * @param string $version
     *
     * @return string
     */
    public function getUrl($version)
    {
        return $this->galleryBehavior->getUrl($this->id, $version);
    }
}
