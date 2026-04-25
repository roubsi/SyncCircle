<?php

declare(strict_types=1);

function app_config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'app_name' => 'SyncCircle',
        'app_url' => '',
        'timezone' => 'UTC',
        'mail' => [
            'enabled' => false,
            'from' => '',
        ],
    ];

    $configFile = dirname(__DIR__) . '/config.php';
    $custom = file_exists($configFile) ? require $configFile : [];
    $config = array_replace_recursive($defaults, is_array($custom) ? $custom : []);

    date_default_timezone_set((string) $config['timezone']);

    return $config;
}

function app_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function data_file_path(): string
{
    return app_path('data/meetings.json');
}

function ensure_data_file(): void
{
    $directory = dirname(data_file_path());

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    if (!file_exists(data_file_path())) {
        file_put_contents(data_file_path(), "[]\n", LOCK_EX);
    }
}

function read_meetings(): array
{
    ensure_data_file();

    $contents = file_get_contents(data_file_path());
    $decoded = json_decode($contents !== false ? $contents : '[]', true);

    if (!is_array($decoded)) {
        throw http_error(500, 'Meetings data is corrupted.');
    }

    usort($decoded, static function (array $left, array $right): int {
        return strcmp((string) ($left['startAt'] ?? ''), (string) ($right['startAt'] ?? ''));
    });

    return $decoded;
}

function write_meetings(array $meetings): void
{
    $json = json_encode(array_values($meetings), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw http_error(500, 'Unable to save meetings.');
    }

    file_put_contents(data_file_path(), $json . PHP_EOL, LOCK_EX);
}

function normalize_meeting(array $input, array $existing = []): array
{
    $title = clean_text($input['title'] ?? null, 'Please add a meeting title.');
    $hostName = clean_text($input['hostName'] ?? null, 'Please add the organizer name.');
    $activityType = clean_text($input['activityType'] ?? null, 'Please describe the activity.');
    $platform = clean_text($input['platform'] ?? null, 'Please add where the group will meet.');
    $startAt = clean_text($input['startAt'] ?? null, 'Please choose a date and time.');
    $description = trim((string) ($input['description'] ?? ''));

    $inviteeInput = $input['invitees'] ?? [];
    if (!is_array($inviteeInput)) {
        throw http_error(400, 'Invitees must be a list of email addresses.');
    }

    $invitees = [];
    foreach ($inviteeInput as $email) {
        $cleaned = clean_email($email);
        if ($cleaned !== '') {
            $invitees[] = $cleaned;
        }
    }

    if ($invitees === []) {
        throw http_error(400, 'Add at least one email address.');
    }

    $timezone = new DateTimeZone((string) app_config()['timezone']);

    try {
        $date = new DateTime($startAt, $timezone);
    } catch (Throwable $exception) {
        throw http_error(400, 'The selected date is invalid.');
    }

    $date->setTimezone(new DateTimeZone('UTC'));

    return [
        'id' => (string) ($existing['id'] ?? bin2hex(random_bytes(16))),
        'title' => $title,
        'hostName' => $hostName,
        'startAt' => $date->format('Y-m-d\TH:i:s\Z'),
        'platform' => $platform,
        'activityType' => $activityType,
        'description' => $description,
        'invitees' => array_values(array_unique($invitees)),
        'createdAt' => (string) ($existing['createdAt'] ?? gmdate('Y-m-d\TH:i:s\Z')),
    ];
}

function clean_text($value, string $message): string
{
    $text = trim((string) $value);

    if ($text === '') {
        throw http_error(400, $message);
    }

    return $text;
}

function clean_email($value): string
{
    $email = strtolower(trim((string) $value));

    if ($email === '') {
        return '';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw http_error(400, sprintf('Invalid email: %s', $email));
    }

    return $email;
}

function meeting_email_message(array $meeting): string
{
    $when = utc_to_local_display((string) $meeting['startAt']);
    $config = app_config();
    $appUrl = trim((string) $config['app_url']);

    $lines = [
        sprintf('You have been invited to "%s".', $meeting['title']),
        '',
        sprintf('Organizer: %s', $meeting['hostName']),
        sprintf('Activity: %s', $meeting['activityType']),
        sprintf('When: %s', $when),
        sprintf('Where: %s', $meeting['platform']),
        '',
        $meeting['description'] !== '' ? sprintf('Notes: %s', $meeting['description']) : 'Notes: No extra notes were added.',
    ];

    if ($appUrl !== '') {
        $lines[] = '';
        $lines[] = sprintf('Open the shared scheduler: %s', $appUrl);
    }

    return implode("\r\n", $lines);
}

function utc_to_local_display(string $utc): string
{
    try {
        $date = new DateTime($utc, new DateTimeZone('UTC'));
    } catch (Throwable $exception) {
        return $utc;
    }

    $date->setTimezone(new DateTimeZone((string) app_config()['timezone']));

    return $date->format('l, F j, Y \a\t g:i A T');
}

function send_invite_emails(array $meeting, array $options = []): array
{
    $config = app_config();
    $mailConfig = $config['mail'] ?? [];
    $enabled = (bool) ($mailConfig['enabled'] ?? false);
    $from = trim((string) ($mailConfig['from'] ?? ''));
    $subjectPrefix = (string) ($options['subject_prefix'] ?? 'Invitation');
    $successLabel = (string) ($options['success_label'] ?? 'Invite email sent');

    if (!$enabled || $from === '') {
        return [
            'attempted' => false,
            'delivered' => false,
            'message' => 'Meeting saved. Email sending is disabled until config.php is set up.',
            'preview' => meeting_email_message($meeting),
        ];
    }

    $subject = sprintf('%s: %s', $subjectPrefix, $meeting['title']);
    $body = meeting_email_message($meeting);
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        sprintf('From: %s <%s>', $config['app_name'], $from),
        sprintf('Reply-To: %s', $from),
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    foreach ($meeting['invitees'] as $invitee) {
        $sent = mail($invitee, $subject, $body, implode("\r\n", $headers));
        if (!$sent) {
            throw http_error(500, sprintf('mail() failed while sending to %s', $invitee));
        }
    }

    return [
        'attempted' => true,
        'delivered' => true,
        'message' => sprintf('%s to %d recipient(s).', $successLabel, count($meeting['invitees'])),
    ];
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        throw http_error(400, 'Invalid JSON body.');
    }

    return $decoded;
}

function json_response($payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function http_error(int $status, string $message): RuntimeException
{
    return new RuntimeException($message, $status);
}
