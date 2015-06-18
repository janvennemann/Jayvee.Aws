<?php
namespace Jayvee\Aws;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Jayvee.Upload".         *
 *                                                                        *
 *                                                                        */

use TYPO3\Flow\Package\Package as BasePackage;

/**
 * The Jayvee Aws Package
 */
class Package extends BasePackage {

    /**
     * Invokes custom PHP code directly after the package manager has been initialized.
     *
     * @param \TYPO3\Flow\Core\Bootstrap $bootstrap The current bootstrap
     * @return void
     */
	public function boot(\TYPO3\Flow\Core\Bootstrap $bootstrap) {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect('TYPO3\Flow\Core\Booting\Sequence', 'afterInvokeStep', function ($step) use ($bootstrap, $dispatcher) {
            if ($step->getIdentifier() === 'typo3.flow:objectmanagement:runtime') {
                $awsFactory = $bootstrap->getObjectManager()->get('Jayvee\Aws\AwsFactory');
                $s3Client = $awsFactory->create('S3');
                $s3Client->registerStreamWrapper();
            }
        });
    }
}
