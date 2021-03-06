<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Payment\Api;

/**
 * Payment method list interface.
 *
 * @api
 */
interface PaymentMethodListInterface
{
    /**
     * Get list of payment methods.
     *
     * @param int $storeId
     * @return \Magento\Payment\Api\Data\PaymentMethodInterface[]
     */
    public function getList($storeId);

    /**
     * Get list of active payment methods.
     *
     * @param int $storeId
     * @return \Magento\Payment\Api\Data\PaymentMethodInterface[]
     */
    public function getActiveList($storeId);
}
