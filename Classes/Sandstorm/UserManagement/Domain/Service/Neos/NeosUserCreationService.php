<?php
namespace Sandstorm\UserManagement\Domain\Service\Neos;

use Sandstorm\UserManagement\Domain\Model\RegistrationFlow;
use Sandstorm\UserManagement\Domain\Service\UserCreationServiceInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Object\ObjectManagerInterface;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Security\AccountRepository;
use TYPO3\Flow\Security\Policy\Role;
use TYPO3\Neos\Domain\Model\User;
use TYPO3\Party\Domain\Model\PersonName;
use TYPO3\Party\Domain\Repository\PartyRepository;
use TYPO3\Party\Domain\Service\PartyService;

/**
 * @Flow\Scope("singleton")
 */
class NeosUserCreationService implements UserCreationServiceInterface
{

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var AccountRepository
     */
    protected $accountRepository;

    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\InjectConfiguration(path="rolesForNewUsers")
     */
    protected $rolesForNewUsers;

    /**
     * In this method, actually create the user / account.
     *
     * NOTE: After this method is called, the $registrationFlow is DESTROYED, so you need to store all attributes
     * in your object as you need them.
     *
     * @param RegistrationFlow $registrationFlow
     * @return void
     */
    public function createUserAndAccount(RegistrationFlow $registrationFlow)
    {
        // Create the account
        $account = new \TYPO3\Flow\Security\Account();
        $account->setAccountIdentifier($registrationFlow->getEmail());
        $account->setCredentialsSource($registrationFlow->getEncryptedPassword());
        $account->setAuthenticationProviderName('Sandstorm.UserManagement:Login');

        // Assign preconfigured roles
        foreach ($this->rolesForNewUsers as $roleString){
            $account->addRole(new Role($roleString));
        }

        // Create the user
        $user = new User();
        $name = new PersonName('', $registrationFlow->getFirstName(), $registrationFlow->getLastName(), '', '', $registrationFlow->getEmail());
        $user->setName($name);

        // Assign them to each other and persist
        $this->getPartyService()->assignAccountToParty($account, $user);
        $this->getPartyRepository()->add($user);
        $this->accountRepository->add($account);
        $this->persistenceManager->whitelistObject($user);
        $this->persistenceManager->whitelistObject($user->getPreferences());
        $this->persistenceManager->whitelistObject($name);
        $this->persistenceManager->whitelistObject($account);
    }

    /**
     * This method exists to ensure the code runs outside Neos.
     * We do not fetch this via injection so it works also in Flow when the class is not present
     *
     * @return PartyService
     */
    protected function getPartyService()
    {
        return $this->objectManager->get(PartyService::class);
    }

    /**
     * This method exists to ensure the code runs outside Neos.
     * We do not fetch this via injection so it works also in Flow when the class is not present
     *
     * @return PartyRepository
     */
    protected function getPartyRepository()
    {
        return $this->objectManager->get(PartyRepository::class);
    }
}
