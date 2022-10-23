# filters

Filters API for designing and creating plugin system, within Laravel.

> We used this in Blessing Skin Server.

The "Filters API" is similar with Filters API of WordPress, but comes with different API.
And this package is designed for Laravel, so it may not work if you use it without Laravel.

## 💿 Install

Run Composer:

```
composer require blessing/filter
```

## 🔨 Usage

With Laravel's Auto-Discovery, you don't need to configure your Laravel application manually.

Currently this package doesn't provide Facade.
You must get instance by using type-hint in your controllers or using global `resolve()` helper function.

For example:

```php
use Blessing\Filter;

class MyController extends Controller
{
    public function home(Filter $filter)
    {
        //
    }
}
```

### Add a filter

To add a filter for a specified hook, just call the `add` method:

```php
$filter->add('hook_name', function ($value) {
    return $value;
});
```

Note that the filter handler must return a value; otherwise, the value after applied will be `null`.

You also can pass a class which has a public method called `filter` as handler.

```php
class MyFilter
{
    public function filter($value)
    {
        return $value;
    }
}

$filter->add('hook_name', MyFilter::class);
// or
$filter->add('hook_name', 'MyFilter');
```

The class will be resolved from Laravel's service container,
so you can use type-hint at the constructor of your class to resolve dependencies.

Additionally, you can specify the priority for your filter handler.
Higher integer value indicates that it should come with higher priority.

Default priority is `20`.

```php
$filter->add('hook_name', function ($value) {
    return $value;
}, 30);  // Higher than default priority.
```

### Apply a hook

You can call `apply` method to apply a hook:

```php
$value = $filter->apply('hook_name', 'hi');
```

Then, the second argument you passed will be manipulated by filters.

Also, you can pass additional arguments as an array:

```php
$value = $filter->apply('hook_name', 'hi', [$arg1, $arg2]);
```

Those additional arguments **won't** be manipulated by filters.

### Remove all filters

To remove all filters for a specified hook, just:

```php
$filter->remove('hook_name');
```

### Totally...

This is a full example:

```php
$filter->add('hook_name', function ($value, $arg1, $arg2) {
    if ($arg1 === '...') {
        return $value;
    }

    return $value.'!';
});

$value = $filter->apply('hook_name', 'hi', ['abc', 'def']);
// You should get the text "hi!" here.
```

## 📄 License

MIT License (c) The Blessing Skin Team
