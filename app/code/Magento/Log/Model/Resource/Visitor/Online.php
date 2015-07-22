<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace Magento\Log\Model\Resource\Visitor;

/**
 * Log Prepare Online visitors resource
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Online extends \Magento\Framework\Model\Resource\Db\AbstractDb
{
    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_date;

    /**
     * @param \Magento\Framework\Model\Resource\Db\Context $context
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param string|null $connectionName
     */
    public function __construct(
        \Magento\Framework\Model\Resource\Db\Context $context,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        $connectionName = null
    ) {
        $this->_date = $date;
        parent::__construct($context, $connectionName);
    }

    /**
     * Initialize connection and define resource
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('log_visitor_online', 'visitor_id');
    }

    /**
     * Prepare online visitors for collection
     *
     * @param \Magento\Log\Model\Visitor\Online $object
     * @return $this
     * @throws \Exception
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function prepare(\Magento\Log\Model\Visitor\Online $object)
    {
        if ($object->getUpdateFrequency() + $object->getPrepareAt() > time()) {
            return $this;
        }

        $adapter = $this->getConnection();
        $adapter = $this->getConnection();

        $adapter->beginTransaction();

        try {
            $adapter->delete($this->getMainTable());

            $visitors = [];
            $lastUrls = [];

            // retrieve online visitors general data

            $lastDate = $this->_date->gmtTimestamp() - $object->getOnlineInterval() * 60;

            $select = $adapter->select()->from(
                $this->getTable('log_visitor'),
                ['visitor_id', 'first_visit_at', 'last_visit_at', 'last_url_id']
            )->where(
                'last_visit_at >= ?',
                $adapter->formatDate($lastDate)
            );

            $query = $adapter->query($select);
            while ($row = $query->fetch()) {
                $visitors[$row['visitor_id']] = $row;
                $lastUrls[$row['last_url_id']] = $row['visitor_id'];
                $visitors[$row['visitor_id']]['visitor_type'] = \Magento\Customer\Model\Visitor::VISITOR_TYPE_VISITOR;
                $visitors[$row['visitor_id']]['customer_id'] = null;
            }

            if (!$visitors) {
                $this->commit();
                return $this;
            }

            // retrieve visitor remote addr
            $select = $adapter->select()->from(
                $this->getTable('log_visitor_info'),
                ['visitor_id', 'remote_addr']
            )->where(
                'visitor_id IN(?)',
                array_keys($visitors)
            );

            $query = $adapter->query($select);
            while ($row = $query->fetch()) {
                $visitors[$row['visitor_id']]['remote_addr'] = $row['remote_addr'];
            }

            // retrieve visitor last URLs
            $select = $adapter->select()->from(
                $this->getTable('log_url_info'),
                ['url_id', 'url']
            )->where(
                'url_id IN(?)',
                array_keys($lastUrls)
            );

            $query = $adapter->query($select);
            while ($row = $query->fetch()) {
                $visitorId = $lastUrls[$row['url_id']];
                $visitors[$visitorId]['last_url'] = $row['url'];
            }

            // retrieve customers
            $select = $adapter->select()->from(
                $this->getTable('log_customer'),
                ['visitor_id', 'customer_id']
            )->where(
                'visitor_id IN(?)',
                array_keys($visitors)
            );

            $query = $adapter->query($select);
            while ($row = $query->fetch()) {
                $visitors[$row['visitor_id']]['visitor_type'] = \Magento\Customer\Model\Visitor::VISITOR_TYPE_CUSTOMER;
                $visitors[$row['visitor_id']]['customer_id'] = $row['customer_id'];
            }

            foreach ($visitors as $visitorData) {
                unset($visitorData['last_url_id']);

                $adapter->insertForce($this->getMainTable(), $visitorData);
            }

            $adapter->commit();
        } catch (\Exception $e) {
            $adapter->rollBack();
            throw $e;
        }

        $object->setPrepareAt();

        return $this;
    }
}
