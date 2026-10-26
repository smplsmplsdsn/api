<?php
/**
 * JSON内の文字列からHTMLタグを除去
 *
 * @param string $value JSON用の文字列
 * @return $string HTMLタグを除去したJSON用の文字列
 */
function stripHtmlFromJsonValue($value) {
  if (is_string($value)) {
    return strip_tags($value);
  }

  if (is_array($value)) {
    foreach ($value as $key => $item) {
      $value[$key] = stripHtmlFromJsonValue($item);
    }
  }

  return $value;
}