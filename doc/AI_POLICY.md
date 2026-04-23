# AI Policy Acceptance — How & When It Is Shown

**Package:** `block_ai_assistant`  
**Last updated:** April 2026  
**Moodle version:** 5.1+

---

## Overview

The AI policy modal is a one-time acceptance dialog that must be completed before a user can interact with any AI tool in a course. Once accepted, it is **never shown again in any course** for that user. It is stored globally per user, not per course.

---

## Storage

Acceptance is recorded in the **Moodle core table** `mdl_ai_policy_register`:

| Column | Description |
|---|---|
| `id` | Auto-increment primary key |
| `userid` | Moodle user ID (unique — one row per user) |
| `contextid` | Context ID of the course where acceptance occurred |
| `timeaccepted` | Unix timestamp of acceptance |

This is a **native Moodle 5.1 core table**, shared between `block_ai_assistant` and Moodle's own AI subsystem (`core_ai`).

---

## Caching

Moodle 5.1 maintains a dedicated application cache for this:

```
Cache store : core/ai_policy
Mode        : MODE_APPLICATION (shared, persistent)
Key         : userid (integer)
Value       : bool — true = accepted, false/missing = not accepted
Datasource  : core_ai\cache\policy
```

The cache has a **datasource** (`core_ai\cache\policy`) which means:
- On a **cache hit** → returns the cached boolean instantly (zero DB queries)
- On a **cache miss** → automatically queries `mdl_ai_policy_register` and re-caches the result
- `staticacceleration = true` → within a single PHP request, the value is held in memory — multiple calls per page incur only one cache lookup

---

## When the Policy Popup Is Shown

The popup fires **only when both conditions are true simultaneously:**

1. **At least one AI tool is active on the course** (`$ai_active === true`)
2. **The user has not yet accepted the policy** (`get_policy_status() === false`)

### What makes `$ai_active = true`

`$ai_active` is computed in `block_ai_assistant::get_content()`:

```php
$moodle_ai_raw     = $DB->get_field('course', 'enableaitools', ['id' => $courseid]);
$moodle_ai_enabled = ($moodle_ai_raw !== false && $moodle_ai_raw !== null && (int)$moodle_ai_raw === 1);
$ai_active         = (!empty($course_record->published) || $moodle_ai_enabled);
```

| Condition | Source | Meaning |
|---|---|---|
| `course.enableaitools = 1` | Course Settings → Enable AI tools | Moodle core AI tools (writing assistant, etc.) are active |
| `block_aia_settings.published = 1` | Block → Enable AI Assistant button | The CRIA chat bot is published and live for students |

**`NULL`, `false`, `0`, `"0"`** for `enableaitools` are all treated as disabled.  
The popup is **never shown** if both are off, regardless of whether the user has accepted.

### Decision matrix

| `enableaitools` | `published` | Policy accepted | Popup shown? |
|---|---|---|---|
| 0 / NULL | 0 | any | ❌ No |
| 1 | 0 | No | ✅ Yes |
| 0 / NULL | 1 | No | ✅ Yes |
| 1 | 1 | No | ✅ Yes |
| 1 or 1 | any | **Yes** | ❌ Never again |

---

## Impersonation ("Login as") Guard

When a site admin uses Moodle's **"Login as"** feature to impersonate another user, `$USER` becomes the target user. Without a guard, the admin clicking **Accept** on the AI policy modal would permanently record acceptance against the **student's** account — even though the student never consented themselves.

### The fix

`block_ai_assistant.php` checks `$SESSION->realuser` before injecting the policy AMD call:

```php
$is_impersonating = !empty($SESSION->realuser);
if ($ai_active && !ai_policy::get_policy_status() && !$is_impersonating) {
    $PAGE->requires->js_call_amd('block_ai_assistant/ai_policy', 'init');
}
```

When `$SESSION->realuser` is set, Moodle is in "Login as" mode:
- The **real admin's** user ID is stored in `$SESSION->realuser`
- `$USER` is the **impersonated student**

### Behaviour during impersonation

| Scenario | AI policy modal shown? |
|----------|----------------------|
| Normal authenticated user | ✅ Yes (if AI active + not accepted) |
| Admin impersonating a student | ❌ No — modal suppressed entirely |
| Admin on their own session | ✅ Yes (if AI active + not accepted) |

### Why this matters

- The AI policy is a **legal/consent record** — it must only be accepted by the user themselves
- An admin impersonating a student for support/testing purposes should not inadvertently accept policies on the student's behalf
- The student's policy status is left unchanged during impersonation



```
block_ai_assistant::get_content()
  │
  ├─ compute $ai_active (published OR enableaitools)
  ├─ call ai_policy::get_policy_status()  →  core_ai\manager::get_user_policy_status($userid)
  │     └─ cache hit  →  returns bool directly
  │     └─ cache miss →  datasource queries mdl_ai_policy_register → re-caches → returns bool
  │
  ├─ if ($ai_active && !get_policy_status())
  │     └─ $PAGE->requires->js_call_amd('block_ai_assistant/ai_policy', 'init')
  │
  └─ $params['ai_active'] passed to mustache template
       └─ {{#ai_active}} renders hidden <input id="ai-policy-status"> {{/ai_active}}
```

### JavaScript side (`amd/src/ai_policy.js`)

1. Looks for `document.getElementById('ai-policy-status')` — if absent, returns immediately (no popup)
2. If element exists and `value === '0'` → loads policy strings via AJAX → shows `ModalFactory.types.SAVE_CANCEL`
3. On **Accept** → calls `block_ai_assistant_ai_policy` web service → inserts row into `mdl_ai_policy_register` + sets cache → reloads page
4. On **Decline** → modal is hidden, user remains on page but will be prompted again on next visit to any course with AI active

---

## Policy Status Check — `ai_policy::get_policy_status()`

Located at `classes/ai_policy.php`:

```php
public static function get_policy_status(): bool
{
    global $DB, $USER;
    try {
        return \core_ai\manager::get_user_policy_status((int) $USER->id);
    } catch (\Throwable $e) {
        // Fallback for environments where core_ai is unavailable.
        return $DB->record_exists('ai_policy_register', ['userid' => $USER->id]);
    }
}
```

- Uses the Moodle 5.1 core cache as the **primary** source of truth
- Falls back to a direct DB query if `core_ai\manager` is unavailable
- Called **twice** per page render in `get_content()` — once for the JS gate, once for `$params['ai_policy_status']` — both hits are served from the in-memory static acceleration cache (single DB round-trip maximum per request)

---

## Consent Withdrawal — `tool_consentwithdraw`

Users can withdraw their AI policy acceptance at any time via the **user menu → Withdraw AI Consent** link. Admins can revoke any user's acceptance via the admin tool.

### Self-revoke (`classes/external/revoke_self.php`)

1. Verifies the `ai_policy_register` record belongs to the current user
2. Deletes the record from `mdl_ai_policy_register`
3. **Deletes the `core/ai_policy` cache key** for the user — ensures the popup re-appears on the very next page load, not after cache expiry
4. Returns success — the consent modal updates its UI in place

### Admin revoke (`classes/external/revoke_user.php`)

1. Requires `tool/consentwithdraw:manage` capability
2. Deletes **all** `ai_policy_register` records for the target user
3. **Deletes the `core/ai_policy` cache key** for the target user
4. On the user's next page load to any course with AI active — popup is shown again

### Why cache invalidation matters

Without deleting the cache key:
- DB row is gone ✅
- Cache still holds `true` ❌
- `get_policy_status()` returns `true` from cache → popup never shown until cache naturally expires

With cache invalidation (current implementation):
- DB row deleted ✅
- Cache key deleted ✅
- Next `get_policy_status()` → cache miss → datasource auto-queries DB → returns `false` → re-caches `false`
- Popup shown on next page load ✅

---

## Full Lifecycle

```
First visit to course with AI active
  └─ get_policy_status() = false  →  popup shown
       ├─ User accepts  →  DB row written + cache set to true
       │     └─ All future visits: get_policy_status() = true  →  no popup (any course)
       └─ User declines →  modal hidden, no DB write
             └─ Next visit with AI active: popup shown again

User withdraws via tool_consentwithdraw
  └─ DB row deleted + cache key deleted
       └─ Next visit with AI active: get_policy_status() = false  →  popup shown again

Admin revokes via tool_consentwithdraw admin tool
  └─ DB row deleted + cache key deleted
       └─ User's next visit with AI active: popup shown again

Course AI tools turned off (enableaitools=0 AND published=0)
  └─ $ai_active = false  →  popup never shown regardless of policy status
```

---

## Debug Logging

A `debugging()` call in `get_content()` logs key values when Moodle is in **DEVELOPER** debug mode (`Site Admin → Development → Debug messages → DEVELOPER`). Silent in production.

```
[block_ai_assistant] courseid=26 published='1' moodle_ai_raw='0'
  moodle_ai_enabled=false ai_active=true policy_accepted=false user=jsmith
```

This appears in the Apache error log (`/var/log/apache2/error.log`) and is useful for diagnosing unexpected popup behaviour.

---

## Related Files

| File | Role |
|---|---|
| `block_ai_assistant.php` | Computes `$ai_active`, gates AMD JS, passes `ai_active` + `ai_policy_status` to templates |
| `classes/ai_policy.php` | `get_policy_status()` — single source of truth for acceptance check |
| `classes/external/ai_policy.php` | Web service that writes acceptance to DB + sets cache on Accept |
| `amd/src/ai_policy.js` | Shows the policy modal, calls web service on Accept |
| `templates/default.mustache` | Teacher view — renders `ai-policy-status` input only when `{{#ai_active}}` |
| `templates/student.mustache` | Student view — same `{{#ai_active}}` gate |
| `admin/tool/consentwithdraw/classes/external/revoke_self.php` | Self-service withdrawal — deletes DB row + invalidates cache |
| `admin/tool/consentwithdraw/classes/external/revoke_user.php` | Admin withdrawal — deletes DB row + invalidates cache |
| `ai/classes/cache/policy.php` | Moodle core cache datasource — auto-loads from DB on cache miss |
| `ai/classes/manager.php` | `get_user_policy_status()` + `user_policy_accepted()` — core cache API |
