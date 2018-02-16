<?php

namespace Drupal\Tests\block_visibility_groups\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\block_visibility_groups\Entity\BlockVisibilityGroup;

/**
 * Class BlockVisibilityGroupsWeightTest tests block weight settings
 *
 * @package Drupal\Tests\block_visibility_groups\Functional
 *
 * @group block_visibility_groups
 *
 */
class BlockVisibilityGroupsWeightTest extends BrowserTestBase {

  /**
   * Modules to install
   *
   * @var array
   */
  public static $modules = [
    'block',
    'block_visibility_groups',
  ];

  /**
   * Groups used in test
   *
   * @var array
   */
  protected $groups;

  /**
   * Placed blocks
   *
   * @var array
   */
  protected $blocks;

  /**
   * An array of values for blocks
   *
   * @var array
   */
  protected $blockValues;

  /**
   * The virtual user administrating the test
   *
   * @var
   */
  protected $adminUser;

  /**
   * Page URL
   *
   * @var string
   */
  protected $path;

  /**
   * Set up the test
   */
  protected function setUp() {
    parent::setUp();

    // Create and log in an administrative user.
    $this->adminUser = $this->drupalCreateUser([
      'administer blocks',
      'access administration pages',
    ]);
    $this->drupalLogin($this->adminUser);

    // Create some block_visibility_groups.
    $configs['request'] = [
      'id'     => 'request_path',
      'pages'  => '/node/*',
      'negate' => 0,
    ];
    $this->groups = [
      'group1' => $this->createGroup($configs, 'group1'),
      'group2' => $this->createGroup($configs, 'group2'),
    ];

    // Start with no placed blocks
    $this->blocks = [];

    // Enable some test blocks.
    $this->blockValues = [
      'global_a' => [
        'plugin_id' => 'system_powered_by_block',
        'settings'  => ['id' => 'global_a', 'label' => 'GlobalA'],
        'group'     => '',
      ],
      'global_b' => [
        'plugin_id' => 'system_powered_by_block',
        'settings'  => ['id' => 'global_b', 'label' => 'GlobalB'],
        'group'     => '',
      ],
      'group1_a' => [
        'plugin_id' => 'system_powered_by_block',
        'settings'  => ['id' => 'group1_a', 'label' => 'Group1A'],
        'group'     => 'group1',
      ],
      'group1_b' => [
        'plugin_id' => 'system_powered_by_block',
        'settings'  => ['id' => 'group1_b', 'label' => 'Group1B'],
        'group'     => 'group1',
      ],

      'group2_a' => [
        'plugin_id' => 'system_powered_by_block',
        'settings'  => ['id' => 'group2_a', 'label' => 'Group2A'],
        'group'     => 'group2',
      ],

      'group2_b' => [
        'plugin_id' => 'system_powered_by_block',
        'settings'  => ['id' => 'group2_b', 'label' => 'Group2B'],
        'group'     => 'group2',
      ],

    ];

    // get block admin page
    $this->path = $this->getBlockLayoutPage('ALL-GROUP');

    // in this test, order blocks by setting weights
    $button = $this->getSession()->getPage()->findButton('Show row weights');
    if ($button) {
      $button->pressButton('Show row weights');
    }

  }

  public function testBlockOrder() {

    // intended block order (lighter to heavier)
    $block_ids = ['global_a', 'group1_a', 'global_b', 'group1_b'];

    // place blocks
    $this->placeGlobalBlocks();
    $this->placeGroupBlocks('group1');

    // Select group1 with "Show Global Blocks" checked
    $this->getBlockLayoutPage('ALL-GROUP');

    // set weights -- valid range is [-2:2] (set by number of blocks)
    foreach ($block_ids as $idx => $block_id) {
      $this->setBlockWeight($block_id, $idx - 1);
    }
    $this->assertSession()->pageTextNotContains('You have unsaved changes.');

    // Check order as set
    $this->assertBlocksPlaced();
    $this->assertBlockOrder($block_ids);

    // Uncheck "Show Global Blocks" with group1 selected
    $this->getBlockLayoutPage('group1');
    $this->setShowGlobal(FALSE);
    $this->getSession()->getPage()->pressButton('Save blocks');

    // Retest block order
    $this->assertBlockOrder($block_ids);
  }

  public function testWeightRange() {
    $this->placeGlobalBlocks();
    $this->placeGroupBlocks('group1');
    $this->placeGroupBlocks('group2');

    // check placed
    $this->assertBlocksPlaced();

    // test weight range for a global block
    $this->getBlockLayoutPage();
    $this->assertWeightRange('global_a');

    // test weight range for a group block
    $this->getBlockLayoutPage('group1');
    $this->assertWeightRange('group1_a');
  }

  /**
   * Assert that all $this->blocks are indeed placed
   */
  protected function assertBlocksPlaced() {
    // load blocks layout page, selecting All Groups.
    $this->getBlockLayoutPage('ALL-GROUP');
    $page = $this->getSession()->getPage();

    $block_ids = array_keys($this->blocks);

    // check that blocks are there.
    foreach ($block_ids as $block_id) {
      $weight_select = $page->findField("blocks[$block_id][weight]");
      $this->assertNotNull($weight_select, "Block $block_id on page.");
    }
  }

  /**
   * Assert that the number of weights for a block is
   * at least the total number of placed blocks.
   *
   * @see BlockVisibilityGroupdListBuilder::parent::buildBlocksForm()
   *
   * @param $block_id
   */
  protected function assertWeightRange($block_id) {

    // we should have a placed block
    $this->assertTrue(key_exists($block_id, $this->blocks),
      "Block $block_id is not placed.");

    // count placed blocks
    $count = count($this->blocks);

    // apply appropriate query when the block is in a group
    $group_id = $this->blockValues[$block_id]['group'];
    if ($group_id) {
      $this->getBlockLayoutPage($group_id);
      $this->setShowGlobal(FALSE);
    }
    else {
      $this->getBlockLayoutPage();
    }
    $page = $this->getSession()->getPage();

    // find and verify select field
    $weight_select = $page->findField("blocks[$block_id][weight]");
    $this->assertTrue($weight_select, "No weight select field for $block_id.");

    // assert number of weight select options
    $options = $weight_select->findAll('xpath', './option');
    $values = array_map(function ($option) {
      return $option->getValue();
    }, $options);
    $this->assertGreaterThanOrEqual($count, count($values),
      "with $count placed blocks, $block_id is only allowed weights " .
      join(' ', $values));
  }


  protected function assertBlockOrder($block_ids) {

    $this->setShowGlobal(TRUE);
    $page = $this->getSession()->getPage();

    // sanity check and get weights;
    $weight = [];
    foreach ($block_ids as $id) {
      $this->assertArrayHasKey($id, $this->blocks, "Unplaced block $id");
      $select = $page->findField("blocks[$id][weight]");
      if ($select) {
        $weight[] = $select->getValue();
      }
    }
    // test order
    for ($i = 1; $i < count($weight); ++$i) {
      $this->assertGreaterThan($weight[$i - 1], $weight[$i],
        "weights out of order: " . join(' ', $weight));
    }
  }

  /**
   * HTTP get and return path to admin block layout page with selected
   * block visibility group, specified by its id.
   *
   * @param string $group_id
   *
   * @return string (path URL)
   */
  protected function getBlockLayoutPage($group_id = '') {
    $default_theme = $this->config('system.theme')->get('default');
    $path = 'admin/structure/block/list/' . $default_theme;
    $valid_ids = array_keys($this->groups);
    $valid_ids[] = 'ALL-GROUP';
    if (!$group_id || !in_array($group_id, $valid_ids)) {
      $group_id = 'UNSET-GROUP';
    }
    $this->drupalGet($path, [
      'query' => [
        'block_visibility_group' => $group_id,
      ],
    ]);
    return $path;
  }

  /**
   * Check or uncheck the 'Show Global Blocks' button
   *
   * @param bool $check
   */
  protected function setShowGlobal($check) {
    $checkbox = $this->getSession()
                     ->getPage()
                     ->findField('block_visibility_group_show_global');
    if ($checkbox) {
      if ($check) {
        $checkbox->check();
      }
      else {
        $checkbox->uncheck();
      }
    }
  }


  protected function setBlockWeight($block_id, $weight) {
    $page = $this->getSession()->getPage();
    $select = $page->findField("blocks[$block_id][weight]");
    // verify we can find select field and set the weight
    $this->assertTrue($select, "No weight select for $block_id");
    $this->assertTrue(
      $select->find('xpath', "./option[@value='$weight']"),
      "No weight-select option $weight for $block_id."
    );

    // assert number of weight select options
    $select->setValue($weight);
    // $this->submitForm([], 'Save blocks');
    $this->getSession()->getPage()->pressButton('Save blocks');
    $this->assertEquals($select->getValue(), $weight, "($block_id) getValue()");

  }

  /**s
   * Place global blocks
   *
   */
  protected function placeGlobalBlocks() {

    // Find global block values
    $block_values = array_filter($this->blockValues, function ($values) {
      return $values['group'] == '';
    });

    // Place the global blocks
    foreach ($block_values as $block_id => $values) {
      $this->blocks[$block_id] = $this->drupalPlaceBlock(
        $values['plugin_id'],
        $values['settings']
      );
    }
  }

  /**
   * Place blocks in a block visibility group
   *
   * @param $group_id
   */
  protected function placeGroupBlocks($group_id) {

    // Find blocks assigned to group in setUp
    $block_values = array_filter($this->blockValues,
      function ($values) use ($group_id) {
        return $values['group'] == $group_id;
      });

    // Place blocks
    if (count($block_values)) {
      $this->path = $this->getBlockLayoutPage($group_id);
      foreach ($block_values as $block_id => $values) {
        $group = $this->groups[$group_id];
        $this->blocks[$block_id] = $this->placeBlockInGroup(
          $values['plugin_id'],
          $group->id(),
          $values['settings']);
      }
    }
  }

  /**
   * Create a block visibility group
   *
   * @param array $configs conditions configurations
   * @param string $id defaults to random
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   */
  protected function createGroup($configs, $id = '') {
    $id = $id ? $id : $this->randomMachineName();
    $settings = [
      'id'    => $id,
      'label' => $this->randomString(),
    ];
    $group = BlockVisibilityGroup::create($settings);
    $group->save();
    foreach ($configs as $config) {
      $group->addCondition($config);
    }
    $group->save();
    return $group;
  }

  /**
   * Create and place a block in a block visibility group.
   *
   * @param $plugin_id
   * @param $group_id
   * @param array $settings
   *
   * @return \Drupal\block\Entity\Block
   */
  protected function placeBlockInGroup($plugin_id, $group_id, $settings = []) {
    $settings += [
      'label_display' => 'visible',
      'label'         => $this->randomMachineName(),
      'visibility'    => [
        'condition_group' => ['block_visibility_group' => $group_id],
      ],
    ];
    $block = $this->drupalPlaceBlock($plugin_id, $settings);

    return $block;
  }

}

