<?php
/**
 * Created by PhpStorm.
 * User: Birjitsinh
 * Date: 17/7/20
 * Time: 11:25 AM
 */
namespace Bmz\AdminPicture\Controller\Adminhtml\User;

use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\State\UserLockedException;
use Magento\Security\Model\SecurityCookie;
use Magento\User\Model\Spi\NotificationExceptionInterface;

/**
 * Class Save
 * @package Bmz\AdminPicture\Controller\Adminhtml\System\Account
 */
class Save extends \Magento\User\Controller\Adminhtml\User implements HttpPostActionInterface
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
        } else {
            return $this->securityCookie;
        }
    }

    /**
     * @inheritDoc
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function execute()
    {
        $userId = (int)$this->getRequest()->getParam('user_id');
        $data = $this->getRequest()->getPostValue();
        if (array_key_exists('form_key', $data)) {
            unset($data['form_key']);
        }
        if (!$data) {
            $this->_redirect('adminhtml/*/');
            return;
        }

        /** @var $model \Magento\User\Model\User */
        $model = $this->_userFactory->create()->load($userId);
        if ($userId && $model->isObjectNew()) {
            $this->messageManager->addError(__('This user no longer exists.'));
            $this->_redirect('adminhtml/*/');
            return;
        }
        /*Admin profile Image*/
        if ($this->getRequest()->getFiles('image')) {
            $profileImage = $this->getRequest()->getFiles('image');
            $notDelete = true;
            if ($this->getRequest()->getParam('image', false)) {
                $image = $this->getRequest()->getParam('image', false);
                if (isset($image['delete'])) {
                    $data['image'] = '';
                    $notDelete = false;
                } else {
                    $data['image'] = $image['value'];
                }
            }
            if ($notDelete) {
                $imageName = $this->uploadFileAndGetName($profileImage);
                if (!empty($imageName)) {
                    $data['image'] = $imageName;
                }
            }
        }
        $model->setData($this->_getAdminUserData($data));
        $userRoles = $this->getRequest()->getParam('roles', []);
        if (count($userRoles)) {
            $model->setRoleId($userRoles[0]);
        }

        /** @var $currentUser \Magento\User\Model\User */
        $currentUser = $this->_objectManager->get(\Magento\Backend\Model\Auth\Session::class)->getUser();
        if ($userId == $currentUser->getId()
            && $this->_objectManager->get(\Magento\Framework\Validator\Locale::class)
                ->isValid($data['interface_locale'])
        ) {
            $this->_objectManager->get(
                \Magento\Backend\Model\Locale\Manager::class
            )->switchBackendInterfaceLocale(
                $data['interface_locale']
            );
        }

        /** Before updating admin user data, ensure that password of current admin user is entered and is correct */
        $currentUserPasswordField = \Magento\User\Block\User\Edit\Tab\Main::CURRENT_USER_PASSWORD_FIELD;
        $isCurrentUserPasswordValid = isset($data[$currentUserPasswordField])
            && !empty($data[$currentUserPasswordField]) && is_string($data[$currentUserPasswordField]);
        try {
            if (!($isCurrentUserPasswordValid)) {
                throw new AuthenticationException(
                    __('The password entered for the current user is invalid. Verify the password and try again.')
                );
            }

            $currentUser->performIdentityCheck($data[$currentUserPasswordField]);
            $model->save();

            $this->messageManager->addSuccess(__('You saved the user.'));
            $this->_getSession()->setUserData(false);
            $this->_redirect('adminhtml/*/');

            $model->sendNotificationEmailsIfRequired();
        } catch (UserLockedException $e) {
            $this->_auth->logout();
            $this->getSecurityCookie()->setLogoutReasonCookie(
                \Magento\Security\Model\AdminSessionsManager::LOGOUT_REASON_USER_LOCKED
            );
            $this->_redirect('adminhtml/*/');
        } catch (NotificationExceptionInterface $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Magento\Framework\Exception\AuthenticationException $e) {
            $this->messageManager->addError(
                __('The password entered for the current user is invalid. Verify the password and try again.')
            );
            $this->redirectToEdit($model, $data);
        } catch (\Magento\Framework\Validator\Exception $e) {
            $messages = $e->getMessages();
            $this->messageManager->addMessages($messages);
            $this->redirectToEdit($model, $data);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            if ($e->getMessage()) {
                $this->messageManager->addError($e->getMessage());
            }
            $this->redirectToEdit($model, $data);
        }
    }

    /**
     * Redirect to Edit form.
     *
     * @param \Magento\User\Model\User $model
     * @param array $data
     * @return void
     */
    protected function redirectToEdit(\Magento\User\Model\User $model, array $data)
    {
        $this->_getSession()->setUserData($data);
        $arguments = $model->getId() ? ['user_id' => $model->getId()] : [];
        $arguments = array_merge($arguments, ['_current' => true, 'active_tab' => '']);
        $this->_redirect('adminhtml/*/edit', $arguments);
    }

    /**
     * @param $profileImage
     * @return string
     */
    public function uploadFileAndGetName($profileImage)
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
                return 'author_image'.$result['file'];
            } catch (\Exception $e) {
                if ($e->getCode() == 0) {
                    $this->messageManager->addError($e->getMessage());
                    return '';
                } else {
                    return '';
                }
            }
        }
    }
}
