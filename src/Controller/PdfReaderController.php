<?php

declare(strict_types=1);

namespace Drupal\policy_evidence_interface\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Smalot\PdfParser\Parser;

final class PdfReaderController extends ControllerBase {

  public function state(): array {
    [$full_path, $error] = $this->resolvePdfPath();
    $file_exists = $full_path !== NULL && file_exists($full_path);
    $is_readable = $full_path !== NULL && is_readable($full_path);

    $message = $error ?? $full_path ?? $this->t('Path resolution failed.')->render();
    return [
      '#markup' => $this->t(
        'Current path: @full_path <br> Exists: @exists <br> Readable: @readable',
        [
          '@full_path' => $message,
          '@exists' => $file_exists ? 'YES' : 'NO',
          '@readable' => $is_readable ? 'YES' : 'NO',
        ]
      ),
    ];
  }

  public function read(): array {
    [$full_path, $error] = $this->resolvePdfPath();
    if ($error !== NULL) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Unable to read PDF: @reason', ['@reason' => $error]),
        '#cache' => ['max-age' => 0],
      ];
    }

    if ($full_path === NULL || !file_exists($full_path) || !is_readable($full_path)) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('PDF file is missing or unreadable.'),
        '#cache' => ['max-age' => 0],
      ];
    }

    try {
      $parser = new Parser();
      $pdf = $parser->parseFile($full_path);
      $text = $pdf->getText();
    }
    catch (\Throwable $exception) {
      $this->getLogger('policy_evidence_interface')->error('Failed parsing PDF @path: @message', [
        '@path' => $full_path,
        '@message' => $exception->getMessage(),
      ]);
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Failed to parse PDF file.'),
        '#cache' => ['max-age' => 0],
      ];
    }

    return [
      '#type' => 'markup',
      '#markup' => nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * @return array{0: ?string, 1: ?string}
   */
  private function resolvePdfPath(): array {
    $config = $this->config('policy_evidence_interface.settings');
    $pdf_file = (string) ($config->get('pdf_file') ?? '');
    $pdf_root_path = (string) ($config->get('pdf_root_path') ?? '');

    if ($pdf_file === '' || $pdf_root_path === '') {
      return [NULL, 'PDF file/path is not configured.'];
    }
    if (str_contains($pdf_file, '/') || str_contains($pdf_file, '\\')) {
      return [NULL, 'PDF filename must not include directory separators.'];
    }

    $public_files_dir = realpath(DRUPAL_ROOT . '/sites/default/files');
    $root_dir = realpath(DRUPAL_ROOT . '/' . ltrim($pdf_root_path, '/'));
    if ($public_files_dir === FALSE || $root_dir === FALSE) {
      return [NULL, 'Configured directory does not exist.'];
    }
    if (!str_starts_with($root_dir, $public_files_dir)) {
      return [NULL, 'Configured directory must be inside sites/default/files.'];
    }

    $full_path = $root_dir . DIRECTORY_SEPARATOR . $pdf_file;
    $resolved_file = realpath($full_path);
    if ($resolved_file !== FALSE && !str_starts_with($resolved_file, $root_dir)) {
      return [NULL, 'Resolved file path escapes configured directory.'];
    }

    return [$full_path, NULL];
  }

}
