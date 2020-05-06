# googlecloud-pubsub-php-adapter

A Google Cloud PubSub driver for Laravel/Lumen Queue.

![CI](https://github.com/twipi-group/googlecloud-pubsub-laravel-queue-driver/workflows/CI/badge.svg)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.1-8892BF.svg?style=flat)](https://www.php.net/manual/fr/migration71.new-features.php)
[![Software License](https://img.shields.io/badge/license-MIT-green.svg?style=flat)](LICENSE)

## Installation
You can easily install this package via [Composer](https://getcomposer.org) using this command:
```bash
composer require twipi-group/googlecloud-pubsub-laravel-queue-driver
```
### Laravel
Register this package by adding the following line to the autoloaded service providers of your `config/app.php` file:

```php
TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\GcServiceProviderQueue::class,
```
### Lumen
For Lumen usage, the service provider should be registered manually as follow in your `boostrap/app.php` file:

```php
$app->configure('queue');
$app->register(TwipiGroup\GoogleCloudPubSubLaravelQueueDriver\GcServiceProviderQueue::class);
```

## Configuration

Add new connection named `gcpubsub` to your `config/queue.php` file. You can customize the following options directly from your `.env` file.

```php
'gcpubsub' => [
    'driver' => 'gcpubsub',
    'project_id' => env('PUBSUB_PROJECT_ID', 'google-cloud-project-id'), // Google cloud project id
    'queue' => env('PUBSUB_QUEUE_DEFAULT', 'default'), // Default queue name corresponding to the gc pubsub topic
    'topic_suffix' => env('PUBSUB_TOPIC_SUFFIX', ''),
    'subscriber_suffix' => env('PUBSUB_SUBSCRIBER_SUFFIX', ''),
    'max_tries' => env('PUBSUB_JOB_MAX_TRIES', 1), // Number of times the job may be attempted.
    'retry_delay' => env('PUBSUB_JOB_RETRY_DELAY', 0), // Delay in seconds before retrying a job that has failed 
],
```

## Running The Queue Worker
You may run the worker using the queue:work Artisan command. ([Laravel documentation](https://github.com/twipi-group/googlecloud-pubsub-laravel-queue-driver/workflows/CI/badge.svg))
```
php artisan queue:work gcpubsub [--queue=myqueue]
```
## Tests
```
vendor/bin/phpunit tests
```
## Contribution
You can contribute to this package by discovering bugs, opening issues or purpose new features.

## Licence
This project is licensed under the terms of the MIT license. See License file for more information.