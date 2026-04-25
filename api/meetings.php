<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/app.php';

app_config();

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $meetingId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';

    if ($method === 'GET') {
        json_response(read_meetings());
    }

    if ($method === 'POST') {
        $payload = request_json();
        $meeting = normalize_meeting($payload);
        $meetings = read_meetings();
        $meetings[] = $meeting;
        write_meetings($meetings);

        try {
            $email = send_invite_emails($meeting);
        } catch (RuntimeException $exception) {
            $email = [
                'attempted' => true,
                'delivered' => false,
                'message' => 'Meeting saved, but email delivery failed: ' . $exception->getMessage(),
            ];
        }

        json_response([
            'meeting' => $meeting,
            'email' => $email,
        ], 201);
    }

    if ($method === 'PUT') {
        if ($meetingId === '') {
            throw http_error(400, 'Meeting id is required.');
        }

        $payload = request_json();
        $meetings = read_meetings();
        $meetingIndex = null;

        foreach ($meetings as $index => $meeting) {
            if (($meeting['id'] ?? '') === $meetingId) {
                $meetingIndex = $index;
                break;
            }
        }

        if ($meetingIndex === null) {
            throw http_error(404, 'Meeting not found.');
        }

        $updatedMeeting = normalize_meeting($payload, [
            'id' => $meetings[$meetingIndex]['id'],
            'createdAt' => $meetings[$meetingIndex]['createdAt'],
        ]);

        $meetings[$meetingIndex] = $updatedMeeting;
        write_meetings($meetings);

        try {
            $email = send_invite_emails($updatedMeeting, [
                'subject_prefix' => 'Updated invitation',
                'success_label' => 'Updated invite email sent',
            ]);
        } catch (RuntimeException $exception) {
            $email = [
                'attempted' => true,
                'delivered' => false,
                'message' => 'Meeting updated, but email delivery failed: ' . $exception->getMessage(),
            ];
        }

        json_response([
            'meeting' => $updatedMeeting,
            'email' => $email,
        ]);
    }

    if ($method === 'DELETE') {
        if ($meetingId === '') {
            throw http_error(400, 'Meeting id is required.');
        }

        $meetings = read_meetings();
        $filtered = array_values(array_filter($meetings, static function (array $meeting) use ($meetingId): bool {
            return ($meeting['id'] ?? '') !== $meetingId;
        }));

        if (count($filtered) === count($meetings)) {
            throw http_error(404, 'Meeting not found.');
        }

        write_meetings($filtered);
        json_response(['deleted' => true]);
    }

    throw http_error(405, 'Method not allowed.');
} catch (RuntimeException $exception) {
    $status = $exception->getCode();
    if (!is_int($status) || $status < 400 || $status > 599) {
        $status = 500;
    }

    json_response([
        'error' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Unexpected server error.',
    ], $status);
}
