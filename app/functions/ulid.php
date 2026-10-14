<?php

/**
 * ULID用Crockford Base32
 */
function encodeUlidTimestamp(int $timestamp): string {
  $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
  $result = '';

  for ($i = 0; $i < 10; $i++) {
    $index = $timestamp & 0x1F;
    $result = $alphabet[$index] . $result;
    $timestamp = intdiv($timestamp, 32);
  }

  return $result;
}

/**
 * 80bitのランダム値をULID用Base32に変換
 */
function encodeUlidRandom(string $bytes): string {
  $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
  $result = '';
  $buffer = 0;
  $bits = 0;

  for ($i = 0; $i < strlen($bytes); $i++) {
    $buffer = ($buffer << 8) | ord($bytes[$i]);
    $bits += 8;

    while ($bits >= 5) {
      $bits -= 5;
      $index = ($buffer >> $bits) & 0x1F;
      $result .= $alphabet[$index];

      if ($bits > 0) {
        $buffer &= (1 << $bits) - 1;
      } else {
        $buffer = 0;
      }
    }
  }

  return $result;
}

/**
 * ULIDを生成
 */
function generateUlid(): string {
  $timestamp = (int) floor(microtime(true) * 1000);

  if ($timestamp > 0xFFFFFFFFFFFF) {
    throw new RuntimeException(
      'ULID timestamp is out of range.'
    );
  }

  $timestampPart = encodeUlidTimestamp($timestamp);
  $randomPart = encodeUlidRandom(
    random_bytes(10)
  );

  return $timestampPart . $randomPart;
}