<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SyncCircle</title>
    <link rel="stylesheet" href="styles.css" />
  </head>
  <body>
    <div class="page-shell">
      <header class="hero">
        <div class="hero-copy">
          <p class="eyebrow">SYNC_CIRCLE v2.0 // SHARED WATCH PARTY TERMINAL</p>
          <h1>Queue the next session before the chat goes offline.</h1>
          <p class="hero-text">
            Log movie nights, anime marathons, series sessions, multiplayer calls, and hangouts in a
            shared terminal board. Anyone in your group can add, edit, or erase plans and email the
            invited people from the same screen.
          </p>
          <div class="hero-pills">
            <span>SHARED_LOG</span>
            <span>MAIL_DISPATCH</span>
            <span>EDIT + DELETE</span>
          </div>
        </div>
        <div class="hero-card">
          <p>SYSTEM STATUS</p>
          <strong>ONLINE</strong>
          <span>Manage invites, dates, and details like an old green-screen planning console.</span>
        </div>
      </header>

      <main class="layout">
        <section class="panel form-panel">
          <div class="section-heading">
            <p class="eyebrow">EVENT_EDITOR</p>
            <h2 id="form-title">Create a new session</h2>
          </div>

          <form id="meeting-form" class="meeting-form">
            <input name="meetingId" type="hidden" />
            <label>
              Session title
              <input name="title" type="text" placeholder="MOVIE NIGHT // Spirited Away" required />
            </label>

            <div class="two-col">
              <label>
                Organizer
                <input name="hostName" type="text" placeholder="Gabriel" required />
              </label>

              <label>
                Activity
                <input name="activityType" type="text" placeholder="Watch anime together" required />
              </label>
            </div>

            <div class="two-col">
              <label>
                Date and time
                <input name="startAt" type="datetime-local" required />
              </label>

              <label>
                Platform or call link
                <input
                  name="platform"
                  type="text"
                  placeholder="Discord, Google Meet, Teleparty, Zoom..."
                  required
                />
              </label>
            </div>

            <label>
              Invitee emails
              <textarea
                name="invitees"
                rows="4"
                placeholder="friend1@email.com, friend2@email.com"
                required
              ></textarea>
            </label>

            <label>
              Notes
              <textarea
                name="description"
                rows="4"
                placeholder="Episode list, game mode, room code, stream link, snacks, etc."
              ></textarea>
            </label>

            <div class="button-row">
              <button type="submit" id="submit-button" class="primary-button">
                Save session and send invites
              </button>
              <button type="button" id="cancel-button" class="secondary-button hidden">
                Cancel edit mode
              </button>
            </div>
            <p id="form-status" class="status" aria-live="polite"></p>
          </form>
        </section>

        <section class="panel list-panel">
          <div class="section-heading">
            <p class="eyebrow">SCHEDULE_BUFFER</p>
            <h2>Stored sessions</h2>
          </div>

          <div id="meeting-list" class="meeting-list"></div>
        </section>
      </main>
    </div>

    <script type="module" src="app.js"></script>
  </body>
</html>
