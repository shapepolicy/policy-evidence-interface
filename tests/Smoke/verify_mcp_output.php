<?php

declare(strict_types=1);

$responses = [];

while (($line = fgets(STDIN)) !== FALSE) {
  $line = trim($line);
  if ($line === '') {
    continue;
  }

  try {
    $response = json_decode($line, TRUE, flags: JSON_THROW_ON_ERROR);
  }
  catch (JsonException $exception) {
    fwrite(STDERR, "MCP returned invalid JSON: {$exception->getMessage()}\n");
    exit(1);
  }

  $responses[$response['id'] ?? 'notification'] = $response;
}

if (($responses[1]['result']['protocolVersion'] ?? NULL) !== '2024-11-05') {
  fwrite(STDERR, "MCP initialize response is missing or invalid.\n");
  exit(1);
}

$tools = $responses[2]['result']['tools'] ?? NULL;
if (!is_array($tools) || $tools === []) {
  fwrite(STDERR, "MCP tools/list returned no tools.\n");
  exit(1);
}

fwrite(STDOUT, sprintf("MCP launch smoke test passed with %d tools.\n", count($tools)));
