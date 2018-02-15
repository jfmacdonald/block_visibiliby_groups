<?php

namespace Drupal\Tests\block_visibility_groups\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\block_visibility_groups\Entity\BlockVisibilityGroup;


class BlockVisibilityGroupsWeightTest extends BrowserTestBase {

  /**
   * Modules to install
   *
   * @var array
   */
  public static $modules = [
    'block',
    'block_visibility_groups',
    'devel',
    'kint',
  ];

  /**
   * Groups used in test
   *
   * @var array
   */
  protected $groups;

  /**
   * Blocks used for this test
   *
   * @var array
   */
  protected $blocks;

  /**
   * An array of block values to build
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
    $this->groups       = [
      'group1' => $this->createGroup($configs, 'group1'),
      'group2' => $this->createGroup($configs, 'group2'),
    ];

    // Enable some test blocks.
    // Weights *must* be between plus or minus the number of blocks.
    $this->blockValues = [
      'global_a' => [
        'plugin_id'   => 'system_powered_by_block',
        'settings'    => ['id' => 'global_a', 'label' => 'GlobalA'],
        'group'       => '',
        'test_weight' => '0',
      ],
      'global_b' => [
        'plugin_id'   => 'system_powered_by_block',
        'settings'    => ['id' => 'global_b', 'label' => 'GlobalB'],
        'group'       => '',
        'test_weight' => '3',
      ],
      'group1_a' => [
        'plugin_id'   => 'system_powered_by_block',
        'settings'    => ['id' => 'group1_a', 'label' => 'Group1A'],
        'group'       => 'group1',
        'test_weight' => '1',
      ],
      'group1_b' => [
        'plugin_id'   => 'system_powered_by_block',
        'settings'    => ['id' => 'group1_b', 'label' => 'Group1B'],
        'group'       => 'group1',
        'test_weight' => '4',
      ],

      'group2_a' => [
        'plugin_id'   => 'system_powered_by_block',
        'settings'    => ['id' => 'group2_a', 'label' => 'Group2A'],
        'group'       => 'group2',
        'test_weight' => '2',
      ],

      'group2_b' => [
        'plugin_id'   => 'system_powered_by_block',
        'settings'    => ['id' => 'group2_b', 'label' => 'Group2B'],
        'group'       => 'group2',
        'test_weight' => '5',
      ],

    ];

    // get block admin page
    $path = $this->getBlockLayoutPage();

    // order by setting weights
    $button = $this->getSession()->getPage()->findButton('Show row weights');
    if ($button) {
      $button->click();
    }

    $global_blocks = array_filter($this->blockValues, function ($values) {
      return $values['group'] == '';
    });
    $group1_blocks = array_filter($this->blockValues, function ($values) {
      return $values['group'] == 'group1';
    });
    $group2_blocks = array_filter($this->blockValues, function ($values) {
      return $values['group'] == 'group2';
    });

    // Place the global blocks
    $this->blocks = [];
    foreach ($global_blocks as $block_id => $values) {
      $this->blocks[$block_id] = $this->drupalPlaceBlock(
        $values['plugin_id'],
        $values['settings']
      );
    }

    // Place Group1 blocks
    $path = $this->getBlockLayoutPage('group1');
    foreach ($group1_blocks as $block_id => $values) {
      $group                   = $this->groups['group1'];
      $this->blocks[$block_id] = $this->placeBlockInGroup(
        $values['plugin_id'],
        $group->id(),
        $values['settings']);
    }

    // Place Group2 blocks
    $path = $this->getBlockLayoutPage('group2');
    foreach ($group2_blocks as $block_id => $values) {
      $group                   = $this->groups['group2'];
      $this->blocks[$block_id] = $this->placeBlockInGroup(
        $values['plugin_id'],
        $group->id(),
        $values['settings']);
    }


    // Set weights
    $path = $this->getBlockLayoutPage('ALL-GROUP');

    $edit = [];
    foreach ($this->blockValues as $block_id => $values) {

      $edit["blocks[$block_id][weight]"] = $values['test_weight'];
    }

    $this->drupalPostForm($path, $edit, t('Save blocks'));

  }

  public function testSetBlockWeights() {

    $this->assertBlockOrder([
      'global_a',
      'group1_a',
      'group2_a',
      'global_b',
      'group1_b',
      'group2_b',
    ]);

  }

  protected
  function assertBlockOrder(
    $block_ids
  ) {

    // sanity check
    foreach ($block_ids as $id) {
      $this->assertArrayHasKey($id, $this->blocks, "We have block $id");
    }
    // test order
    for ($i = 1; $i < count($block_ids); ++$i) {
      $prev_id = $block_ids[$i - 1];
      $this_id = $block_ids[$i];
      $this->assertGreaterThanOrEqual(
        $this->blocks[$prev_id]->getWeight(),
        $this->blocks[$this_id]->getWeight(),
        "Block $this_id is heavier than block $prev_id."
      );
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
  protected
  function getBlockLayoutPage(
    $group_id = ''
  ) {
    $default_theme = $this->config('system.theme')->get('default');
    $path          = 'admin/structure/block/list/' . $default_theme;
    $valid_ids     = array_keys($this->groups);
    $valid_ids[]   = 'ALL-GROUP';
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
   * Create a block visibility group
   *
   * @param array $configs conditions configurations
   * @param string $id defaults to random
   *
   * @return \Drupal\Core\Entity\EntityInterface|static
   */
  protected
  function createGroup(
    $configs,
    $id = ''
  ) {
    $id       = $id ? $id : $this->randomMachineName();
    $settings = [
      'id'    => $id,
      'label' => $this->randomString(),
    ];
    $group    = BlockVisibilityGroup::create($settings);
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
  protected
  function placeBlockInGroup(
    $plugin_id,
    $group_id,
    $settings = []
  ) {
    $settings += [
      'label_display' => 'visible',
      'label'         => $this->randomMachineName(),
      'visibility'    => [
        'condition_group' => ['block_visibility_group' => $group_id],
      ],
    ];
    $block    = $this->drupalPlaceBlock($plugin_id, $settings);

    return $block;
  }

}

