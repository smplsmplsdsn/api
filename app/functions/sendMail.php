<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * メール送信
 *
 * @param array $env 環境設定
 * @param string $subject メール件名
 * @param string $body メール本文
 * @return bool 送信成功時は true
 */
function sendMail(
  array $env,
  string $subject,
  string $body
): bool {

  $host = getConfigValue('GMAIL_SMTP_HOST', $env);
  $port = getConfigValue('GMAIL_SMTP_PORT', $env);
  $username = getConfigValue('GMAIL_SMTP_USERNAME', $env);
  $password = getConfigValue('GMAIL_SMTP_PASSWORD', $env);
  $from = getConfigValue('GMAIL_SMTP_FROM', $env);
  $to = getConfigValue('GMAIL_SMTP_TO', $env);

  if (
    !$host ||
    !$port ||
    !$username ||
    !$password ||
    !$from ||
    !$to
  ) {
    error_log('メール送信設定が不足しています。');

    return false;
  }

  $mail = new PHPMailer(true);

  try {
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) $port;

    $mail->CharSet = 'UTF-8';
    $mail->setFrom($from, 'ご意見箱');
    $mail->addAddress($to);

    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->isHTML(false);

    $mail->send();

    return true;

  } catch (Exception $e) {
    error_log('メール送信に失敗しました: ' . $mail->ErrorInfo);

    return false;
  }
}