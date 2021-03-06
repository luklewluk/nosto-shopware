<?php
/**
 * Copyright (c) 2017, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <shopware@nosto.com>
 * @copyright Copyright (c) 2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 */

use Nosto\Object\Order\Order as NostoOrder;

/**
 * Model for order information. This is used when compiling the info about an
 * order that is sent to Nosto.
 *
 * Extends Shopware_Plugins_Frontend_NostoTagging_Components_Model_Base.
 * Implements NostoOrderInterface.
 * Implements NostoValidatableModelInterface.
 *
 * @package Shopware
 * @subpackage Plugins_Frontend
 */
class Shopware_Plugins_Frontend_NostoTagging_Components_Model_Order extends NostoOrder
{
    private $includeSpecialLineItems = true;

    /**
     * Loads order details from the order model.
     *
     * @param \Shopware\Models\Order\Order $order the order model.
     * @throws Enlight_Event_Exception
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Nosto\NostoException
     */
    public function loadData(\Shopware\Models\Order\Order $order)
    {
        $this->setOrderNumber($order->getNumber());
        $this->setCreatedAt($order->getOrderTime());
        $payment = $order->getPayment();
        try {
            $paymentProvider = $payment->getName();
            $paymentPlugin = $payment->getPlugin();
            if (!is_null($paymentPlugin) && $paymentPlugin->getVersion()) {
                $paymentProvider .= sprintf(' [%s]', $paymentPlugin->getVersion());
            }
        } catch (Exception $e) {
            $paymentProvider = 'unknown';
        }
        $this->setPaymentProvider($paymentProvider);

        $orderStatus = new Shopware_Plugins_Frontend_NostoTagging_Components_Model_Order_Status();
        $orderStatus->loadData($order);
        $this->setOrderStatus($orderStatus);

        $buyerInfo = new Shopware_Plugins_Frontend_NostoTagging_Components_Model_Order_Buyer();
        $buyerInfo->loadData($order->getCustomer());
        $this->setCustomer($buyerInfo);
        foreach ($order->getDetails() as $detail) {
            /** @var Shopware\Models\Order\Detail $detail */
            if ($this->includeSpecialLineItems || $detail->getArticleId() > 0) {
                $item = new Shopware_Plugins_Frontend_NostoTagging_Components_Model_Order_LineItem();
                $item->loadData($detail);
                $this->addPurchasedItems($item);
            }
        }

        if ($this->includeSpecialLineItems) {
            $shippingCost = $order->getInvoiceShipping();
            if ($shippingCost > 0) {
                $item = new Shopware_Plugins_Frontend_NostoTagging_Components_Model_Order_LineItem();
                $item->loadSpecialItemData('Shipping cost', $shippingCost, $order->getCurrency());
                $this->addPurchasedItems($item);
            }
        }

        Shopware()->Events()->notify(
            __CLASS__ . '_AfterLoad',
            array(
                'nostoOrder' => $this,
                'order' => $order,
            )
        );
    }

    /**
     * Disables "special" line items when calling `loadData()`.
     * Special items are shipping cost, cart based discounts etc.
     */
    public function disableSpecialLineItems()
    {
        $this->includeSpecialLineItems = false;
    }
}
