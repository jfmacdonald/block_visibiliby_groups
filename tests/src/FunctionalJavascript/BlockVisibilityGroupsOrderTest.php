<?php

namespace Drupal\Tests\block_visibility_groups\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\block_visibility_groups\Entity\BlockVisibilityGroup;

/**
 * Class BlockVisibilityGroupsOrderTest
 *
 * @package Drupal\Tests\block_visibility_groups\FunctionalJavascript
 * @group block_visibility_groups
 */
class BlockVisibilityGroupsOrderTest extends JavascriptTestBase {

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
      'view the administration theme',
    ]);
    $this->drupalLogin($this->adminUser);

    // Create some block_visibility_groups.
    $configs['request'] = [
      'id'     => 'request_path',
      'pages'  => '/node/*',
      'negate' => 0,
    ];
    $this->groups = [
      'group_a' => $this->createGroup($configs, 'group_a'),
      'group_b' => $this->createGroup($configs, 'group_b'),
    ];

    // Start with no placed blocks
    $this->blocks = [];

    // Enable some test blocks.
    // weights must be between -N/2 and +N/2, with N the number of blocks
    $this->blockValues = [
      'g1' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'g1', 'label' => 'G1'],
        'group'        => '',
        'setup_weight' => -3,
      ],
      'g2' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'g2', 'label' => 'G2'],
        'group'        => '',
        'setup_weight' => -2,
      ],
      'g3' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'g3', 'label' => 'G3'],
        'group'        => '',
        'setup_weight' => -1,
      ],
      'a1' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'a1', 'label' => 'A1'],
        'group'        => 'group_a',
        'setup_weight' => 1,
      ],
      'a2' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'a2', 'label' => 'A2'],
        'group'        => 'group_a',
        'setup_weight' => 3,
      ],
      'b1' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'b1', 'label' => 'B1'],
        'group'        => 'group_b',
        'setup_weight' => 0,
      ],
      'b2' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'b2', 'label' => 'B2'],
        'group'        => 'group_b',
        'setup_weight' => 2,
      ],
      'b3' => [
        'plugin_id'    => 'system_powered_by_block',
        'settings'     => ['id' => 'b3', 'label' => 'B3'],
        'group'        => 'group_b',
        'setup_weight' => 4,
      ],

    ];

    // get block admin page
    $this->path = $this->getBlockLayoutPage('ALL-GROUP');

    // place blocks
    $this->placeGlobalBlocks();
    $this->placeGroupBlocks('group_a');
    $this->placeGroupBlocks('group_b');

    // reset to All Groups and set weights
    $this->getBlockLayoutPage('ALL-GROUP');
    $this->setWeights();


    $edit = [];
    foreach ($this->blockValues as $id => $values) {
      $edit["blocks[$id][weight]"] = (string) $values['setup_weight'];
    }
    // $this->submitForm($edit, t('Save blocks'), 'block-admin-display-form');
    $this->assertSession()->pageTextNotContains('You have unsaved changes.');

  }


  /**
   * Verify that setUp() placed and ordered blocks as intended.
   */
  public function testSetup() {
    $expected_order = ['g1', 'g2', 'g3', 'b1', 'a1', 'b2', 'a2', 'b3'];
    $this->getBlockLayoutPage('ALL-GROUP');
    $this->assertBlockOrder($expected_order);
  }

  /**
   * Check that blocks have full weight range available
   */
  public function testWeightRange() {

    // check placed
    $this->assertBlocksPlaced();

    // test weight range for a global block
    $this->getBlockLayoutPage();
    $this->assertWeightRange('g1');

    // test weight range for a group block
    $this->getBlockLayoutPage('group_a');
    $this->assertWeightRange('a1');
  }

  /**
   * Test reordering blocks by changing weights
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testBlockReorderByWeight() {

    // show Group A with Globals
    $this->getBlockLayoutPage('group_a');
    $this->setShowGlobal(TRUE);

    // swap a1 and a2 weights and save
    $this->setWeights(['a1' => 3, 'a2' => 1]);

    // Retest block order
    $this->getBlockLayoutPage('ALL-GROUP');
    $expected_order = ['g1', 'g2', 'g3', 'b1', 'a2', 'b2', 'a1', 'b3'];
    $this->assertBlockOrder($expected_order,
      "Reorder Group A with 'Show global' checked.");

    // now, show Group A only and reset original weights
    $this->getBlockLayoutPage('group_a');
    $this->setShowGlobal(FALSE);
    $this->setWeights(['a1' => 1, 'a2' => 3]);

    // Retest block order
    $this->getBlockLayoutPage('ALL-GROUP');
    $expected_order = ['g1', 'g2', 'g3', 'b1', 'a1', 'b2', 'a2', 'b3'];
    $this->assertBlockOrder($expected_order,
      "Reorder Group A with 'Show global' unchecked.");
  }

  /**
   * Test block reordering by dragging rows
   *
   */
  public function testBlockReorderByDrag() {
    // show Group A with Globals
    $this->getBlockLayoutPage('group_a');
    $this->setShowGlobal(TRUE);

    // swap a1 and a2 order and save
    $this->dragBlockToTarget('a2', 'a1');
    $this->submitForm([], 'Save blocks');

    // test order
    $expected_order = ['g1', 'g2', 'g3', 'b1', 'a2', 'b2', 'a1', 'b3'];
    $this->getBlockLayoutPage('ALL-GROUP');
    $this->assertBlockOrder($expected_order,
      "Dragging a2 to a1 with 'Show Global Blocks' checked.");

    // show Group A without Globals
    $this->getBlockLayoutPage('group_a');
    $this->setShowGlobal(FALSE);

    // swap a1 and a2 again to restore original order
    $this->dragBlockToTarget('a1', 'a2');

    // test order
    $expected_order = ['g1', 'g2', 'g3', 'b1', 'a1', 'b2', 'a2', 'b3'];
    $this->getBlockLayoutPage('ALL-GROUP');
    $this->assertBlockOrder($expected_order,
      "Dragging a1 to a2 with 'Show Global Blocks' unchecked.");
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

  /**
   * Are blocks ordered in Block layouts page as expected?
   *
   * @param $expected_ids
   */
  protected function assertBlockOrder($expected_ids, $message = '') {
    $page = $this->getSession()->getPage();
    $rows = $page->findAll('xpath', '//tr[@data-drupal-selector]');
    $actual_ids = [];
    foreach ($rows as $row) {
      $selector = $row->getAttribute('data-drupal-selector');
      foreach ($expected_ids as $id) {
        if ($selector == "edit-blocks-$id") {
          $actual_ids[] = $id;
          break;
        }
      }
    }
    $this->assertSame($expected_ids, $actual_ids,
      'Unexpected order ' . $message);
  }

  protected function dragBlockToTarget($dragged_block_id, $target_block_id) {
    $page = $this->getSession()->getPage();
    $handle = ".//a[@class='tabledrag-handle']";
    $query = "//tr[@data-drupal-selector='edit-blocks-$dragged_block_id']";
    $row = $page->find('xpath', $query);
    $this->assertNotNull($row, "failed $query");
    $dragged = $row->find('xpath', $handle);
    $this->assertNotNull($dragged, "failed $query/$handle");
    $query = "//tr[@data-drupal-selector='edit-blocks-$target_block_id']";
    $row = $page->find('xpath', $query);
    $this->assertNotNull($row, "failed $query");
    $target = $row->find('xpath', $handle);
    $this->assertNotNull($target, "failed $query/$handle");
    $dragged->dragTo($target);
    $this->assertSession()->pageTextContains('You have unsaved changes.');
    $this->submitForm([], t('Save blocks'), 'block-admin-display-form');
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

  /**
   * Set block weights using form
   *
   * @param array $block_weights
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function setWeights($block_weights = []) {
    $page = $this->getSession()->getPage();
    if (count($block_weights)) {
      foreach ($block_weights as $id => $weight) {
        $select = $page->findField("blocks[$id][weight]");
        $select->selectOption((string) $weight);
      }
    }
    else {
      foreach ($this->blockValues as $id => $values) {
        $select = $page->findField("blocks[$id][weight]");
        $select->selectOption((string) $values['setup_weight']);
      }
    }
    $this->submitForm([], t('Save blocks'), 'block-admin-display-form');
  }

  /**
   * Place global blocks used in test
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
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
    $this->getSession()->getPage()->pressButton('Save blocks');
  }

  /**
   * @param $group_id
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
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
    $this->getSession()->getPage()->pressButton('Save blocks');
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

