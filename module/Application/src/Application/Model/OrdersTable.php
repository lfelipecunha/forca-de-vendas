<?php

namespace Application\Model;

use Application\Form;
use Application\Exception;

class OrdersTable extends AbstractTable
{
    protected function loadForm($identifier)
    {
        $form = null;
        switch ($identifier) {
            case 'create':
                $form = new Form\OrderForm();
                $form->setInputFilter(new Form\OrderFilter($this->getServiceLocator()));
                break;
        }
        return $form;
    }

    public function save($id, $data)
    {
        $items = null;
        if (isset($data['items'])) {
            $items = $data['items'];
            if (!is_array($items)) {
                $items = [$items];
            }
            unset($data['items']);
        }
        try {
            $this->beginTransaction();
            $id = parent::save($id, $data);

            if (!is_null($items)) {
                $this->syncItems($id, $items);
            }
            $this->commit();
        } catch (\Exception $e) {
            $this->rollback();
            if ($e instanceof Exception\UnknowRegistryException) {
                throw $e;
            }
            throw new Exception\RuntimeException($e->getMessage());
        }

        return $id;
    }

    public function syncItems($id, array $items)
    {
        $table = $this->getServiceLocator()->get('OrderItems');
        $productsTable = $this->getServiceLocator()->get('Application\Model\ProductsTable');
        $table->delete(array('order_id' => $id));
        foreach ($items as $item) {
            $product = $productsTable->find($item['product_id']);
            $item['price_id'] = $product['price_id'];
            $item['order_id'] = $id;
            $table->insert($item);
        }
        return $this;
    }

    protected function getBaseSelect($where, $options)
    {
        $select = parent::getBaseSelect($where, $options);
        $select
            ->join(
                ['c' => 'clients'],
                'c.id = orders.client_id',
                [
                    'client_name' => 'name',
                    'client_trading_name' => 'trading_name'
                ]
            )->join(
                ['d' => 'addresses'],
                'd.id = orders.deliver_address_id',
                [
                    'delivery_street' => 'street',
                    'delivery_number' => 'number',
                    'delivery_complement' => 'complement',
                    'delivery_neighborhood' => 'neighborhood',
                    'delivery_city' => 'city',
                    'delivery_state' => 'state',
                    'delivery_zipcode' => 'zipcode',
                ]
            )->join(
                ['b' => 'addresses'],
                'b.id = orders.charge_address_id',
                [
                    'billing_street' => 'street',
                    'billing_number' => 'number',
                    'billing_complement' => 'complement',
                    'billing_neighborhood' => 'neighborhood',
                    'billing_city' => 'city',
                    'billing_state' => 'state',
                    'billing_zipcode' => 'zipcode',
                ]
            )->join(
                ['pf' => 'payments_forms'],
                'pf.id = orders.payment_form_id',
                [
                    'payment_description' => 'description',
                    'payment_installments' => 'installments',
                    'payment_interest' => 'interest',
                ]
            )->join(
                ['p' => 'payments'],
                'pf.payment_id = p.id',
                ['payment_name' => 'name']
            )
            ->order(['date' => 'DESC']);

        return $select;
    }

    public function filterData($item)
    {
        $table = $this->getServiceLocator()->get('OrderItems');
        $select = $table->getSql()->select();
        $select
            ->join(['p' => 'products'], 'p.id = order_items.product_id', ['name'])
            ->join(['pr' => 'prices'], 'pr.id = order_items.price_id', ['price'])
            ->where(['order_id' => $item['id']]);
        $item['items'] = $table->selectWith($select)->toArray();
        return $item;
    }

    public function delete($where)
    {
        throw new Exception();
    }

    public function relatorioMensal()
    {
        $table = $this->getTable();
        $select = $table->getSql()->select();
        $select->join(['i' => 'order_items'], 'i.order_id = orders.id', [])
            ->join(['pr' => 'prices'], 'pr.id = i.price_id', [])
            ->join(['pf' => 'payments_forms'], 'pf.id = orders.payment_form_id', [])
            ->columns([
                'id',
                'total' => new \Zend\Db\Sql\Expression('SUM(pr.price * i.quantity) * (1 + pf.interest/100)'),
                'date'
            ])
            ->group(['orders.id']);
        $mainSelect = $table->getSql()->select();
        $mainSelect->join(['a' => $select], 'a.id = orders.id', [])
            ->columns([
                'total' => new \Zend\Db\Sql\Expression('SUM(a.total)'),
                'count' => new \Zend\Db\Sql\Expression('COUNT(orders.id)'),
                'date' => new \Zend\Db\Sql\Expression('CONCAT(MONTH(orders.date),\'/\', YEAR(orders.date))')
            ])
            ->group([
                new \Zend\Db\Sql\Expression('YEAR(orders.date)'),
                new \Zend\Db\Sql\Expression('MONTH(orders.date)')
            ])
            ->where('YEAR(orders.date) = '.date('Y'));
        return $table->selectWith($mainSelect)->toArray();
    }

    public function relatorioAnual()
    {
        $table = $this->getTable();
        $select = $table->getSql()->select();
        $select->join(['i' => 'order_items'], 'i.order_id = orders.id', [])
            ->join(['pr' => 'prices'], 'pr.id = i.price_id', [])
            ->join(['pf' => 'payments_forms'], 'pf.id = orders.payment_form_id', [])
            ->columns([
                'id',
                'total' => new \Zend\Db\Sql\Expression('SUM(pr.price * i.quantity) * (1 + pf.interest/100)'),
                'date'
            ])
            ->group(['orders.id']);
        $mainSelect = $table->getSql()->select();
        $mainSelect->join(['a' => $select], 'a.id = orders.id', [])
            ->columns([
                'total' => new \Zend\Db\Sql\Expression('SUM(a.total)'),
                'count' => new \Zend\Db\Sql\Expression('COUNT(orders.id)'),
                'date' => new \Zend\Db\Sql\Expression('YEAR(orders.date)')
            ])
            ->group([
                new \Zend\Db\Sql\Expression('YEAR(orders.date)'),
            ]);
        return $table->selectWith($mainSelect)->toArray();
    }
}
