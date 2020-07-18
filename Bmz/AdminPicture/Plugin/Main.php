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
class Main
{
    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * Main constructor.
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Framework\Registry $registry
    ) {
        $this->registry = $registry;
    }

    /**
     * Get form HTML
     *
     * @return string
     */
    public function aroundGetFormHtml(
        \Magento\User\Block\User\Edit\Form $subject,
        \Closure $proceed
    ) {
        $model = $this->registry->registry('permissions_user');

        $form = $subject->getForm();
        $form->setData('enctype', 'multipart/form-data');
        if (is_object($form)) {
            $subject->setForm($form);
        }

        return $proceed();
    }
}