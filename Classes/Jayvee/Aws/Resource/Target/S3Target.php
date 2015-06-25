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
use TYPO3\Flow\Resource\Storage\StorageInterface;
use TYPO3\Flow\Resource\Storage\PackageStorage;
use Jayvee\Aws\Resource\Storage\S3Storage;
use TYPO3\Flow\Utility\Arrays;

/**
 * A target which publishes resources to a specific bucket on Aws S3
 */
class S3Target implements \TYPO3\Flow\Resource\Target\TargetInterface {

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
	 * Wether or not to use CloudFront
	 *
	 * @var bool
	 */
	protected $isCloudFrontEnabled = FALSE;

    /**
     * CloudFront settings array
     *
     * @var array
     */
	protected $cloudFrontSettings = array();

	/**
	 * Value for the GrantRead option when publishing
	 */
	protected $grantRead;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Resource\ResourceRepository
	 */
	protected $resourceRepository;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Resource\ResourceManager
	 */
	protected $resourceManager;

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
	protected $s3Client;

	/**
	 * Aws CloudFront client
	 *
	 * @var \Aws\CloudFront\CloudFrontClient
	 */
	protected $cloudFrontClient;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Log\SystemLoggerInterface
	 */
	protected $systemLogger;

	/**
	 * Constructor
	 *
	 * @param string $name Name of this storage instance, according to the resource settings
	 * @param array $options Options for this storage
	 */
	public function __construct($name, array $options = array()) {
		$this->name = $name;

		if (!isset($options['bucketName'])) {
			throw new \Jayvee\Aws\Resource\Exception\InvalidBucketException('You must specify a bucket name for this publication target with the "bucketName" option.', 1434532112);
		}
		$this->bucketName = $options['bucketName'];

	    if (isset($options['cloudFront'])) {
	        $this->cloudFrontSettings = $options['cloudFront'];
	    }
	}

	/**
	 * Initializes this publishing target
	 *
	 * @return void
	 * @throws \Jayvee\Aws\Resource\Exception\InvalidBucketException
	 */
	public function initializeObject() {
		$this->initializeSimpleStorageService();
		if ($this->cloudFrontSettings !== array()) {
		    $this->initializeCloudFront();
		}
		$this->buildAccessControlPolicy();
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

		if ($storage instanceof PackageStorage) {
			foreach ($storage->getPublicResourcePaths() as $packageKey => $path) {
				$this->publishDirectory($path, $packageKey);
			}
		} else {
			foreach ($collection->getObjects() as $object) {
				/** @var \TYPO3\Flow\Resource\Storage\Object $object */
				$sourceStream = $object->getStream();
				if ($sourceStream === FALSE) {
					throw new \TYPO3\Flow\Resource\Exception(sprintf('Could not publish resource %s with SHA1 hash %s of collection %s because there seems to be no corresponding data in the storage.', $object->getFilename(), $object->getSha1(), $collection->getName()), 1417168142);
				}
				
				$relativePathAndFilename = $this->getRelativePublicationPathAndFilename($object);
        		$storage = $collection->getStorage();
        		if ($this->isThisTargetSameAsStorage($storage)) {
        			$this->publishFileByMakingItPublic($relativePathAndFilename);
        		} else {
        			$this->publishFile($sourceStream, $relativePathAndFilename);
        		}
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
		$sourceStream = $resource->getStream();
		if ($sourceStream === FALSE) {
			throw new \TYPO3\Flow\Resource\Exception(sprintf('Could not publish resource %s with SHA1 hash %s of collection %s because there seems to be no corresponding data in the storage.', $object->getFilename(), $object->getSha1(), $collection->getName()), 1417168142);
		}

		$relativePathAndFilename = $this->getRelativePublicationPathAndFilename($resource);
		$storage = $collection->getStorage();
		if ($this->isThisTargetSameAsStorage($storage)) {
			$this->publishFileByMakingItPublic($relativePathAndFilename);
		} else {
			$this->publishFile($sourceStream, $relativePathAndFilename);
		}
	}

	/**
	 * Unpublishes the given persistent resource
	 *
	 * @param Resource $resource The resource to unpublish
	 * @return void
	 */
	public function unpublishResource(Resource $resource) {
		$resources = $this->resourceRepository->findSimilarResources($resource);
		if (count($resources) > 1) {
			return;
		}

		$collection = $this->resourceManager->getCollection($resource->getCollectionName());
		if ($collection === NULL) {
			return;
		}

		$relativePathAndFilename = $this->getRelativePublicationPathAndFilename($resource);
		$storage = $collection->getStorage();
		if ($this->isThisTargetSameAsStorage($storage)) {
			$this->unpublishFileByMakingItPrivate($relativePathAndFilename);
		} else {
			$this->unpublishFile($relativePathAndFilename);
		}
	}

	/**
	 * Returns the web accessible URI pointing to the given static resource
	 *
	 * @param string $relativePathAndFilename Relative path and filename of the static resource
	 * @return string The URI
	 */
	public function getPublicStaticResourceUri($relativePathAndFilename) {
		if ($this->isCloudFrontEnabled) {
			return 'https://' . $this->cloudFrontSettings['domainName'] . '/' . $relativePathAndFilename;
		}

		return $this->s3Client->getObjectUrl($this->bucketName, $relativePathAndFilename);
	}

	/**
	 * Returns the web accessible URI pointing to the specified persistent resource
	 *
	 * @param Resource $resource Resource object
	 * @return string The URI
	 * @throws Exception
	 */
	public function getPublicPersistentResourceUri(Resource $resource) {
		if ($this->isCloudFrontEnabled) {
			return 'https://' . $this->cloudFrontSettings['domainName'] . '/' . $resource->getSha1();
		}

		return $this->s3Client->getObjectUrl($this->bucketName, $resource->getSha1());
	}

	/**
	 * Initalizes the S3 client
	 *
	 * @return void
	 */
	protected function initializeSimpleStorageService() {
	    $this->s3Client = $this->awsFactory->create('S3');

		if (!\Aws\S3\S3Client::isBucketDnsCompatible($this->bucketName)) {
			throw new \Jayvee\Aws\Resource\Exception\InvalidBucketException('The name "' . $this->bucketName . '" is not a valid bucket name.', 1434503713);
		}

		if (!$this->s3Client->doesBucketExist($this->bucketName, false)) {
			$this->s3Client->createBucket(['Bucket' => $this->bucketName]);
			$this->s3Client->waitUntil('BucketExists', ['Bucket' => $this->bucketName]);
		}
	}

	/**
	 * Initializes the CloudFront client
	 *
	 * @return void
	 */
	protected function initializeCloudFront() {
        if (!isset($this->cloudFrontSettings['distributionIdentifier']) && !is_string($this->cloudFrontSettings['distributionIdentifier'])) {
	        throw new \Jayvee\Aws\Resource\Exception(sprintf('Invalid CloudFront distribution identifier given for target %s.', $this->name), 1434801704);
	    }

	    $this->cloudFrontClient = $this->awsFactory->create('CloudFront', ['region' => 'us-east-1']);

	    $distributionIdentifier = $this->cloudFrontSettings['distributionIdentifier'];
	    $distributionData = $this->cloudFrontClient->getDistribution([
            'Id' => $distributionIdentifier,
        ]);
        $origins = $distributionData->getPath('Distribution.DistributionConfig.Origins.Items');
        $targetOrigin = NULL;
        foreach($origins as $origin) {
            if ($origin['DomainName'] === $this->bucketName . '.s3.amazonaws.com') {
                $targetOrigin = $origin;
                break;
            }
        }
        if ($targetOrigin === NULL) {
        	$comment = Arrays::getValueByPath($distributionData, 'Distribution.DistributionConfig.Comment');
            throw new \Jayvee\Aws\Resource\Exception(sprintf('Could not find bucket %s as an origin in your CloudFront distribution %s (%).', $this->bucketName, $distributionIdentifier, $comment), 1434803791);
        }
        
        $this->isCloudFrontEnabled = TRUE;
        $this->cloudFrontSettings['domainName'] = $distributionData->getPath('Distribution.DomainName');
        
        $oaiString = Arrays::getValueByPath($targetOrigin, 'S3OriginConfig.OriginAccessIdentity');
        if ($oaiString) {
            $oai = substr($oaiString, strrpos($oaiString, '/') + 1);
            $oaiData = $this->cloudFrontClient->getCloudFrontOriginAccessIdentity(['Id' => $oai]);
            $oaiCanonicalUserId = $oaiData->getPath('CloudFrontOriginAccessIdentity.S3CanonicalUserId');
            $this->cloudFrontSettings['oaiCanonicalUserId'] = $oaiCanonicalUserId;
        }
	}

	/**
	 * Builds the access control policy used for publishing resources
	 *
	 * @return void
	 */
	protected function buildAccessControlPolicy() {
	    if (isset($this->cloudFrontSettings['oaiCanonicalUserId'])) {
	        $this->accessControlPolicy = [
    	        'Grants' => [[
    	            'Grantee' => [
    	                'DisplayName' => 'CloudFront Origin Access Identity',
    	                'Type' => 'CanonicalUser',
    	                'ID' => $this->cloudFrontSettings['oaiCanonicalUserId']
    	            ],
    	            'Permission' => 'READ'
                ]]
    	    ];
	    } else {
	        $this->accessControlPolicy = [
    	        'Grants' => [[
    	            'Grantee' => [
    	                'DisplayName' => 'Everyone',
    	                'Type' => 'Group',
    	                'ID' => 'http://acs.amazonaws.com/groups/global/AllUsers'
    	            ],
    	            'Permission' => 'READ'
                ]]
    	    ];
	    }
	}
	
	protected function buildGrantReadFromAccessControlPolicy() {
	    if ($this->accessControlPolicy['Grants'][0]['Grantee']['Type'] === 'CanonicalUser') {
	        return 'id=' . $this->accessControlPolicy['Grants'][0]['Grantee']['ID'];
	    } else {
	        return 'uri=' . $this->accessControlPolicy['Grants'][0]['Grantee']['URI'];
	    }
	}

	/**
	 * Checks if the given storage uses the same bucket as this target
	 *
	 * @param StorageInterface $storage The storage to check against
	 * @return boolean [description]
	 */
	protected function isThisTargetSameAsStorage(StorageInterface $storage) {
		return $storage instanceof S3Storage && $storage->getBucketname() === $this->bucketName;
	}

	/**
	 * Publishes the given bucket object by setting its ACL to public-read
	 *
	 * @param string $relativeTargetPathAndFilename relative path and filename in the target file
	 * @return void
	 */
	protected function publishFileByMakingItPublic($relativeTargetPathAndFilename) {
		$this->s3Client->putObjectAcl([
		    'Bucket' => $this->bucketName,
			'Key' => $relativeTargetPathAndFilename,
			'AccessControlPolicy' => $this->accessControlPolicy
		]);

		$this->systemLogger->log(sprintf('S3Target: Published file by making it public. (target: %s, file: %s)', $this->name, $relativeTargetPathAndFilename), LOG_DEBUG);
	}

	/**
	 * Publishes the given source stream to this target, with the given relative path.
	 *
	 * @param resource $sourceStream Stream of the source to publish
	 * @param string $relativeTargetPathAndFilename relative path and filename in the target file
	 * @return void
	 */
	protected function publishFile($sourceStream, $relativeTargetPathAndFilename) {
		$this->s3Client->upload($this->bucketName, $relativeTargetPathAndFilename, $sourceStream, 'private', [
			'before' => function(\Aws\CommandInterface $command) {
				$command['GrantRead'] = $this->buildGrantReadFromAccessControlPolicy();
			}
		]);

		$this->systemLogger->log(sprintf('S3Target: Published file. (target: %s, file: %s)', $this->name, $relativeTargetPathAndFilename), LOG_DEBUG);
	}

	/**
	 * Publishes the specified directory to this target, with the given relative path.
	 *
	 * @param string $sourcePath Absolute path to the source directory
	 * @param string $relativeTargetPath relative path in the target directory
	 * @return void
	 */
	protected function publishDirectory($sourcePath, $relativeTargetPath) {
		$this->s3Client->deleteMatchingObjects($this->bucketName, $relativeTargetPath);
		$this->s3Client->uploadDirectory($sourcePath, $this->bucketName, $relativeTargetPath, [
			'before' => function(\Aws\CommandInterface $command) {
				$command['GrantRead'] = $this->buildGrantReadFromAccessControlPolicy();
			}
		]);
	}

	/**
	 * Unpublishes the given bucket object by setting its ACL to private
	 *
	 * @param string $relativeTargetPathAndFilename relative path and filename in the target file
	 * @return void
	 */
	protected function unpublishFileByMakingItPrivate($relativeTargetPathAndFilename) {
		$this->s3Client->putObjectAcl([
			'Key' => $relativeTargetPathAndFilename,
			'ACL' => 'private'
		]);

		$this->systemLogger->log(sprintf('S3Target: Unpublished file by making it private. (target: %s, file: %s)', $this->name, $relativeTargetPathAndFilename), LOG_DEBUG);
	}

	/**
	 * Removes the specified target file from the S3 bucket
	 *
	 * This method fails silently if the given file could not be unpublished or already didn't exist anymore.
	 *
	 * @param string $relativeTargetPathAndFilename relative path and filename in the target directory
	 * @return void
	 */
	protected function unpublishFile($relativeTargetPathAndFilename) {
		$this->s3Client->deleteObject([
			'Bucket' => $this->bucketName,
			'Key' => $relativeTargetPathAndFilename
		]);

		$this->systemLogger->log(sprintf('S3Target: Unpublished file. (target: %s, file: %s)', $this->name, $relativeTargetPathAndFilename), LOG_DEBUG);
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
			$pathAndFilename = $object->getSha1();
		}
		return $pathAndFilename;
	}

}