<?php
namespace Jayvee\Aws\Resource\Target;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Jayvee.Aws".            *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Resource\CollectionInterface;
use TYPO3\Flow\Resource\Collection;
use TYPO3\Flow\Resource\Resource;

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
	 * Constructor
	 *
	 * @param string $name Name of this storage instance, according to the resource settings
	 * @param array $options Options for this storage
	 */
	public function __construct($name, array $options = array()) {
		$this->name = $name;
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

	}

	/**
	 * Unpublishes the given persistent resource
	 *
	 * @param Resource $resource The resource to unpublish
	 * @return void
	 */
	public function unpublishResource(Resource $resource) {

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

}