<?php
namespace Jayvee\Aws;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Jayvee.Aws".            *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use Aws\Common\Aws;

/**
 * The Aws Factory provides a convenient way to create clients for the
 * various Aws Services.
 *
 * @Flow\Scope("singleton")
 */
class AwsFactory {

	/**
	 * @var \Aws\Common\Aws
	 */
	protected $aws;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Initalize the service locator
	 */
	public function initializeObject() {
		$this->aws = Aws::factory($this->settings);
	}

	/**
	 * Creates a client instance for the specified service name
	 *
	 * @param string $serviceName
	 * @return \Aws\Common\Client\AwsClientInterface
	 */
	public function create($serviceName) {
		return $this->aws->get($serviceName);
	}

}

?>