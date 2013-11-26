<?php

/**
 * @file
 * Contains \Drupal\views\Tests\EntityViewsControllerTest.
 */

namespace Drupal\views\Tests {

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
  use Drupal\entity_test\Entity\EntityTestMulRev;
  use Drupal\Tests\UnitTestCase;
use Drupal\views\EntityViewsController;

/**
 * Tests the generic entity views controller.
 *
 * @covers \Drupal\views\EntityViewsController
 */
class EntityViewsControllerTest extends UnitTestCase {

  /**
   * Entity info to use in this test.
   *
   * @var array
   */
  protected $baseEntityInfo = array(
    'base_table' => 'entity_test',
    'label' => 'Entity test',
    'entity_keys' => array(
      'id' => 'id',
    ),
  );

  /**
   * The mocked entity storage.
   *
   * @var \Drupal\Core\Entity\DatabaseStorageController|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityStorage;

  /**
   * The mocked entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityManager;

  /**
   * The mocked module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleHandler;

  /**
   * The mocked translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $translationManager;

  /**
   * The tested entity views controller.
   *
   * @var \Drupal\views\Tests\TestEntityViewsController
   */
  protected $viewsController;

  public static function getInfo() {
    return array(
      'name' => 'Entity views controller test',
      'description' => 'Tests code of the entity views controller plugin.',
      'group' => 'Views Plugins',
    );
  }

  protected function setUp() {
    $this->entityStorage = $this->getMockBuilder('Drupal\Core\Entity\DatabaseStorageController')
      ->disableOriginalConstructor()
      ->getMock();
    $this->entityManager = $this->getMock('Drupal\Core\Entity\EntityManagerInterface');

    $this->entityManager->expects($this->any())
      ->method('getDefinition')
      ->will($this->returnValueMap(array(
        array('user', static::userEntityInfo()),
      )));

    $this->translationManager = $this->getStringTranslationStub();
    $this->moduleHandler = $this->getMock('Drupal\Core\Extension\ModuleHandlerInterface');

    $this->viewsController = new TestEntityViewsController('entity_test', $this->baseEntityInfo, $this->entityStorage, $this->entityManager, $this->moduleHandler, $this->translationManager);
  }

  /**
   * Tests base tables.
   */
  public function testBaseTables() {
    $data = $this->viewsController->viewsData();

    $this->assertEquals('id', $data['entity_test']['table']['base']['field']);
    $this->assertEquals('Entity test', $data['entity_test']['table']['base']['title']);
    $this->assertFalse(isset($data['entity_test_mul_property_data']));
    $this->assertFalse(isset($data['revision_table']));
    $this->assertFalse(isset($data['revision_data_table']));
  }


  /**
   * Tests data_table support.
   */
  public function testDataTable() {
    $entity_info = $this->baseEntityInfo += array(
      'data_table' => 'entity_test_mul_property_data',
    );
    $this->viewsController->setEntityInfo($entity_info);
    $this->viewsController->setEntityType('entity_test_mul');

    // Tests the join definition between the base and the data table.
    $data = $this->viewsController->viewsData();
    $field_views_data = $data['entity_test_mul_property_data'];
    $this->assertEquals(array('entity_test' => array('left_field' => 'id', 'field' => 'id')), $field_views_data['table']['join']);
    $this->assertFalse(isset($data['revision_table']));
    $this->assertFalse(isset($data['revision_data_table']));
  }

  /**
   * Tests revision table support.
   */
  public function testRevisionTable() {
    $entity_info = $this->baseEntityInfo += array(
      'revision_table' => 'entity_test_mulrev_revision',
      'revision_data_table' => 'entity_test_mulrev_property_revision',
    );
    $entity_info['entity_keys']['revision'] = 'revision_id';
    $this->viewsController->setEntityInfo($entity_info);
    $this->viewsController->setEntityType('entity_test_mulrev');

    $data = $this->viewsController->viewsData();

    // Tests the join definition between the base and the revision table.
    $revision_data = $data['entity_test_mulrev_revision'];
    $this->assertEquals(array('entity_test' => array('left_field' => 'id', 'field' => 'id')), $revision_data['table']['join']);
    $revision_data = $data['entity_test_mulrev_property_revision'];
    $this->assertEquals(array('entity_test_mulrev_revision' => array('left_field' => 'revision_id', 'field' => 'revision_id')), $revision_data['table']['join']);
    $this->assertFalse(isset($data['data_table']));
  }

  /**
   * Tests fields on the base table.
   */
  public function testBaseTableFields() {
    $this->entityManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->with('entity_test')
      ->will($this->returnCallback(function() {
        return EntityTest::baseFieldDefinitions('entity_test');
      }));
    $this->viewsController->setSchemaFields(array('entity_test' => array('id', 'uuid', 'type', 'langcode', 'name', 'user_id')));
    $data = $this->viewsController->viewsData();

    $this->assertNumericField($data['entity_test']['id']);
    $this->assertUuidField($data['entity_test']['uuid']);
    $this->assertStringField($data['entity_test']['type']);
    $this->assertLanguageField($data['entity_test']['langcode']);
    $this->assertStringField($data['entity_test']['name']);

    $this->assertEntityReferenceField($data['entity_test']['user_id']);
    $relationship = $data['entity_test']['user_id']['relationship'];
    $this->assertEquals('users', $relationship['base']);
    $this->assertEquals('uid', $relationship['base field']);
  }

  /**
   * Tests fields on the data table.
   */
  public function testDataTableFields() {
    $this->entityManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->with('entity_test_mul')
      ->will($this->returnCallback(function() {
        return EntityTestMul::baseFieldDefinitions('entity_test_mul');
      }));
    $this->viewsController->setSchemaFields(array(
      'entity_test_mul' => array('id', 'uuid', 'type', 'langcode'),
      'entity_test_mul_property_data' => array('id', 'langcode', 'default_langcode', 'name', 'user_id'),
    ));

    $entity_info = $this->baseEntityInfo += array(
      'data_table' => 'entity_test_mul_property_data',
    );
    $entity_info['base_table'] = 'entity_test_mul';
    $this->viewsController->setEntityInfo($entity_info);
    $this->viewsController->setEntityType('entity_test_mul');

    $data = $this->viewsController->viewsData();

    // Check the base fields.
    $this->assertNumericField($data['entity_test_mul']['id']);
    $this->assertUuidField($data['entity_test_mul']['uuid']);
    $this->assertStringField($data['entity_test_mul']['type']);
    $this->assertLanguageField($data['entity_test_mul']['langcode']);
    // Also ensure that field_data only fields don't appear on the base table.
    $this->assertFalse(isset($data['entity_test_mul']['name']));
    $this->assertFalse(isset($data['entity_test_mul']['user_id']));

    // Check the data fields.
    $this->assertNumericField($data['entity_test_mul_property_data']['id']);
    $this->assertLanguageField($data['entity_test_mul_property_data']['langcode']);
    // @todo Plach said the default language should not be exposed.
    $this->assertStringField($data['entity_test_mul_property_data']['name']);

    $this->assertEntityReferenceField($data['entity_test_mul_property_data']['user_id']);
    $relationship = $data['entity_test_mul_property_data']['user_id']['relationship'];
    $this->assertEquals('users', $relationship['base']);
    $this->assertEquals('uid', $relationship['base field']);
  }

  /**
   * Tests fields on the revision table.
   */
  public function testRevisionTableFields() {
    $this->entityManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->with('entity_test_mulrev')
      ->will($this->returnCallback(function() {
        return EntityTestMulRev::baseFieldDefinitions('entity_test_mul');
      }));
    $this->viewsController->setSchemaFields(array(
      'entity_test_mulrev' => array('id', 'revision_id', 'uuid', 'type'),
      'entity_test_mulrev_revision' => array('id', 'revision_id', 'langcode'),
      'entity_test_mulrev_property_data' => array('id', 'revision_id', 'langcode', 'default_langcode', 'name', 'user_id'),
      'entity_test_mulrev_property_revision' => array('id', 'revision_id', 'langcode', 'default_langcode', 'name', 'user_id'),
    ));

    $entity_info = $this->baseEntityInfo += array(
      'data_table' => 'entity_test_mul_property_data',
      'revision_table' => 'entity_test_mulrev_revision',
      'revision_data_table' => 'entity_test_mulrev_property_revision',
    );
    $entity_info['base_table'] = 'entity_test_mulrev';
    $this->viewsController->setEntityInfo($entity_info);
    $this->viewsController->setEntityType('entity_test_mulrev');

    $data = $this->viewsController->viewsData();

    // Check the base fields.
    $this->assertNumericField($data['entity_test_mulrev']['id']);
    $this->assertNumericField($data['entity_test_mulrev']['revision_id']);
    $this->assertUuidField($data['entity_test_mulrev']['uuid']);
    $this->assertStringField($data['entity_test_mulrev']['type']);

    // Also ensure that field_data only fields don't appear on the base table.
    $this->assertFalse(isset($data['entity_test_mulrev']['name']));
    $this->assertFalse(isset($data['entity_test_mulrev']['langcode']));
    $this->assertFalse(isset($data['entity_test_mulrev']['user_id']));

    // Check the revision fields.
    $this->assertNumericField($data['entity_test_mulrev_revision']['id']);
    $this->assertNumericField($data['entity_test_mulrev_revision']['revision_id']);
    $this->assertLanguageField($data['entity_test_mulrev_revision']['langcode']);
    // Also ensure that field_data only fields don't appear on the revision table.
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['name']));
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['langcode']));
    $this->assertFalse(isset($data['entity_test_mulrev_revision']['user_id']));

    // @todo check property and revision property data.
  }

  /**
   * Tests views data for a string field.
   *
   * @param array $data
   *   The views data to check.
   */
  public function assertStringField($data) {
    $this->assertEquals('standard', $data['field']['id']);
    $this->assertEquals('string', $data['filter']['id']);
    $this->assertEquals('string', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  /**
   * Tests views data for a UUID field.
   *
   * @param array $data
   *   The views data to check.
   */
  public function assertUuidField($data) {
    // @todo Can we provide additional support for UUIDs in views?
    $this->assertEquals('standard', $data['field']['id']);
    $this->assertEquals('string', $data['filter']['id']);
    $this->assertEquals('string', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  /**
   * Tests views data for a numeric field.
   *
   * @param array $data
   *   The views data to check.
   */
  public function assertNumericField($data) {
    $this->assertEquals('numeric', $data['field']['id']);
    $this->assertEquals('numeric', $data['filter']['id']);
    $this->assertEquals('numeric', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  /**
   * Tests views data for a language field.
   *
   * @param array $data
   *   The views data to check.
   */
  public function assertLanguageField($data) {
    $this->assertEquals('language', $data['field']['id']);
    $this->assertEquals('language', $data['filter']['id']);
    $this->assertEquals('language', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  public function assertEntityReferenceField($data) {
    $this->assertEquals('numeric', $data['field']['id']);
    $this->assertEquals('numeric', $data['filter']['id']);
    $this->assertEquals('numeric', $data['argument']['id']);
    $this->assertEquals('standard', $data['sort']['id']);
  }

  /**
   * Returns entity info for the user entity.
   *
   * @return array
   */
  public static function userEntityInfo() {
    return array(
      'id' => 'user',
      'label' => 'User',
      'base_table' => 'users',
      'entity_keys' => array(
        'id' => 'uid',
        'uuid' => 'uuid',
      ),
    );
  }

}

class TestEntityViewsController extends EntityViewsController {

  protected $schemaFields = array();

  public function setSchemaFields($fields) {
    $this->schemaFields = $fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalSchemaFieldsSql($table) {
    return isset($this->schemaFields[$table]) ? $this->schemaFields[$table] : array();
  }

  public function setEntityInfo(array $entity_info) {
    $this->entityInfo = $entity_info;
  }

  public function setEntityType($entity_type) {
    $this->entityType = $entity_type;
  }

}

}

namespace {
  use Drupal\Component\Utility\String;

  if (!function_exists('t')) {
    function t($string, array $args = array()) {
      return String::format($string, $args);
    }
  }
}
