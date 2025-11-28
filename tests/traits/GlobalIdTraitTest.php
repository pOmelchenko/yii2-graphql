<?php

namespace yiiunit\extensions\graphql\traits;

use yiiunit\extensions\graphql\TestCase;

class GlobalIdTraitTest extends TestCase
{
    public function testEncodeDecode()
    {
        $helper = new GlobalIdHelper();
        $encoded = $helper->encodeGlobalId('User', 5);

        $this->assertSame(['User', '5'], $helper->decodeGlobalId($encoded));
        $this->assertSame('5', $helper->decodeRelayId($encoded));
        $this->assertSame('User', $helper->decodeRelayType($encoded));
    }
}

class GlobalIdHelper
{
    use \yii\graphql\traits\GlobalIdTrait;
}
