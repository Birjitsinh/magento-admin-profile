<?php
/**
 * Created by PhpStorm.
 * User: Biejitsinh
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
        \Magento\User\Block\User\Edit\Tab\Main $subject,
        \Closure $proceed
    ) {
        $model = $this->registry->registry('permissions_user');

        $form = $subject->getForm();
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
                    'value' => $model->getImage(),
                    'note' => 'Allow image type: jpg, jpeg, png'
                ]
            );
            $subject->setForm($form);
        }

        return $proceed();
    }
}