<?php
/**
 * Обработчик формы заявки.
 * 1. Проверяет поля формы.
 * 2. Проверяет токен Яндекс SmartCaptcha на сервере.
 * 3. Отправляет заявку на почту.
 *
 * Требования: PHP 7.4+, расширения curl и mbstring, работающая функция mail().
 */

// ===================== НАСТРОЙКИ =====================
const MAIL_TO   = 'info@star-spb.com';    // куда приходят заявки
const MAIL_FROM = 'noreply@star-spb.com'; // отправитель: адрес на домене сайта
const SITE_NAME = 'star-spb.com';
const SMARTCAPTCHA_SERVER_KEY = 'ВАШ_СЕРВЕРНЫЙ_КЛЮЧ'; // «Ключ сервера» из консоли Yandex Cloud
// =====================================================

date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/** Отдаёт JSON-ответ и завершает скрипт. */
function respond($code, $ok, $error = '')
{
    http_response_code($code);
    echo json_encode(
        $ok ? ['ok' => true] : ['ok' => false, 'error' => $error],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/** Чистит значение: убирает управляющие символы, обрезает по длине. */
function clean($value, $maxLength, $multiline = false)
{
    $value = is_string($value) ? $value : '';
    // удаляем управляющие символы (для многострочного поля оставляем переводы строк)
    $pattern = $multiline ? '/[^\P{C}\n]+/u' : '/\p{C}+/u';
    $value = (string) preg_replace($pattern, ' ', $value);
    $value = trim($value);
    return mb_substr($value, 0, $maxLength);
}

/** Проверяет токен SmartCaptcha на стороне Яндекса. */
function verify_captcha($token, $ip)
{
    if ($token === '') {
        return false;
    }

    $ch = curl_init('https://smartcaptcha.cloud.yandex.ru/validate');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret' => SMARTCAPTCHA_SERVER_KEY,
            'token'  => $token,
            'ip'     => $ip,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Рекомендация Яндекса: при сбое самого сервиса (ответ не 200) не блокировать пользователей
    if ($code !== 200) {
        error_log("SmartCaptcha недоступна, HTTP $code — проверка пропущена");
        return true;
    }

    $json = json_decode((string) $body, true);
    return is_array($json) && ($json['status'] ?? '') === 'ok';
}

// ---------- 1. Только POST ----------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, false, 'Метод не поддерживается.');
}

// ---------- 2. Honeypot: скрытое поле, которое заполняют только боты ----------
if (!empty($_POST['website'])) {
    respond(200, true); // делаем вид, что всё хорошо, письмо не отправляем
}

// ---------- 3. Данные формы ----------
$name    = clean($_POST['name'] ?? '', 150);
$email   = clean($_POST['email'] ?? '', 254);
$phone   = clean($_POST['phone'] ?? '', 30);
$company = clean($_POST['company'] ?? '', 200);
$message = clean($_POST['message'] ?? '', 3000, true);
$agree   = !empty($_POST['agree']);
$token   = is_string($_POST['smart-token'] ?? null) ? $_POST['smart-token'] : '';
$ip      = $_SERVER['REMOTE_ADDR'] ?? '';

if ($name === '' || $email === '' || $phone === '' || $company === '') {
    respond(422, false, 'Заполните все обязательные поля.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false, 'Укажите корректный email.');
}
if (!preg_match('/^[0-9+\-()\s.]{5,30}$/', $phone)) {
    respond(422, false, 'Укажите корректный телефон.');
}
if (!$agree) {
    respond(422, false, 'Подтвердите согласие на обработку персональных данных.');
}

// ---------- 4. Капча ----------
if (!verify_captcha($token, $ip)) {
    respond(403, false, 'Не удалось пройти проверку на робота. Обновите капчу и попробуйте снова.');
}

// ---------- 5. Письмо ----------
$subject = 'Новая заявка с сайта ' . SITE_NAME;

$text  = "Новая заявка с сайта " . SITE_NAME . "\n\n";
$text .= "ФИО: $name\n";
$text .= "Email: $email\n";
$text .= "Телефон: $phone\n";
$text .= "Компания: $company\n\n";
$text .= "Запрос:\n" . ($message !== '' ? $message : '— не указан —') . "\n\n";
$text .= "----------\n";
$text .= 'Дата: ' . date('d.m.Y H:i') . " (МСК)\n";
$text .= "IP: $ip\n";

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
    'From: ' . mb_encode_mimeheader('Сайт ' . SITE_NAME, 'UTF-8', 'B') . ' <' . MAIL_FROM . '>',
    'Reply-To: ' . $email, // ответ из почтового клиента уйдёт клиенту (email уже проверен)
    'X-Mailer: PHP/' . phpversion(),
];

$sent = mail(
    MAIL_TO,
    mb_encode_mimeheader($subject, 'UTF-8', 'B'),
    chunk_split(base64_encode($text)),
    implode("\r\n", $headers),
    '-f' . MAIL_FROM // адрес отправителя для SPF; если хостинг ругается, уберите этот параметр
);

if (!$sent) {
    error_log('Не удалось отправить заявку через mail()');
    respond(500, false, 'Не удалось отправить заявку. Попробуйте позже или напишите на ' . MAIL_TO . '.');
}

respond(200, true);
