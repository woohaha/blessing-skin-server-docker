# rejection

Rejection is an object that indicates you are rejecting.

> We used this in Blessing Skin Server for plugins.

## 💿 Install

Run Composer:

```
composer require blessing/rejection
```

## 🔨 Usage

### Create a rejection

```php
use Blessing\Rejection;

$rejection = new Rejection('reason');
```

You can pass optional second argument to constructor:

```php
$rejection = new Rejection('reason', ['name' => '']);
```

### Retrieve reason

```php
$rejection->getReason();
```

### Retrieve data:

```php
$rejection->getData();
```

If your data is an array, you pass a key:

```php
$rejection = new Rejection('reason', ['name' => '']);
$rejection->getData('name');
```

## 📄 License

MIT License (c) The Blessing Skin Team
