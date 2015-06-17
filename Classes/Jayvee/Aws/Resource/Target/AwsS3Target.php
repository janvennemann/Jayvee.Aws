<?php
namespace Jayvee\Aws\Resource\Target;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Jayvee.Aws".            *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Resource\CollectionInterface;
use TYPO3\Flow\Resource\Collection;
use TYPO3\Flow\Resource\Resource;
use TYPO3\Flow\Resource\ResourceMetaDataInterface;

/**
 * A target which publishes resources to a specific bucket on Aws S3
 */
class AwsS3Target implements \TYPO3\Flow\Resource\Target\TargetInterface {

	/**
	 * Name which identifies this publishing target
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The name of the bucket where the resources will be published
	 *
	 * @var string
	 */
	protected $bucketName;

	/**
	 * The CNAME of the CloudFront distribution to use for URI generation
	 *
	 * If you do not have enabled a CloudFront distribution for your bucket,
	 * leave this option empty. The links will point directly to the bucket if not set.
	 *
	 * @var string
	 */
	protected $cloudFrontCname;

	/**
	 * @Flow\Inject
	 * @var \Jayvee\Aws\AwsFactory
	 */
	protected $awsFactory;

	/**
	 * AWS S3 client
	 *
	 * @var \Aws\S3\S3Client
	 */
	protected $client;

	/**
	 * Constructor
	 *
	 * @param string $name Name of this storage instance, according to the resource settings
	 * @param array $options Options for this storage
	 */
	public function __construct($name, array $options = array()) {
		$this->name = $name;

		if (!isset($options['bucketName'])) {
			throw new \Jayvee\Aws\Resource\Exception\InvalidBucketException('You must specify a bucket name for this storage with the "bucketName" option.', 1434532112);
		}

		$this->bucketName = $options['bucketName'];
	}

	/**
	 * Initializes this publishing target
	 *
	 * @return void
	 * @throws \TYPO3\Flow\Resource\Exception
	 */
	public function initializeObject() {
		$this->client = $this->awsFactory->create('S3');

		if (!\Aws\S3\S3Client::isBucketDnsCompatible($this->bucketName)) {
			throw new \Jayvee\Aws\Resource\Exception\InvalidBucketException('The name "' . $this->bucketName . '" is not a valid bucket name.', 1434503713);
		}

		if (!$this->client->doesBucketExist($this->bucketName, false)) {
			$this->client->createBucket(['Bucket' => $this->bucketName]);
			$this->client->waitUntil('BucketExists', ['Bucket' => $this->bucketName]);
		}
	}

	/**
	 * Returns the name of this target instance
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Publishes the whole collection to this target
	 *
	 * @param Collection $collection The collection to publish
	 * @return void
	 */
	public function publishCollection(Collection $collection) {
		$storage = $collection->getStorage();

		if ($storage instanceof AwsS3Storage) {
			return;
		} else if ($storage instanceof PackageStorage) {
			foreach ($storage->getPublicResourcePaths() as $packageKey => $path) {
				$this->publishDirectory($path, $packageKey);
			}
		} else {
			foreach ($collection->getObjects() as $object) {
				/** @var \TYPO3\Flow\Resource\Storage\Object $object */
				$sourceStream = $object->getStream();
				if ($sourceStream === FALSE) {
					throw new Exception(sprintf('Could not publish resource %s with SHA1 hash %s of collection %s because there seems to be no corresponding data in the storage.', $object->getFilename(), $object->getSha1(), $collection->getName()), 1417168142);
				}
				$this->publishFile($sourceStream, $this->getRelativePublicationPathAndFilename($object));
				fclose($sourceStream);
			}
		}
	}

	/**
	 * Publishes the given persistent resource from the given storage
	 *
	 * @param Resource $resource The resource to publish
	 * @param CollectionInterface $collection The collection the given resource belongs to
	 * @return void
	 * @throws Exception
	 */
	public function publishResource(Resource $resource, CollectionInterface $collection) {
		if ($collection->getStorage() instanceof AwsS3Storage) {
			return;
		}
	}

	/**
	 * Unpublishes the given persistent resource
	 *
	 * @param Resource $resource The resource to unpublish
	 * @return void
	 */
	public function unpublishResource(Resource $resource) {
		if ($collection->getStorage() instanceof AwsS3Storage) {
			return;
		}
	}

	/**
	 * Returns the web accessible URI pointing to the given static resource
	 *
	 * @param string $relativePathAndFilename Relative path and filename of the static resource
	 * @return string The URI
	 */
	public function getPublicStaticResourceUri($relativePathAndFilename) {

	}

	/**
	 * Returns the web accessible URI pointing to the specified persistent resource
	 *
	 * @param Resource $resource Resource object
	 * @return string The URI
	 * @throws Exception
	 */
	public function getPublicPersistentResourceUri(Resource $resource) {

	}

	/**
	 * Determines and returns the relative path and filename for the given Storage Object or Resource. If the given
	 * object represents a persistent resource, its own relative publication path will be empty. If the given object
	 * represents a static resources, it will contain a relative path.
	 *
	 * No matter which kind of resource, persistent or static, this function will return a sub directory structure
	 * if no relative publication path was defined in the given object.
	 *
	 * @param ResourceMetaDataInterface $object Resource or Storage Object
	 * @return string The relative path and filename, for example "c828d/0f88c/e197b/e1aff/7cc2e/5e86b/12442/41ac6/MyPicture.jpg" (if subdivideHashPathSegment is on) or "c828d0f88ce197be1aff7cc2e5e86b1244241ac6/MyPicture.jpg" (if it's off)
	 */
	protected function getRelativePublicationPathAndFilename(ResourceMetaDataInterface $object) {
		if ($object->getRelativePublicationPath() !== '') {
			$pathAndFilename = $object->getRelativePublicationPath() . $object->getFilename();
		} else {
			if ($this->subdivideHashPathSegment) {
				$pathAndFilename = wordwrap($object->getSha1(), 5, '/', TRUE) . '/' . $object->getFilename();
			} else {
				$pathAndFilename = $object->getSha1() . '/' . $object->getFilename();
			}
		}
		return $pathAndFilename;
	}

	/**
	 * Publishes the given source stream to this target, with the given relative path.
	 *
	 * @param resource $sourceStream Stream of the source to publish
	 * @param string $relativeTargetPathAndFilename relative path and filename in the target directory
	 * @return void
	 */
	protected function publishFile($sourceStream, $relativeTargetPathAndFilename) {

	}

	/**
	 * Publishes the specified directory to this target, with the given relative path.
	 *
	 * @param string $sourcePath Absolute path to the source directory
	 * @param string $relativeTargetPath relative path in the target directory
	 * @return void
	 */
	protected function publishDirectory($sourcePath, $relativeTargetPath) {
		echo 'Upload directory ' . $sourcePath;
		$this->client->uploadDirectory($sourcePath, $this->bucketName, $relativeTargetPath);
	}

}