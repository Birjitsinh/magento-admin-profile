<?php
/**
 * Created by PhpStorm.
 * User: Birjitsinh
 * Date: 17/7/20
 * Time: 11:25 AM
 */
namespace Bmz\AdminPicture\Plugin;

/**
 * Class Main
 * @package Bmz\AdminPicture\Block\Plugin
 */
class Form
{
    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    protected $_authSession;

    /**
     * @var \Magento\User\Model\UserFactory
     */
    protected $_userFactory;

    /**
     * Form constructor.
     * @param \Magento\User\Model\UserFactory $userFactory
     * @param \Magento\Backend\Model\Auth\Session $authSession
     */
    public function __construct(
        \Magento\User\Model\UserFactory $userFactory,
        \Magento\Backend\Model\Auth\Session $authSession
    ) {
        $this->_userFactory = $userFactory;
        $this->_authSession = $authSession;
    }

    /**
     * Get form HTML
     *
     * @return string
     */
    public function aroundGetFormHtml(
        \Magento\Backend\Block\System\Account\Edit\Form $subject,
        \Closure $proceed
    ) {
        $userId = $this->_authSession->getUser()->getId();
        $user = $this->_userFactory->create()->load($userId);

        $form = $subject->getForm();
        $form->setData('enctype', 'multipart/form-data');
        if (is_object($form)) {
            $fieldset = $form->getElement('base_fieldset');
            $fieldset->addField(
                'image',
                'image',
                [
                    'name' => 'image',
                    'label' => __('Image'),
                    'id' => 'image',
                    'title' => __('Image'),
                    'required' => false,
                    'value' => $user->getImage(),
                    'note' => 'Allow image type: jpg, jpeg, png'
                ]
            );

            $subject->setForm($form);
        }

        return $proceed();
    }
}