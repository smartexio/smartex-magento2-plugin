<?php
/**
 * @license Copyright 2016-2017 Smartex.io Ltd., MIT License
 * 
 */

namespace Smartex;

/**
 * This is the currency code set for the price setting.  The pricing currencies
 * currently supported are USD, EUR, ETH
 *
 * @package Smartex
 */
interface CurrencyInterface
{
    /**
     * @return string
     */
    public function getCode();

    /**
     * @return string
     */
    public function getSymbol();

    /**
     * @return string
     */
    public function getPrecision();

    /**
     * @return string
     */
    public function getExchangePctFee();

    /**
     * @return boolean
     */
    public function isPayoutEnabled();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getPluralName();

    /**
     * @return array
     */
    public function getAlts();

    /**
     * @return array
     */
    public function getPayoutFields();
}
