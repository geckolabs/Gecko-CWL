# AWS CloudWatch Logs Handler for Monolog

<!--
[![Actions Status](https://github.com/maxbanton/cwh/workflows/Pipeline/badge.svg)](https://github.com/maxbanton/cwh/actions)
[![Coverage Status](https://img.shields.io/coveralls/maxbanton/cwh/master.svg)](https://coveralls.io/github/maxbanton/cwh?branch=master)
[![License](https://img.shields.io/packagist/l/maxbanton/cwh.svg)](https://github.com/maxbanton/cwh/blob/master/LICENSE)
[![Version](https://img.shields.io/packagist/v/maxbanton/cwh.svg)](https://packagist.org/packages/maxbanton/cwh)
[![Downloads](https://img.shields.io/packagist/dt/maxbanton/cwh.svg)](https://packagist.org/packages/maxbanton/cwh/stats)
-->

# Forked from [maxbanton/cwh](https://github.com/maxbanton/cwh)

Forked with the intended changes:

- Stop any GET requests to CWL
- Assume the LogGroup exists and don't waste resources checking for it
- Assuming we're always creating a new LogStream - we'll surpass the RPS for putting logs to a single stream so always create a new one per container

Handler for PHP logging library [Monolog](https://github.com/Seldaek/monolog) for sending log entries to 
[AWS CloudWatch Logs](http://docs.aws.amazon.com/AmazonCloudWatch/latest/logs/WhatIsCloudWatchLogs.html) service.

Before using this library, it's recommended to get acquainted with the [pricing](https://aws.amazon.com/en/cloudwatch/pricing/) for AWS CloudWatch services.

Please press **&#9733; Star** button if you find this library useful.

## Requirements
* PHP ^7.2
* AWS account with proper permissions (see [list of permissions below](#AWS IAM needed permissions))

## Features
* Up to 10000 batch logs sending in order to avoid _Rate exceeded_ errors 
* Avoid unnecessary queries to CloudWatch by assuming the LogGroup exists and that the LogStream needs created
* Will query LogStreams to refresh sequence token, but this should never be required
* Suitable for web applications and for long-living CLI daemons and workers

## Installation
Install the latest version with [Composer](https://getcomposer.org/) by running

```bash
# TODO: This isn't published to packagist yet
# $ composer require maxbanton/cwh:^2.0
```

## Basic Usage
```php
<?php

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Maxbanton\Cwh\Handler\CloudWatch;
use Monolog\Logger;
use Monolog\Formatter\JsonFormatter;

$sdkParams = [
    'region' => 'eu-west-1',
    'version' => 'latest',
    'credentials' => [
        'key' => 'your AWS key',
        'secret' => 'your AWS secret',
        'token' => 'your AWS session token', // token is optional
    ]
];

// Instantiate AWS SDK CloudWatch Logs Client
$client = new CloudWatchLogsClient($sdkParams);

// Log group name, will be created if none
$groupName = 'php-logtest';

// Log stream name, will be created if none
$streamName = 'ec2-instance-1';

// Instantiate handler (tags are optional)
$handler = new CloudWatch($client, $groupName, $streamName, 10000);

// Optionally set the JsonFormatter to be able to access your log messages in a structured way
$handler->setFormatter(new JsonFormatter());

// Create a log channel
$log = new Logger('name');

// Set handler
$log->pushHandler($handler);

// Add records to the log
$log->debug('Foo');
$log->warning('Bar');
$log->error('Baz');
```

## Frameworks integration
 - [Silex](http://silex.sensiolabs.org/doc/master/providers/monolog.html#customization)
 - [Symfony](http://symfony.com/doc/current/logging.html) ([Example](https://github.com/maxbanton/cwh/issues/10#issuecomment-296173601))
 - [Lumen](https://lumen.laravel.com/docs/5.2/errors)
 - [Laravel](https://laravel.com/docs/5.4/errors) ([Example](https://stackoverflow.com/a/51790656/1856778))
  
 [And many others](https://github.com/Seldaek/monolog#framework-integrations)
 
# AWS IAM needed permissions
if you prefer to use a separate programmatic IAM user (recommended) or want to define a policy, make sure following permissions are included:
1. `CreateLogGroup` [aws docs](https://docs.aws.amazon.com/AmazonCloudWatchLogs/latest/APIReference/API_CreateLogGroup.html)
1. `CreateLogStream` [aws docs](https://docs.aws.amazon.com/AmazonCloudWatchLogs/latest/APIReference/API_CreateLogStream.html)
1. `PutLogEvents` [aws docs](https://docs.aws.amazon.com/AmazonCloudWatchLogs/latest/APIReference/API_PutLogEvents.html)
1. `PutRetentionPolicy` [aws docs](https://docs.aws.amazon.com/AmazonCloudWatchLogs/latest/APIReference/API_PutRetentionPolicy.html)
1. `DescribeLogStreams` [aws docs](https://docs.aws.amazon.com/AmazonCloudWatchLogs/latest/APIReference/API_DescribeLogStreams.html)
1. `DescribeLogGroups` [aws docs](https://docs.aws.amazon.com/AmazonCloudWatchLogs/latest/APIReference/API_DescribeLogGroups.html)

When setting the `$createGroup` argument to `false`, permissions `DescribeLogGroups` and `CreateLogGroup` can be omitted

## AWS IAM Policy full json example
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "logs:DescribeLogStreams",
            ],
            "Resource": "{LOG_GROUP_ARN}"
        },
        {
            "Effect": "Allow",
            "Action": [
                "logs:PutLogEvents"
            ],
            "Resource": [
                "{LOG_STREAM_1_ARN}",
                "{LOG_STREAM_2_ARN}"
            ]
        }
    ]
}
```

## Issues
Feel free to [report any issues](https://github.com/maxbanton/cwh/issues/new)

## Contributing
Please check [this document](https://github.com/maxbanton/cwh/blob/master/CONTRIBUTING.md)

___

Made in Ukraine 🇺🇦
