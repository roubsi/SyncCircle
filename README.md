# SyncCircle

SyncCircle is a lightweight PHP scheduling website for online hangouts. Anyone who opens the site can add upcoming calls, game sessions, movie nights, anime marathons, or series watch parties, and the app can send invite emails to the people listed on each meeting.

## What it does

- Creates shared meetings from a single web form
- Lets anyone edit invitees, dates, platforms, and notes for existing meetings
- Lets anyone erase meetings from the shared schedule
- Stores upcoming plans in a local JSON file
- Shows all scheduled sessions on the same page
- Sends email invitations through PHP `mail()` when enabled
- Uses a retro black-and-green terminal interface

## Deploy it

1. Upload the project files to a PHP host.
2. Copy [config.example.php](/Users/gabriel/Documents/Codex/2026-04-25/make-a-website-that-allows-me/config.example.php) to `config.php`.
3. Set your site URL, timezone, and sender email inside `config.php`.
4. Turn `'enabled' => true` under the `mail` section when your host supports outgoing mail.
5. Make sure the `data` folder is writable by PHP so the app can save meetings.

The homepage is [index.php](/Users/gabriel/Documents/Codex/2026-04-25/make-a-website-that-allows-me/index.php), and the meeting API is [api/meetings.php](/Users/gabriel/Documents/Codex/2026-04-25/make-a-website-that-allows-me/api/meetings.php).

If mail is disabled, meetings are still saved and shown on the page, but invite emails stay off.

## Main files

- [index.php](/Users/gabriel/Documents/Codex/2026-04-25/make-a-website-that-allows-me/index.php): app page
- [app.js](/Users/gabriel/Documents/Codex/2026-04-25/make-a-website-that-allows-me/app.js): create, edit, delete, and refresh logic
- [styles.css](/Users/gabriel/Documents/Codex/2026-04-25/make-a-website-that-allows-me/styles.css): retro terminal styling
- [api/meetings.php](/Users/gabriel/Documents/Codex/2026-04-25/make-a-website-that-allows-me/api/meetings.php): PHP JSON API
- [lib/app.php](/Users/gabriel/Documents/Codex/2026-04-25/make-a-website-that-allows-me/lib/app.php): storage, validation, and email helpers

## Email notes

This version uses PHP `mail()`, which is the simplest option for common shared hosting. If your host does not allow outgoing mail, the scheduler still works but emails stay disabled until you connect a mail-capable setup.
