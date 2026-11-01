<?php

declare(strict_types=1);

namespace Drupal\Tests\policy_evidence_interface\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\policy_evidence_interface\Commands\McpServerCommands;
use Drupal\policy_evidence_interface\Controller\McpServerController;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets up a small site for the module's kernel tests.
 *
 * The content model matches what the README asks for:
 * - "article" has body, field_tags and field_pdf_article.
 * - "page" has none of those fields.
 * The term "restricted" is in the disallowed tag list; "public" is not.
 */
abstract class PolicyEvidenceKernelTestBase extends KernelTestBase {

  use ContentTypeCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use UserCreationTrait;

  /**
   * The five tools provided by the main module.
   */
  public const CORE_TOOLS = [
    'get_node',
    'get_site_info',
    'list_content_types',
    'read_node_pdf',
    'search_nodes',
  ];

  /**
   * Drupal finds no schema for the module's config; see the tests README.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = ['policy_evidence_interface.settings'];

  /**
   * Run as a normal user: without this, user 1 would bypass access checks.
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'file',
    'node',
    'taxonomy',
    'policy_evidence_interface',
  ];

  /**
   * The disallowed term.
   */
  protected TermInterface $restrictedTerm;

  /**
   * A term that is not disallowed.
   */
  protected TermInterface $publicTerm;

  /**
   * The user who runs the tools, with "access content".
   */
  protected UserInterface $reader;

  /**
   * A real directory used as public://, so PDFs have a real path on disk.
   */
  protected string $publicDirectory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['filter', 'node', 'policy_evidence_interface']);

    // Kernel tests keep public:// in a virtual file system that has no real
    // path, but read_node_pdf needs one, so use a temporary directory.
    $this->publicDirectory = sys_get_temp_dir() . '/pei_test_' . $this->randomMachineName();
    mkdir($this->publicDirectory);
    $this->setSetting('file_public_path', $this->publicDirectory);

    $this->createContentModel();

    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    $this->restrictedTerm = Term::create(['vid' => 'tags', 'name' => 'restricted']);
    $this->restrictedTerm->save();
    $this->publicTerm = Term::create(['vid' => 'tags', 'name' => 'public']);
    $this->publicTerm->save();

    $this->config('policy_evidence_interface.settings')
      ->set('access_control_tags.disallow_tag_ids', [(int) $this->restrictedTerm->id()])
      ->save();

    $this->reader = $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Not set if setUp() failed early; skip so the real error stays visible.
    if (isset($this->publicDirectory)) {
      \Drupal::service('file_system')->deleteRecursive($this->publicDirectory);
    }
    parent::tearDown();
  }

  /**
   * Creates the "article" and "page" content types and their fields.
   */
  private function createContentModel(): void {
    // createContentType() also adds the body field.
    $this->createContentType(['type' => 'article', 'name' => 'Article']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    $this->createEntityReferenceField(
      'node',
      'article',
      'field_tags',
      'Tags',
      'taxonomy_term',
      'default',
      [],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    );
    FieldStorageConfig::create([
      'field_name' => 'field_pdf_article',
      'entity_type' => 'node',
      'type' => 'file',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_pdf_article',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();
  }

  /**
   * Creates and saves a published article owned by the reader.
   */
  protected function createArticle(string $title, array $values = []): NodeInterface {
    $node = Node::create($values + [
      'type' => 'article',
      'title' => $title,
      'uid' => $this->reader->id(),
      'status' => 1,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Creates $count published articles titled "Housing 1", "Housing 2", ...
   */
  protected function createHousingArticles(int $count): void {
    for ($i = 1; $i <= $count; $i++) {
      $this->createArticle("Housing $i");
    }
  }

  /**
   * Creates an article with a tests/fixtures PDF in field_pdf_article.
   */
  protected function createArticleWithPdf(string $fixture, array $values = []): NodeInterface {
    $source = __DIR__ . '/../../fixtures/' . $fixture;
    $this->assertFileExists($source, "Test setup: fixture $fixture exists.");
    $file = \Drupal::service('file.repository')->writeData(file_get_contents($source), 'public://' . $fixture);

    return $this->createArticle('Report with PDF', $values + [
      'field_pdf_article' => ['target_id' => $file->id()],
    ]);
  }

  /**
   * Runs a tool plugin with the given arguments.
   */
  protected function runTool(string $plugin_id, array $arguments): mixed {
    return \Drupal::service('plugin.manager.policy_evidence_interface.tool')
      ->createInstance($plugin_id)
      ->execute($arguments);
  }

  /**
   * Allows 100 calls per minute to each core tool.
   *
   * After install every limit is 0, which blocks all tool calls.
   */
  protected function allowToolCalls(): void {
    $this->config('policy_evidence_interface.settings')
      ->set('rate_limit', [
        'enabled' => TRUE,
        'global' => ['limit' => 100, 'window_seconds' => 60],
        'tools' => array_fill_keys(self::CORE_TOOLS, ['limit' => 100]),
      ])
      ->save();
  }

  /**
   * Gives the reader the mcp_connector role that the HTTP endpoint requires.
   */
  protected function grantMcpRole(): void {
    $this->createRole([], 'mcp_connector', 'MCP connector');
    $this->reader->addRole('mcp_connector');
    $this->reader->save();
  }

  /**
   * Sends a raw request body to the HTTP endpoint controller.
   *
   * The request is made the current request, as it is for a real HTTP call,
   * because the controller reads some values from \Drupal::request().
   */
  protected function sendHttp(string $method, string $body = '', string $url = 'http://localhost/_mcp'): Response {
    $request = Request::create($url, $method, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    $request_stack = $this->container->get('request_stack');
    $request_stack->push($request);
    try {
      return McpServerController::create($this->container)->handle($request);
    }
    finally {
      $request_stack->pop();
    }
  }

  /**
   * Sends one JSON-RPC message over HTTP and returns the decoded response.
   */
  protected function httpRpc(string $method, array $params = []): array {
    $body = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    return json_decode($this->sendHttp('POST', $body)->getContent(), TRUE);
  }

  /**
   * Sends one JSON-RPC message through the stdio command (drush mcp:server).
   *
   * The command reads stdin in a loop, so this calls its dispatch() method
   * directly. A NULL $method or $id leaves that key out of the message.
   * Returns NULL when the command sends no response.
   */
  protected function stdioRpc(?string $method, array $params = [], ?int $id = 1): ?array {
    $commands = new class(
      $this->container->get('plugin.manager.policy_evidence_interface.tool'),
      $this->container->get('plugin.manager.policy_evidence_interface.resource'),
      $this->container->get('policy_evidence_interface.rate_limiter'),
    ) extends McpServerCommands {

      /**
       * Makes the protected dispatch() callable from tests.
       */
      public function dispatchForTest(array $message): ?array {
        return $this->dispatch($message);
      }

    };
    $message = array_filter(
      ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params],
      fn ($value) => $value !== NULL,
    );
    return $commands->dispatchForTest($message);
  }

}
