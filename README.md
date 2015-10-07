# Amazon Web Services integration for TYPO3 Flow 
This packages provides you with a S3 resource storage and publishing target for the new TYPO3 Flow resource management. It also comes with handy little factory, which you can use to easily create clients for the various services.

**WARNING** This package is still under development. The configuration options may change or some things may not work as expected. Do not use this in an production environment.

## Installation

Just add the `jayvee/aws` package as a requirement to your root composer.json and run a `composer update` to install this package.

## Configuration

### Setting your AWS credentials

The recommended way to authenticate is to use instance profile credentials or environment variables. The [AWS User Guide](http://docs.aws.amazon.com/aws-sdk-php/v3/guide/guide/credentials.html) has more information about how you setup these. If somehow you can not use these authentication methods, you can also set your credentials in the Settings.yaml of this package.

```yaml
Jayvee:
  Aws:
    sdk:
      credentials:
        key: <access_key>
        secret: <secret_key>
      region: <preferred_region>
```

There is also an option to set your preferred AWS region, which defaults to `eu-east-1`if not set.

### Resource storage

The resource storage and publishing target can be defined within the settings of the TYPO3 Flow package. To simply set a S3 bucket as a resource storage, this is all you need. If the S3 bucket you specified does not exists, the storage will try to create it (the same applies to publishing targets).

```yaml
TYPO3:
  Flow:
    resource:
      storages:
        s3PersistentResourcesStorage:
          storage: 'Jayvee\Aws\Resource\Storage\S3Storage'
          storageOptions:
            bucketName: '<bucket_name>'
```

A storage won't need any further configuration and you are ready to go with the above configuration.

### Publishing targets

You can publish your resources either directly from your S3 bucket or with additional CloudFront support to use as a CDN. If you just want to serve your resources from S3, the following config will do this job.

```yaml
TYPO3:
  Flow:
    resource:
      targets:
        s3PersistentResourcesTarget:
          target: 'Jayvee\Aws\Resource\Target\S3Target'
          targetOptions:
            bucketName: '<bucket_name>'
```

*Note:* You can use one bucket as a storage and publishing target at the same time. Resources are stored with the ACL private by default and are set to public-read upon publishing. If you use different buckets, the resource will be copied to the bucket defined as the publishing target.

#### Adding CloudFront support

To serve your files via CloudFront and not directly from your S3 bucket, add the identifier of the distribution you want to use with the `cloudFront` option. Since creating a distribution is a longer running task, you will have to set it up yourself and set the S3 bucket as its origin, e.g. via the AWS Console.

```yaml
TYPO3:
  Flow:
    resource:
      targets:
        s3PersistentResourcesTarget:
          target: 'Jayvee\Aws\Resource\Target\S3Target'
          targetOptions:
            bucketName: '<bucket_name>'
            cloudFront:
              distributionIdentifier: '<distribution>
```

The target will read your distribution configuration and automatically use an origin access identity if available. Please note that you do not need to setup a bucket policy for the origin access identity since the permissions will be granted per object.

## Troubleshooting

Sometimes you may receive the following error during resource publishing:

```
An error occurred while publishing resources (see full description below). You can check and probably fix the integrity of the resource registry by using the resource:clean command.
TYPO3\Flow\Error\Exception (Exception code: 1)
Warning: file_put_contents(/tmp/aws-cache/data_s3_2006-03-01_paginators-1.json.php): failed to open stream: Permission denied in {...}/Packages/Libraries/aws/aws-sdk-php/src
/JsonCompiler.php line 93
```

This is most likely due to incorrect permissions of the cache directory for the AWS SDK. Simply change the permissions or edit the `AWS_PHP_CACHE_DIR` environment variable to use another directory. A more detailed desciption can be found on the [AWS SDK for PHP FAQ](http://docs.aws.amazon.com/aws-sdk-php/v3/guide/faq.html#how-do-i-fix-an-error-related-to-aws-cache)

## What's next?

The follwing features a already planned and will be available shortly

* More CloudFront options like using CNAMEs or issuing invalidation requests upon resource unpublishing
* Configure caching options on both storages and publishing targets
