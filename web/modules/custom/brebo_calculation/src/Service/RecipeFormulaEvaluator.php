<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

/**
 * Evaluates simple arithmetic recipe formulas without eval().
 *
 * Supported:
 * - numeric literals
 * - variables: letters, numbers and underscores
 * - operators: +, -, *, /
 * - parentheses
 * - functions: min, max, round, ceil, floor
 */
final class RecipeFormulaEvaluator {

  /**
   * @param array<string,int|float|string> $variables
   */
  public function evaluate(?string $formula, array $variables = []): float {
    $formula = trim((string) $formula);
    if ($formula === '') {
      return 0.0;
    }

    $tokens = $this->tokenize($formula);
    $position = 0;
    $value = $this->parseExpression($tokens, $position, $variables);
    if ($position !== count($tokens)) {
      throw new \InvalidArgumentException('Unexpected token in recipe formula.');
    }
    if (!is_finite($value)) {
      throw new \InvalidArgumentException('Recipe formula produced a non-finite value.');
    }
    return $value;
  }

  /** @return list<string> */
  private function tokenize(string $formula): array {
    if (!preg_match_all('/\s*(\d+(?:\.\d+)?|[A-Za-z_][A-Za-z0-9_]*|[()+\-*\/,])\s*/', $formula, $matches)) {
      throw new \InvalidArgumentException('Invalid recipe formula.');
    }
    $tokens = $matches[1];
    $normalized = preg_replace('/\s+/', '', $formula);
    if (implode('', $tokens) !== $normalized) {
      throw new \InvalidArgumentException('Unsupported character in recipe formula.');
    }
    return array_values($tokens);
  }

  /**
   * @param list<string> $tokens
   * @param array<string,int|float|string> $variables
   */
  private function parseExpression(array $tokens, int &$position, array $variables): float {
    $value = $this->parseTerm($tokens, $position, $variables);
    while (($tokens[$position] ?? NULL) === '+' || ($tokens[$position] ?? NULL) === '-') {
      $operator = $tokens[$position++];
      $rhs = $this->parseTerm($tokens, $position, $variables);
      $value = $operator === '+' ? $value + $rhs : $value - $rhs;
    }
    return $value;
  }

  /**
   * @param list<string> $tokens
   * @param array<string,int|float|string> $variables
   */
  private function parseTerm(array $tokens, int &$position, array $variables): float {
    $value = $this->parseFactor($tokens, $position, $variables);
    while (($tokens[$position] ?? NULL) === '*' || ($tokens[$position] ?? NULL) === '/') {
      $operator = $tokens[$position++];
      $rhs = $this->parseFactor($tokens, $position, $variables);
      if ($operator === '/' && abs($rhs) < PHP_FLOAT_EPSILON) {
        throw new \InvalidArgumentException('Division by zero in recipe formula.');
      }
      $value = $operator === '*' ? $value * $rhs : $value / $rhs;
    }
    return $value;
  }

  /**
   * @param list<string> $tokens
   * @param array<string,int|float|string> $variables
   */
  private function parseFactor(array $tokens, int &$position, array $variables): float {
    $token = $tokens[$position] ?? NULL;
    if ($token === NULL) {
      throw new \InvalidArgumentException('Unexpected end of recipe formula.');
    }

    if ($token === '+' || $token === '-') {
      $position++;
      $value = $this->parseFactor($tokens, $position, $variables);
      return $token === '-' ? -$value : $value;
    }

    if ($token === '(') {
      $position++;
      $value = $this->parseExpression($tokens, $position, $variables);
      if (($tokens[$position] ?? NULL) !== ')') {
        throw new \InvalidArgumentException('Missing closing parenthesis in recipe formula.');
      }
      $position++;
      return $value;
    }

    if (is_numeric($token)) {
      $position++;
      return (float) $token;
    }

    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token)) {
      $position++;
      if (($tokens[$position] ?? NULL) === '(') {
        return $this->parseFunction($token, $tokens, $position, $variables);
      }
      if (!array_key_exists($token, $variables) || !is_numeric($variables[$token])) {
        throw new \InvalidArgumentException('Unknown or non-numeric recipe variable: ' . $token);
      }
      return (float) $variables[$token];
    }

    throw new \InvalidArgumentException('Invalid recipe formula token.');
  }

  /**
   * @param list<string> $tokens
   * @param array<string,int|float|string> $variables
   */
  private function parseFunction(string $name, array $tokens, int &$position, array $variables): float {
    $allowed = ['min', 'max', 'round', 'ceil', 'floor'];
    if (!in_array($name, $allowed, TRUE)) {
      throw new \InvalidArgumentException('Unsupported recipe function: ' . $name);
    }
    $position++;
    $arguments = [];
    if (($tokens[$position] ?? NULL) !== ')') {
      while (TRUE) {
        $arguments[] = $this->parseExpression($tokens, $position, $variables);
        if (($tokens[$position] ?? NULL) !== ',') {
          break;
        }
        $position++;
      }
    }
    if (($tokens[$position] ?? NULL) !== ')') {
      throw new \InvalidArgumentException('Missing closing parenthesis in recipe function.');
    }
    $position++;

    return match ($name) {
      'min' => $arguments ? min($arguments) : throw new \InvalidArgumentException('min() requires arguments.'),
      'max' => $arguments ? max($arguments) : throw new \InvalidArgumentException('max() requires arguments.'),
      'round' => count($arguments) === 1 ? round($arguments[0]) : throw new \InvalidArgumentException('round() requires one argument.'),
      'ceil' => count($arguments) === 1 ? ceil($arguments[0]) : throw new \InvalidArgumentException('ceil() requires one argument.'),
      'floor' => count($arguments) === 1 ? floor($arguments[0]) : throw new \InvalidArgumentException('floor() requires one argument.'),
    };
  }

}
