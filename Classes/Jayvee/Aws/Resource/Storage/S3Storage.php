<?php
namespace Jayvee\Aws\Resource\Storage;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Jayvee.Aws".            *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Resource\CollectionInterface;
use TYPO3\Flow\Resource\Resource;
use Jayvee\Aws\Resource\Exception;
use TYPO3\Flow\Resource\Storage\Object;
use TYPO3\Flow\Utility\Unicode\Functions as UnicodeFunctions;

/**
 * A resource storage based on Aws S3
 */
class S3Storage implements \TYPO3\Flow\Resource\Storage\WritableStorageInterface {

	/**
	 * Name which identifies this resource storage
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The name of the bucket where the resources will be stored
	 *
	 * @var string
	 */
	protected $bucketName;
	
	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Utility\Environment
	 */
	protected $environment;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Resource\ResourceManager
	 */
	protected $resourceManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Resource\ResourceRepository
	 */
	protected $resourceRepository;
	
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
	 * @throws Exception
	 */
	public function __construct($name, array $options = array()) {
		$this->name = $name;

		if (!isset($options['bucketName'])) {
			throw new \Jayvee\Aws\Resource\Exception\InvalidBucketException('You must specify a bucket name for this storage with the "bucketName" option.', 1434532112);
		}
		$this->bucketName = $options['bucketName'];
	}
	
	/**
	 * Initializes this storage
	 *
	 * @return void
	 * @throws \Jayvee\Aws\Resource\Exception\InvalidBucketException
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
	 * Returns the instance name of this storage
	 *
	 * @return string
	 * @api
	 */
	public function getName() {
		return $this->name;
	}
	
	/**
	 * Returns name of the bucket this storage uses
	 * 
	 * @return string
	 */
	public function getBucketName() {
		return $this->bucketName;
	}

	/**
	 * Returns a stream handle which can be used internally to open / copy the given resource
	 * stored in this storage.
	 *
	 * @param \TYPO3\Flow\Resource\Resource $resource The resource stored in this storage
	 * @return resource | boolean The resource stream or FALSE if the stream could not be obtained
	 * @api
	 */
	public function getStreamByResource(Resource $resource) {
		$resourceUri = 's3://' . $this->bucketName . '/' . $resource->getSha1();
		return (file_exists($resourceUri) ? fopen($resourceUri, 'rb') : FALSE);
	}

	/**
	 * Returns a stream handle which can be used internally to open / copy the given resource
	 * stored in this storage.
	 *
	 * @param string $relativePath A path relative to the storage root, for example "MyFirstDirectory/SecondDirectory/Foo.css"
	 * @return resource | boolean A URI (for example the full path and filename) leading to the resource file or FALSE if it does not exist
	 * @api
	 */
	public function getStreamByResourcePath($relativePath) {
		// @fixme: what it is this good for?
	}

	/**
	 * Retrieve all Objects stored in this storage.
	 *
	 * @return array<\TYPO3\Flow\Resource\Storage\Object>
	 * @api
	 */
	public function getObjects() {
		$objects = array();
		foreach ($this->resourceManager->getCollectionsByStorage($this) as $collection) {
			$objects = array_merge($objects, $this->getObjectsByCollection($collection));
		}
		return $objects;
	}

	/**
	 * Retrieve all Objects stored in this storage, filtered by the given collection name
	 *
	 * @param CollectionInterface $collection
	 * @return array<\TYPO3\Flow\Resource\Storage\Object>
	 * @api
	 */
	public function getObjectsByCollection(CollectionInterface $collection) {
		$objects = array();
		$that = $this;
		foreach ($this->resourceRepository->findByCollectionName($collection->getName()) as $resource) {
			/** @var \TYPO3\Flow\Resource\Resource $resource */
			$object = new Object();
			$object->setFilename($resource->getFilename());
			$object->setSha1($resource->getSha1());
			$object->setMd5($resource->getMd5());
			$object->setFileSize($resource->getFileSize());
			$object->setStream(function () use ($that, $resource) { return $that->getStreamByResource($resource); } );
			$objects[] = $object;
		}
		return $objects;
	}

	/**
	 * Imports a resource (file) from the given URI or PHP resource stream into this storage.
	 *
	 * On a successful import this method returns a Resource object representing the newly imported persistent resource.
	 *
	 * @param string | resource $source The URI (or local path and filename) or the PHP resource stream to import the resource from
	 * @param string $collectionName Name of the collection the new Resource belongs to
	 * @throws Exception
	 * @return Resource A resource object representing the imported resource
	 */
	public function importResource($source, $collectionName) {
		$temporaryTargetPathAndFilename = $this->environment->getPathToTemporaryDirectory() . uniqid('TYPO3_Flow_ResourceImport_');

		if (is_resource($source)) {
			try {
				$target = fopen($temporaryTargetPathAndFilename, 'wb');
				stream_copy_to_stream($source, $target);
				fclose($target);
			} catch (\Exception $e) {
				throw new Exception(sprintf('Could import the content stream to temporary file "%s".', $temporaryTargetPathAndFilename), 1434639470);
			}
		} else {
			try {
				copy($source, $temporaryTargetPathAndFilename);
			} catch (\Exception $e) {
				throw new Exception(sprintf('Could not copy the file from "%s" to temporary file "%s".', $source, $temporaryTargetPathAndFilename), 1434639485);
			}
		}

		return $this->importTemporaryFile($temporaryTargetPathAndFilename, $collectionName);
	}

	/**
	 * Imports a resource from the given string content into this storage.
	 *
	 * On a successful import this method returns a Resource object representing the newly
	 * imported persistent resource.
	 *
	 * The specified filename will be used when presenting the resource to a user. Its file extension is
	 * important because the resource management will derive the IANA Media Type from it.
	 *
	 * @param string $content The actual content to import
	 * @param string $collectionName Name of the collection the new Resource belongs to
	 * @return Resource A resource object representing the imported resource
	 * @throws Exception
	 */
	public function importResourceFromContent($content, $collectionName) {
		$temporaryTargetPathAndFilename = $this->environment->getPathToTemporaryDirectory() . uniqid('TYPO3_Flow_ResourceImport_');
		try {
			file_put_contents($temporaryTargetPathAndFilename, $content);
		} catch (\Exception $e) {
			throw new Exception(sprintf('Could import the content stream to temporary file "%s".', $temporaryTargetPathAndFilename), 1381156098);
		}

		return $this->importTemporaryFile($temporaryTargetPathAndFilename, $collectionName);
	}

	/**
	 * Deletes the storage data related to the given Resource object
	 *
	 * @param \TYPO3\Flow\Resource\Resource $resource The Resource to delete the storage data of
	 * @return boolean TRUE if removal was successful
	 */
	public function deleteResource(Resource $resource) {
		$result = $this->client->deleteObject([
			'Bucket' => $this->bucketName,
			'Key' => $resource->getSha1()
		]);

		return TRUE;
	}
	
	/**
	 * Imports the given temporary file into the storage and creates the new resource object.
	 *
	 * @param string $temporaryFile
	 * @param string $collectionName
	 * @return Resource
	 * @throws Exception
	 */
	protected function importTemporaryFile($temporaryFile, $collectionName) {
		$sha1Hash = sha1_file($temporaryFile);
		
		$sourceStream = fopen($temporaryFile, 'r');
		$result = $this->client->upload($this->bucketName, $sha1Hash, $sourceStream);

		$resource = new Resource();
		$resource->setFileSize(filesize($temporaryFile));
		$resource->setCollectionName($collectionName);
		$resource->setSha1($sha1Hash);
		$resource->setMd5(md5_file($temporaryFile));

		return $resource;
	}

}