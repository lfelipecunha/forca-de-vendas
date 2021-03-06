<?php

namespace ApplicationTest\Model;

use ApplicationTest\Bootstrap;

class GroupsTableTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        Bootstrap::getServiceManager()->get('FixturesRunner')->uses(array('groups', 'users'));
        parent::setUp();
    }

    public function testFind()
    {
        $table = Bootstrap::getServiceManager()->get('Application\Model\GroupsTable');
        $result = $table->find(1);
        $this->assertEquals(1, $result['id']);

        $this->setExpectedException('Application\Exception\UnknowRegistryException');
        $table->find(999999);
    }

    public function testFetchAll()
    {
        $table = Bootstrap::getServiceManager()->get('Application\Model\GroupsTable');
        $result = $table->fetchAll();
        $this->assertInstanceOf('Zend\Db\ResultSet\ResultSet', $result);
        $this->assertNotEmpty($result->toArray());

        $result = $table->fetchAll(null, array('paginated' => true));
        $this->assertInstanceOf('Zend\Paginator\Paginator', $result);
        $this->assertNotEmpty($result->getCurrentItems());
    }

    public function testCreate()
    {
        $table = Bootstrap::getServiceManager()->get('Application\Model\GroupsTable');
        $data = array(
            'name' => 'Admin',
        );

        $result = $table->save(null, $data);
        $this->assertTrue(is_numeric($result));
        $group = $table->find($result);
        $this->assertNotEmpty($group);
        $this->assertEquals('Admin', $group['name']);

        $this->setExpectedException('Application\Exception\RuntimeException');
        $table->save(null, array());
    }

    public function testUpdate()
    {
        $table = Bootstrap::getServiceManager()->get('Application\Model\GroupsTable');
        $data = array(
            'name' => 'admin_up',
        );

        $result = $table->save(1, $data);
        $this->assertEquals(1, $result);
        $group = $table->find($result);
        $this->assertNotEmpty($group);
        $this->assertEquals('admin_up', $group['name']);

        $this->setExpectedException('Application\Exception\UnknowRegistryException');
        $table->save(999999, $data);
    }

    public function testUpdateException()
    {
        $table = Bootstrap::getServiceManager()->get('Application\Model\GroupsTable');
        $this->setExpectedException('Application\Exception\RuntimeException');
        $table->save(1, array());
    }

    public function testDelete()
    {
        $table = Bootstrap::getServiceManager()->get('Application\Model\GroupsTable');
        $gateway = $table->getTable();
        $select = $gateway->getSql()->select();
        $select->columns(array('count' => new \Zend\Db\Sql\Expression('COUNT(*)')));
        $preResult = $gateway->selectWith($select)->current();
        $result = $table->delete(array('id' => 2));
        $this->assertTrue($result);
        $postResult = $gateway->selectWith($select)->current();
        $this->assertEquals($preResult['count'] -1, $postResult['count']);

        $preResult = $gateway->selectWith($select)->current();
        $result = $table->delete(array('id' => 1));
        $this->assertFalse($result);
        $postResult = $gateway->selectWith($select)->current();
        $this->assertEquals($preResult['count'], $postResult['count']);
    }

    public function testGetForms()
    {
        $table = Bootstrap::getServiceManager()->get('Application\Model\GroupsTable');
        $this->assertInstanceOf('Zend\Form\Form', $table->getForm('create'));
        $this->assertInstanceOf('Zend\Form\Form', $table->getForm('edit'));
    }

}
