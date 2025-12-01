<?php

namespace yiiunit\extensions\graphql\data;

use GraphQL\Utils\Utils;

class Image
{
    public const TYPE_USERPIC = 'userpic';

    public const SIZE_ICON = 'icon';
    public const SIZE_SMALL = 'small';
    public const SIZE_MEDIUM = 'medium';
    public const SIZE_ORIGINAL = 'original';

    public $id;

    public $type;

    public $size;

    public $width;

    public $height;

    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
