<?php

namespace dyerc\monet\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\vue\VueAsset;

class MonetAssets extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = "@dyerc/monet/web/assets/dist/assets";

        $this->depends = [
            CpAsset::class,
            VueAsset::class,
        ];

        $this->js = [
            'monet.js'
        ];

        $this->css = [
            'monet.css'
        ];

        parent::init();
    }
}