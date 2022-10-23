# texture-renderer

Minecraft texture renderer written in pure PHP.

## Install

```
composer require blessing/texture-renderer
```

## Usage

At all the code examples below, we assume you've prepared your texture source as the variable `$source`.
The source can be a file path, a URL, or a string represents texture binary.

### High Level API

All the high level APIs will return a GD resource.

```php
use Blessing\Minecraft;

$m = new Minecraft();
$resource = $m->renderSkin($source, /* optional */ $ratio, /* optional */ $isAlex);
$resource = $m->renderCape($source, $height);
$resource = $m->render2dAvatar($source, /* optional */ $ratio);
$resource = $m->render3dAvatar($source, /* optional */ $ratio);
```

### Low Level API

This library contains two renderers: skin renderer and cape renderer.

#### Skin Renderer

The constructor of the skin renderer can accept many parameters (all are optional).
For example, you can specify `$ratio`, `$headOnly`, `$horizontalRotation`, `$verticalRotation`.
For details, please check out the source code.

```php
use Blessing\Renderer\SkinRenderer;

$renderer = new SkinRenderer();
$resource = $renderer->render($source, $isAlex);  // returns GD resource
```

As you can see above, the second parameter of the `render` method will tell the renderer
whether your texture is of Alex model or not. Default value is `false`.

#### Cape Renderer

Two arguments below are necessary.

The `$height` stands for the height of rendered image.

```php
use Blessing\Renderer\CapeRenderer;

$renderer = new CapeRenderer();
$resource = $renderer->render($source, $height);  // returns GD resource
```

### Utility Functions

#### `isAlex`

This utility can be used to detect if a texture is an alex texture.

```php
use Blessing\Renderer\TextureUtil;

$isAlex = TextureUtil::isAlex($texture); // returns bool type
```

## License

MIT License

2020-present (c) The Blessing Skin Team
