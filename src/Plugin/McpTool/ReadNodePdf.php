<?php

namespace Drupal\policy_evidence_interface\Plugin\McpTool;

use Drupal\policy_evidence_interface\Plugin\McpToolBase;
use Smalot\PdfParser\Parser;
/**
 * PDF parse has issues with certain fonts and pdf formats 
 * If that text is needed in the future another process needs to be considered
 * at this stage it's only affecting some intro pages 
 * 
 * If required
 * tesseract could be used 
 * or alternativly pdfs can be extracted using a differnt tool 
 * and this tool will return the cached extracts
 */

/**
 * Parses the PDF attached to a Drupal node by its ID.
 *
 * @McpTool(
 *   id = "read_node_pdf",
 *   tool_name = "read_node_pdf",
 *   description = "Parse and extract text from a PDF file attached to a published Drupal node by its numeric node ID (nid)."
 *   rate_limit = {
 *     "limit" = 10,
 *     "window" = 60
 * }
 * )
 */
class ReadNodePdf extends McpToolBase {

  /**
   * {@inheritdoc}
   */
  protected function inputSchema(): array {
    return [
      'type'       => 'object',
      'properties' => [
        'nid' => [
          'type'        => 'integer',
          'description' => 'The numeric node ID (nid) of the node whose PDF attachment should be parsed.',
        ],
        'page_start' => [
          'type'        => 'integer',
          'description' => 'First page to extract (1-based index). Defaults to 1.',
          'default'     => 1,
        ],
      ],
      'required' => ['nid'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(array $arguments): mixed {
    $nid = (int) ($arguments['nid'] ?? 0);
    $page_start = (int) ($arguments['page_start'] ?? 1);

    if (!$nid) {
      return ['error' => 'A valid nid is required.'];
    }

    // Load the node.
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

    if (!$node) {
      return ['error' => "Node {$nid} not found."];
    }

    if (!$node->isPublished()) {
      return ['error' => "Node {$nid} is not published."];
    }

    // Check for a PDF file field on the node.
    $pdf_field = NULL;
    foreach (['field_pdf_article', 'field_pdf', 'field_file'] as $candidate) {
      if ($node->hasField($candidate) && !$node->get($candidate)->isEmpty()) {
        $pdf_field = $candidate;
        break;
      }
    }

    if (!$pdf_field) {
      return ['error' => "Node {$nid} has no PDF file attached."];
    }

    // Load the file entity.
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $node->get($pdf_field)->entity;

    if (!$file) {
      return ['error' => "Could not load the file entity from node {$nid}."];
    }

    $full_path = \Drupal::service('file_system')->realpath($file->getFileUri());

    if (!$full_path || !file_exists($full_path)) {
      return ['error' => "PDF file not found on disk for node {$nid}. Path: {$full_path}"];
    }

    if (!is_readable($full_path)) {
      return ['error' => "PDF file is not readable for node {$nid}. Path: {$full_path}"];
    }

    // Parse the PDF.
    try {
      $parser = new Parser();
      $pdf    = $parser->parseFile($full_path);
      $pages  = $pdf->getPages();
      $total_pages = count($pages);

      // Convert 1-based input to 0-based index.
      $page_index = $page_start - 1;

      if ($page_index < 0 || $page_index >= $total_pages) {
        return ['error' => "Page {$page_start} is out of range. This PDF has {$total_pages} page(s)."];
      }

      $text = $pages[$page_index]->getText();

      // remove null bytes
      $text = str_replace("\0", '', $text);

      // decode escaped unicode (like \u0022 etc.)
      $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      
      // remove control characters but keep newlines/tabs
      $text = preg_replace('/[^\P{C}\n\t]+/u', '', $text);

      // collapse excessive whitespace
      $text = preg_replace('/[ \t]+/', ' ', $text);
      $text = preg_replace('/\n{3,}/', "\n\n", $text);
      
      // normalize encoding
      $text = mb_convert_encoding($text, 'UTF-8', 'auto');
      
      // checks if the text is garbage due to font encodings
      $alphaRatio = preg_match_all('/[a-zA-Z]/', $text) / max(strlen($text), 1);
      // 0.1 if almost no real letters, it's garbage
      if ($alphaRatio < 0.2) {
        return ['error' => 'This PDF page contains non-extractable text (likely font-encoded or scanned).'];
      }

    }
    catch (\Exception $e) {
      return ['error' => 'Failed to parse PDF: ' . $e->getMessage()];
    }

    return [
      'nid'         => $nid,
      'title'       => $node->label(),
      'file_name'   => $file->getFilename(),
      'file_uri'    => $file->getFileUri(),
      'full_path'   => $full_path,
      'page'        => $page_start,
      'total_pages' => $total_pages,
      'text'        => $text,
    ];
  }

}