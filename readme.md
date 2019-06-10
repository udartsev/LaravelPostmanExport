# Laraman

Based on [Laraman](https://github.com/RL-Studio/laraman)

**Updates:** 
1. Postman collection scheme
2. Artisan consol commands
3. Can post project **URL** address via console
4. Basic file name change to **postman_collection.json**

## Installation
Install via composer:
```
composer require --dev udartsev/laravel-postman-export
```

Add the service provider to your `providers` array in `config/app.php`

```php
udartsev\laravel-postman-export\PostmanServiceProvider::class,
```

That's all!

## Usage

```
php artisan postman:export
```

This will create a `postman_collection.json` inside your `storage/app` folder. You are free to change the name of the file by specifying the filename as follows:

```
php artisan laraman:export --name=my-app
```