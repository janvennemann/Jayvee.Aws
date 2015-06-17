<?php
namespace Jayvee\Aws\Resource\Storage;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Jayvee.Aws".            *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Resource\CollectionInterface;
use TYPO3\Flow\Resource\Resource;

/**
 * A resource storage based on Aws S3
 */
class AwsS3Storage implements \TYPO3\Flow\Resource\Storage\WritableStorageInterface {

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
	 * @var \TYPO3\Flow\Resource\ResourceManager
	 */
	protected $resourceManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Resource\ResourceRepository
	 */
	protected $resourceRepository;

	/**
	 * Constructor
	 *
	 * @param string $name Name of this storage instance, according to the resource settings
	 * @param array $options Options for this storage
	 * @throws Exception
	 */
	public function __construct($name, array $options = array()) {
		$this->name = $name;

		foreach ($options as $key => $value) {
			switch ($key) {
				case 'bucketName':
					$this->$key = $value;
					break;
				default:
					if ($value !== NULL) {
						throw new Exception(sprintf('An unknown option "%s" was specified in the configuration of a resource AwsS3Storage. Please check your settings.', $key), 1361533187);
					}
			}
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
	 * Returns a stream handle which can be used internally to open / copy the given resource
	 * stored in this storage.
	 *
	 * @param \TYPO3\Flow\Resource\Resource $resource The resource stored in this storage
	 * @return resource | boolean The resource stream or FALSE if the stream could not be obtained
	 * @api
	 */
	public function getStreamByResource(Resource $resource) {

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

	}

	/**
	 * Retrieve all Objects stored in this storage.
	 *
	 * @return array<\TYPO3\Flow\Resource\Storage\Object>
	 * @api
	 */
	public function getObjects() {

	}

	/**
	 * Retrieve all Objects stored in this storage, filtered by the given collection name
	 *
	 * @param CollectionInterface $collection
	 * @return array<\TYPO3\Flow\Resource\Storage\Object>
	 * @api
	 */
	public function getObjectsByCollection(CollectionInterface $collection) {

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

	}

	/**
	 * Deletes the storage data related to the given Resource object
	 *
	 * @param \TYPO3\Flow\Resource\Resource $resource The Resource to delete the storage data of
	 * @return boolean TRUE if removal was successful
	 */
	public function deleteResource(Resource $resource) {

	}

}