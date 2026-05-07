<?php
namespace Drupal\policy_evidence_interface\Controller;
use Drupal\Core\Controller\ControllerBase;
use Smalot\PdfParser\Parser;

class PdfReaderController extends ControllerBase {

  public function hello() {
    return [
      '#markup' => 'Hello',
    ];
  }

  public function state() {
    $config = $this->config('policy_evidence_interface.settings');

    $pdf_file = $config->get('pdf_file');
    $pdf_root_path = $config->get('pdf_root_path');
    $drupal_root = DRUPAL_ROOT;
    
    $full_path = DRUPAL_ROOT . '/' . $pdf_root_path . '/' . $pdf_file;    

    $file_exists = file_exists($full_path);
    $is_readable = is_readable($full_path);
    
    return [
      '#markup' => $this->t(
        'Current path: @full_path <br> Exists: @exists <br> Readable: @readable',
        [
          '@full_path' => $full_path,
          '@exists' => $file_exists ? 'YES' : 'NO',
          '@readable' => $is_readable ? 'YES' : 'NO',
        ]
      ),
    ];
  }

  public function read() {
    $config = $this->config('policy_evidence_interface.settings');

    $pdf_file = $config->get('pdf_file');
    $pdf_root_path = $config->get('pdf_root_path');

    $full_path = DRUPAL_ROOT . '/' . $pdf_root_path . '/' . $pdf_file;
    $parser = new Parser();
    $pdf = $parser->parseFile($full_path);

    $text = $pdf->getText();

    return [
      '#markup' => nl2br($text),
    ];
  }

}
?>