# Auth Otp Api

#### Package to build auth based api with otp verfication

## Requirements

- PHP >= 7.4
- Laravel >= 8.5
- Laravel Sanctum for Laravel <= 8.5 and lower

## Installation

Install location using `composer require`:

```bash
composer require ma/auth-otp-api
```

Add the service provider in `config/app.php`:


```php
Ma\AuthOtpApi\AuthApiServiceProvider::class,
```

Publish the configuration file (this will create a `auth-otp-api.php` file inside the `config/` directory :

```bash
php artisan vendor:publish --provider="Ma\AuthOtpApi\AuthApiServiceProvider"
```

add `api` auth guard in `auth.php` file :
```
'guards' => [
        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],
```
