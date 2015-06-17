<?php
namespace Jayvee\Aws;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Jayvee.Aws".            *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use Aws\Sdk;
use Aws\Credentials\Credentials;
use Aws\Credentials\CredentialProvider;

/**
 * The Aws Factory provides a convenient way to create clients for the
 * various Aws Services.
 *
 * @Flow\Scope("singleton")
 */
class AwsFactory {

	/**
	 * @var \Aws\Sdk
	 */
	protected $awsSdk;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * Initalize the aws factory
	 */
	public function initializeObject() {
		if (isset($this->settings['key']) && isset($this->settings['secret'])) {
			$key = $this->settings['key'];
			$secret = $this->settings['secret'];
			$credentialProvider = CredentialProvider::fromCredentials(new Credentials($key, $secret));
		} else {
			$credentialProvider = CredentialProvider::defaultProvider();
		}

		$region = isset($this->settings['region']) ? $this->settings['region'] : 'eu-east-1';
		$config = array(
			'region'  => $region,
    		'version' => 'latest',
    		'credentials' => $credentialProvider
    	);
		$this->awsSdk = new Sdk($config);
	}

	/**
	 * Creates a client instance for the specified service name
	 *
	 * @param string $name
	 * @param array $options
	 * @return \Aws\AwsClientInterface
	 */
	public function create($name, array $options = array()) {
		return $this->awsSdk->createClient($name, $options);
	}

}

?>