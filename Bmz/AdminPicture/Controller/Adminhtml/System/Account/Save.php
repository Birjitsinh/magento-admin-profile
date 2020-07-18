<?php
/**
 * Created by PhpStorm.
 * User: Birjitsinh
 * Date: 17/7/20
 * Time: 11:25 AM
 */
namespace Bmz\AdminPicture\Controller\Adminhtml\System\Account;

use Magento\Framework\Validator\Exception as ValidatorException;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\State\UserLockedException;
use Magento\Security\Model\SecurityCookie;
use Magento\Framework\App\Filesystem\DirectoryList;


/**
 * Class Save
 * @package Bmz\AdminPicture\Controller\Adminhtml\System\Account
 */
class Save extends \Magento\Backend\Controller\Adminhtml\System\Account
{
    /**
     * @var SecurityCookie
     */
    private $securityCookie;

    /**
     * Get security cookie
     *
     * @return SecurityCookie
     * @deprecated 100.1.0
     */
    private function getSecurityCookie()
    {
        if (!($this->securityCookie instanceof SecurityCookie)) {
            return \Magento\Framework\App\ObjectManager::getInstance()->get(SecurityCookie::class);
        }
        return $this->securityCookie;
    }

    /**
     * Saving edited user information
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        $userId = $this->_objectManager->get(\Magento\Backend\Model\Auth\Session::class)->getUser()->getId();
        $password = (string)$this->getRequest()->getParam('password');
        $passwordConfirmation = (string)$this->getRequest()->getParam('password_confirmation');
        $interfaceLocale = (string)$this->getRequest()->getParam('interface_locale', false);

        /** @var $user \Magento\User\Model\User */
        $user = $this->_objectManager->create(\Magento\User\Model\User::class)->load($userId);
        $user->setId($userId)
            ->setUserName($this->getRequest()->getParam('username', false))
            ->setFirstName($this->getRequest()->getParam('firstname', false))
            ->setLastName($this->getRequest()->getParam('lastname', false))
            ->setEmail(strtolower($this->getRequest()->getParam('email', false)));
        /*Admin profile Image*/
        if ($this->getRequest()->getFiles('image')) {
            $profileImage = $this->getRequest()->getFiles('image');
            $notDelete = true;
            if ($this->getRequest()->getParam('image', false)) {
                $image = $this->getRequest()->getParam('image', false);
                if (isset($image['delete'])) {
                    $user->setImage('');
                    $notDelete = false;
                }
            }
            if ($notDelete) {
                $this->uploadFileAndGetName($profileImage, $user);
            }
        }

        if ($this->_objectManager->get(\Magento\Framework\Validator\Locale::class)->isValid($interfaceLocale)) {
            $user->setInterfaceLocale($interfaceLocale);
            /** @var \Magento\Backend\Model\Locale\Manager $localeManager */
            $localeManager = $this->_objectManager->get(\Magento\Backend\Model\Locale\Manager::class);
            $localeManager->switchBackendInterfaceLocale($interfaceLocale);
        }
        /** Before updating admin user data, ensure that password of current admin user is entered and is correct */
        $currentUserPasswordField = \Magento\User\Block\User\Edit\Tab\Main::CURRENT_USER_PASSWORD_FIELD;
        $currentUserPassword = $this->getRequest()->getParam($currentUserPasswordField);
        try {
            $user->performIdentityCheck($currentUserPassword);
            if ($password !== '') {
                $user->setPassword($password);
                $user->setPasswordConfirmation($passwordConfirmation);
            }
            $errors = $user->validate();
            if ($errors !== true && !empty($errors)) {
                foreach ($errors as $error) {
                    $this->messageManager->addErrorMessage($error);
                }
            } else {
                $user->save();
                $user->sendNotificationEmailsIfRequired();
                $this->messageManager->addSuccessMessage(__('You saved the account.'));
            }
        } catch (UserLockedException $e) {
            $this->_auth->logout();
            $this->getSecurityCookie()->setLogoutReasonCookie(
                \Magento\Security\Model\AdminSessionsManager::LOGOUT_REASON_USER_LOCKED
            );
        } catch (ValidatorException $e) {
            $this->messageManager->addMessages($e->getMessages());
            if ($e->getMessage()) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while saving account.'));
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath("*/*/");
    }

    /**
     * @param $profileImage
     * @param $model
     * @return mixed
     */
    public function uploadFileAndGetName($profileImage , $model)
    {
        $fileName = ($profileImage && array_key_exists('name', $profileImage)) ? $profileImage['name'] : null;
        if ($profileImage && $fileName) {
            try {
                /** @var \Magento\Framework\ObjectManagerInterface $uploader */
                $uploader = $this->_objectManager->create(
                    'Magento\MediaStorage\Model\File\Uploader',
                    ['fileId' => 'image']
                );
                $uploader->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
                /** @var \Magento\Framework\Image\Adapter\AdapterInterface $imageAdapterFactory */
                $imageAdapterFactory = $this->_objectManager->get('Magento\Framework\Image\AdapterFactory')
                    ->create();
                $uploader->setAllowRenameFiles(false);
                $uploader->setFilesDispersion(true);
                $uploader->setAllowCreateFolders(true);
                /** @var \Magento\Framework\Filesystem\Directory\Read $mediaDirectory */
                $mediaDirectory = $this->_objectManager->get('Magento\Framework\Filesystem')
                    ->getDirectoryRead(DirectoryList::MEDIA);
                if (!is_dir($mediaDirectory->getAbsolutePath('author_image'))) {
                    mkdir($mediaDirectory->getAbsolutePath('author_image'), 0755, true);
                }
                $result = $uploader->save(
                    $mediaDirectory->getAbsolutePath('author_image')
                );
                $model->setImage('author_image'.$result['file']);
            } catch (\Exception $e) {
                if ($e->getCode() == 0) {
                    $this->messageManager->addError($e->getMessage());
                }
            }
        }
    }
}
