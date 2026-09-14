<?php

declare(strict_types=1);

/** Small contract test for the user-facing BAG house-number notation. */
$format = static function (array $address): string {
  $number = trim((string) ($address['house_number'] ?? ''));
  $letter = trim((string) ($address['house_letter'] ?? ''));
  $addition = trim((string) ($address['addition'] ?? ''));
  $formatted = $number . $letter;
  if ($addition !== '') {
    $formatted .= '-' . ltrim($addition, '-');
  }
  return $formatted;
};

$cases = [
  [['house_number' => '87'], '87'],
  [['house_number' => '87', 'house_letter' => 'H'], '87H'],
  [['house_number' => '87', 'addition' => '1'], '87-1'],
  [['house_number' => '87', 'house_letter' => 'H', 'addition' => '1'], '87H-1'],
  [['house_number' => '87', 'addition' => '-2'], '87-2'],
];

foreach ($cases as [$input, $expected]) {
  $actual = $format($input);
  if ($actual !== $expected) {
    fwrite(STDERR, sprintf("Expected %s, got %s\n", $expected, $actual));
    exit(1);
  }
}

echo "BAG address formatting contract passed.\n";
