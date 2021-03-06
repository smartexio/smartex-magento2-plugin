<?php
/**
 * @license Copyright 2016-2017 Smartex.io Ltd., MIT License
 * 
 */
namespace Smartex\Core\Model\Config\Source;

/**
 * Class TransactionSpeed
 * 
 */
class TransactionSpeed implements \Magento\Framework\Option\ArrayInterface
{
    const SPEED_LOW    = 'low';
    const SPEED_MEDIUM = 'medium';
    const SPEED_HIGH   = 'high';

    /**
     * Possible TransactionSpeed types
     * 
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::SPEED_LOW,
                'label' => ucwords(self::SPEED_LOW),
            ],
            [
                'value' => self::SPEED_MEDIUM,
                'label' => ucwords(self::SPEED_MEDIUM)
            ],
            [
                'value' => self::SPEED_HIGH,
                'label' => ucwords(self::SPEED_HIGH)
            ]
        ];
    }
}
