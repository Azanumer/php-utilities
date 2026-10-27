<?php
require __DIR__ . '/../src/Color.php';

$brand = Color::fromHex('#2563eb');
echo $brand->toHex() . "\n";                       // #2563eb
echo $brand->lighten(20)->toHex() . "\n";          // lighter tint
echo $brand->darken(20)->toHex() . "\n";            // darker shade
echo implode(',', $brand->toHsl()) . "\n";         // h,s,l
echo $brand->bestTextColor() . "\n";               // #ffffff (white reads best)
echo $brand->contrastRatio(Color::fromHex('#ffffff')) . "\n";
echo Color::fromHex('#f80')->toHex() . "\n";       // 3-digit shorthand -> #ff8800
