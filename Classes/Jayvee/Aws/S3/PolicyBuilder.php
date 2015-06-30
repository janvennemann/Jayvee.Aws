<?php
namespace Jayvee\Aws\S3;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Jayvee.Aws".            *
 *                                                                        */

class PolicyBuilder {
    
    protected $policy;
    
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
    
    public function setPolicy($policy) {
        $this->policy = $policy;
    }
    
    public function getPolicy() {
        return $this->policy;
    }
    
    public function setOwner($owner) {
        $this->policy['Owner']['ID'] = $owner;
    }
    
    public function addCanonicalUser($user, $permissions) {
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
    
    public function removeCanonicalUser($user) {
        $this->policy = array_filter($this->policy['Grants'], function($grant) {
            if ($grant['Grantee']['Type'] != 'CanonicalUser') {
                return TRUE;
            }
            
            return $grant['Grantee']['ID'] !== $user;
        });
    }
    
    public function addGroup($group, $permissions) {
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
    
    public function removeGroup($group) {
        $this->policy = array_filter($this->policy['Grants'], function($grant) {
            if ($grant['Grantee']['Type'] != 'Group') {
                return TRUE;
            }
            
            return $grant['Grantee']['URI'] !== $group;
        });
    }
    
}