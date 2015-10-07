<?php
namespace Jayvee\Aws\S3;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Jayvee.Aws".            *
 *                                                                        */

/**
 * A builder for Aws S3 object policies 
 */
class PolicyBuilder {
    
    /**
     * @var array
     */
    protected $policy;
    
    /**
     * Creates a new policy builder using an existing policy
     * 
     * @return PolicyBuilder
     */
    public static function createWithExistingPolicy($existingPolicy) {
        array_walk($existingPolicy['Grants'], function(&$grant) {
            if (!isset($grant['Grantee']['Type'])) {
                if (isset($grant['Grantee']['ID'])) {
                    $grant['Grantee']['Type'] = 'CanonicalUser';
                } else if (isset($grant['Grantee']['URI'])) {
                    $grant['Grantee']['Type'] = 'Group';
                }
            }
        });
        
        $policyBuilder = new self;
        $policyBuilder->setPolicy($existingPolicy);
        return $policyBuilder;
    }
    
    /**
     * Sets the policy
     * 
     * @return void
     */
    public function setPolicy($policy) {
        $this->policy = $policy;
    }
    
    /**
     * Gets the current policy
     */
    public function getPolicy() {
        return $this->policy;
    }
    
    /**
     * Sets the object owner
     */
    public function setOwner($owner) {
        $this->policy['Owner']['ID'] = $owner;
    }
    
    /**
     * Adds a new canonical user and grants the given permissions
     * 
     * @param string $user String identifying the canonical user
     * @param array $permissions Permssions granted to the user
     * @return void
     */
    public function addCanonicalUser($user, array $permissions) {
        foreach ($permissions as $permission) {
            $grant = [
                'Grantee' => [
                    'Type' => 'CanonicalUser',
                    'ID' => $user,
                ],
                'Permission' => $permission
            ];
            $this->policy['Grants'][] = $grant;
        }
    }
    
    /**
     * Removes a canonical user
     * 
     * @param string $user String identifying the canonical user to remove
     * @return void
     */
    public function removeCanonicalUser($user) {
        $this->policy = array_filter($this->policy['Grants'], function($grant) {
            if ($grant['Grantee']['Type'] != 'CanonicalUser') {
                return TRUE;
            }
            
            return $grant['Grantee']['ID'] !== $user;
        });
    }
    
    /**
     * Adds a new group and grants the given permissions
     * 
     * @param string $group String identifying the group
     * @param array $permissions Permissions granted to the group
     * @return void
     */
    public function addGroup($group, array $permissions) {
        foreach ($permissions as $permission) {
            $grant = [
                'Grantee' => [
                    'Type' => 'Group',
                    'URI' => $group,
                ],
                'Permission' => $permission
            ];
            $this->policy['Grants'][] = $grant;
        }
    }
    
    /**
     * Removes a group
     * 
     * @param string $group String identifying the group to remove
     * @return void
     */
    public function removeGroup($group) {
        $this->policy = array_filter($this->policy['Grants'], function($grant) {
            if ($grant['Grantee']['Type'] != 'Group') {
                return TRUE;
            }
            
            return $grant['Grantee']['URI'] !== $group;
        });
    }
    
}