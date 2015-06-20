# Amazon Web Services integration for TYPO3 Flow 
This packages provides you with a S3 resource storage and publishing target for the new TYPO3 Flow resource management. It also comes with handy little factory, which you can use to easily create clients for the various services.

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

To serve your files via CloudFront and not directly from your S3 bucket, just add the CloudFront domain name or cname of the distribution you want to use with the `cloudFrontDomainName` option. Since creating a distribution is a longer running task, you will have to set it up yourself and set the S3 bucket as its origin.

```yaml
TYPO3:
  Flow:
    resource:
      targets:
        s3PersistentResourcesTarget:
          target: 'Jayvee\Aws\Resource\Target\S3Target'
          targetOptions:
            bucketName: '<bucket_name>'
            cloudFrontDomainName: 'xxxxxxxxx.cloudfront.com'
            originAccessIdentiyEnabled: true
```

The option `originAccessIdentiyEnabled` is only needed if you want to use a origin access identity. It defaults to `false` if not set.
