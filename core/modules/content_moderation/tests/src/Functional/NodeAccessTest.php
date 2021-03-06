<?php

namespace Drupal\Tests\content_moderation\Functional;

/**
 * Tests permission access control around nodes.
 *
 * @group content_moderation
 */
class NodeAccessTest extends ModerationStateTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'content_moderation',
    'block',
    'block_content',
    'node',
    'node_access_test_empty',
  ];

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'administer workflows',
    'access administration pages',
    'administer content types',
    'administer nodes',
    'view latest version',
    'view any unpublished content',
    'access content overview',
    'use editorial transition create_new_draft',
    'use editorial transition publish',
    'bypass node access',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
    $this->createContentTypeFromUi('Moderated content', 'moderated_content', TRUE);
    $this->grantUserPermissionToCreateContentOfType($this->adminUser, 'moderated_content');

    // Rebuild permissions because hook_node_grants() is implemented by the
    // node_access_test_empty module.
    node_access_rebuild();
  }

  /**
   * Verifies that a non-admin user can still access the appropriate pages.
   */
  public function testPageAccess() {
    $this->drupalLogin($this->adminUser);

    // Create a node to test with.
    $this->drupalPostForm('node/add/moderated_content', [
      'title[0][value]' => 'moderated content',
    ], t('Save and Create New Draft'));
    $node = $this->getNodeByTitle('moderated content');
    if (!$node) {
      $this->fail('Test node was not saved correctly.');
    }

    $view_path = 'node/' . $node->id();
    $edit_path = 'node/' . $node->id() . '/edit';
    $latest_path = 'node/' . $node->id() . '/latest';

    // Now make a new user and verify that the new user's access is correct.
    $user = $this->createUser([
      'use editorial transition create_new_draft',
      'view latest version',
      'view any unpublished content',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet($edit_path);
    $this->assertResponse(403);

    $this->drupalGet($latest_path);
    $this->assertResponse(403);
    $this->drupalGet($view_path);
    $this->assertResponse(200);

    // Publish the node.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm($edit_path, [], t('Save and Publish'));

    // Ensure access works correctly for anonymous users.
    $this->drupalLogout();

    $this->drupalGet($edit_path);
    $this->assertResponse(403);

    $this->drupalGet($latest_path);
    $this->assertResponse(403);
    $this->drupalGet($view_path);
    $this->assertResponse(200);

    // Create a forward revision for the 'Latest revision' tab.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm($edit_path, [
      'title[0][value]' => 'moderated content revised',
    ], t('Save and Create New Draft'));

    $this->drupalLogin($user);

    $this->drupalGet($edit_path);
    $this->assertResponse(403);

    $this->drupalGet($latest_path);
    $this->assertResponse(200);
    $this->drupalGet($view_path);
    $this->assertResponse(200);

    // Now make another user, who should not be able to see forward revisions.
    $user = $this->createUser([
      'use editorial transition create_new_draft',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet($edit_path);
    $this->assertResponse(403);

    $this->drupalGet($latest_path);
    $this->assertResponse(403);
    $this->drupalGet($view_path);
    $this->assertResponse(200);
  }

}
