const form = document.querySelector("#meeting-form");
const formTitleEl = document.querySelector("#form-title");
const submitButtonEl = document.querySelector("#submit-button");
const cancelButtonEl = document.querySelector("#cancel-button");
const statusEl = document.querySelector("#form-status");
const meetingListEl = document.querySelector("#meeting-list");
const API_URL = "api/meetings.php";

let meetingsCache = [];
let editingMeetingId = "";

form.addEventListener("submit", async (event) => {
  event.preventDefault();

  const formData = new FormData(form);
  const meetingId = String(formData.get("meetingId") || "").trim();
  const payload = {
    title: formData.get("title"),
    hostName: formData.get("hostName"),
    activityType: formData.get("activityType"),
    startAt: formData.get("startAt"),
    platform: formData.get("platform"),
    description: formData.get("description"),
    invitees: splitInvitees(formData.get("invitees"))
  };

  const isEditing = Boolean(meetingId);
  setStatus(
    isEditing ? "Updating the session and resending invitations..." : "Saving the session and sending invitations...",
    ""
  );

  try {
    const response = await fetch(isEditing ? `${API_URL}?id=${encodeURIComponent(meetingId)}` : API_URL, {
      method: isEditing ? "PUT" : "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.error || `Unable to ${isEditing ? "update" : "create"} the meeting.`);
    }

    resetForm();
    const tone = result.email.delivered || result.email.attempted === false ? "success" : "error";
    setStatus(result.email.message, tone);
    await loadMeetings();
  } catch (error) {
    setStatus(error.message, "error");
  }
});

cancelButtonEl.addEventListener("click", () => {
  resetForm();
  setStatus("Edit mode cancelled.", "");
});

meetingListEl.addEventListener("click", async (event) => {
  const actionButton = event.target.closest("button[data-action]");
  if (!actionButton) {
    return;
  }

  const meetingId = actionButton.dataset.id;
  const meeting = meetingsCache.find((item) => item.id === meetingId);

  if (!meeting) {
    setStatus("That session could not be found anymore.", "error");
    return;
  }

  if (actionButton.dataset.action === "edit") {
    populateForm(meeting);
    return;
  }

  if (actionButton.dataset.action === "delete") {
    const confirmed = window.confirm(`Delete "${meeting.title}" from the schedule?`);
    if (!confirmed) {
      return;
    }

    setStatus("Erasing the session from the shared schedule...", "");

    try {
      const response = await fetch(`${API_URL}?id=${encodeURIComponent(meetingId)}`, {
        method: "DELETE"
      });
      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.error || "Unable to delete the meeting.");
      }

      if (editingMeetingId === meetingId) {
        resetForm();
      }

      setStatus("Session deleted.", "success");
      await loadMeetings();
    } catch (error) {
      setStatus(error.message, "error");
    }
  }
});

loadMeetings();

async function loadMeetings() {
  meetingListEl.innerHTML = '<div class="empty-state">Loading session buffer...</div>';

  try {
    const response = await fetch(API_URL);
    const meetings = await response.json();

    if (!response.ok) {
      throw new Error(meetings.error || "Unable to load meetings.");
    }

    meetingsCache = meetings;
    renderMeetings(meetings);
  } catch (error) {
    meetingListEl.innerHTML = `<div class="empty-state">${escapeHtml(error.message)}</div>`;
  }
}

function renderMeetings(meetings) {
  if (!meetings.length) {
    meetingListEl.innerHTML =
      '<div class="empty-state">No sessions stored yet. Queue the first movie, anime, series, or game night.</div>';
    return;
  }

  meetingListEl.innerHTML = meetings
    .map((meeting) => {
      const date = new Date(meeting.startAt);

      return `
        <article class="meeting-card">
          <div class="meeting-card-header">
            <div>
              <h3>${escapeHtml(meeting.title)}</h3>
              <div class="tag-row">
                <span class="meeting-tag">${escapeHtml(meeting.activityType)}</span>
                <span class="meeting-tag">${escapeHtml(meeting.platform)}</span>
              </div>
            </div>
            <div class="card-actions">
              <button type="button" class="ghost-button" data-action="edit" data-id="${escapeHtml(meeting.id)}">
                Edit
              </button>
              <button type="button" class="danger-button" data-action="delete" data-id="${escapeHtml(meeting.id)}">
                Delete
              </button>
            </div>
          </div>

          <div class="meeting-meta">
            <div><strong>Organizer:</strong> ${escapeHtml(meeting.hostName)}</div>
            <div><strong>When:</strong> ${formatDate(date)}</div>
            <div><strong>Notes:</strong> ${escapeHtml(meeting.description || "No extra notes added.")}</div>
          </div>

          <div class="invitee-row">
            ${meeting.invitees
              .map((invitee) => `<span class="invitee-chip">${escapeHtml(invitee)}</span>`)
              .join("")}
          </div>
        </article>
      `;
    })
    .join("");
}

function populateForm(meeting) {
  editingMeetingId = meeting.id;
  form.elements.meetingId.value = meeting.id;
  form.elements.title.value = meeting.title;
  form.elements.hostName.value = meeting.hostName;
  form.elements.activityType.value = meeting.activityType;
  form.elements.startAt.value = toLocalInputValue(meeting.startAt);
  form.elements.platform.value = meeting.platform;
  form.elements.description.value = meeting.description || "";
  form.elements.invitees.value = meeting.invitees.join(", ");

  formTitleEl.textContent = "Edit scheduled session";
  submitButtonEl.textContent = "Update session and resend invites";
  cancelButtonEl.classList.remove("hidden");
  setStatus(`Editing "${meeting.title}". Save to update invitations and details.`, "");
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function resetForm() {
  editingMeetingId = "";
  form.reset();
  form.elements.meetingId.value = "";
  formTitleEl.textContent = "Create a new session";
  submitButtonEl.textContent = "Save session and send invites";
  cancelButtonEl.classList.add("hidden");
}

function splitInvitees(rawValue) {
  return String(rawValue || "")
    .split(/[,\n]/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function toLocalInputValue(dateString) {
  const date = new Date(dateString);
  const timezoneOffset = date.getTimezoneOffset() * 60_000;
  return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16);
}

function formatDate(date) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "full",
    timeStyle: "short"
  }).format(date);
}

function setStatus(message, type) {
  statusEl.textContent = message;
  statusEl.className = `status${type ? ` ${type}` : ""}`;
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}
