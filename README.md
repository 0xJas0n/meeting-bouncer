# Meeting Bouncer

Calendar invites are guests at a club door. Meeting Bouncer checks each invite against a dress code (agenda, guest list, cost, collisions), gives a verdict, **Let in / Shorten / Go async / Bounce**, with a witty line and a serious reason, drafts the polite reply, and shows how many hours and how much money you saved.

Fun on the surface, serious underneath: the jokes live in the persona text, the logic is plain, tested PHP. Built for the [Symfony AI & MCP Hackathon](https://symfony.com/hackathon).

**Tags:** MCP server, structured output, agents (Symfony AI `0.15.0`, Mistral by default, `mcp/sdk`).

Two ways to use it:

1. **Web UI** (Twig): in-tray, review sheet with an Approved / Not approved stamp, savings receipt, pre-check page.
2. **MCP server** (STDIO and HTTP): say "Bouncer, check my week" in Claude Desktop or Claude Code.

## Setup

Steps 1 to 3 take about 5 minutes and give you the app with a demo week. Steps 4 and 5 are optional.

### 1. Install

Requirements: PHP 8.4+, Composer, SQLite (`pdo_sqlite`), [Symfony CLI](https://symfony.com/download) (or `php -S`).

```bash
git clone <this repo> meeting-bouncer && cd meeting-bouncer
composer install
bin/console doctrine:schema:create
```

### 2. Add an LLM key

Put your key into `.env.local` (gitignored, never commit it):

```bash
echo 'MISTRAL_API_KEY=...' > .env.local
```

Without a key the app still lists invites, costs and door check flags; only the verdict step needs the LLM. Another provider: see [LLM provider](#llm-provider).

### 3. Start with the demo week

```bash
bin/console app:import-fixtures
symfony serve        # or: php -S 127.0.0.1:8000 -t public
```

Open the URL the server prints (`symfony serve` uses https only after `symfony server:ca:install`, otherwise http). Pick who reviews today and press **Review N waiting**. On an empty in-tray, **Load demo week** does the same as `app:import-fixtures`. Demo dates are relative to today.

### 4. Optional: your own Google Calendar (about 10 minutes)

Imports the next 7 days of your primary calendar, **read-only** (`calendar.events.readonly`): the app can never create, change or delete events. Uses Google's *Personal mode*: only your own Google account can sign in, no test-user list, no 7-day token expiry.

**a. Create a project and enable the API**

1. Open the [Google Cloud Console](https://console.cloud.google.com/), project dropdown (top left), **New project**, create it and select it.
2. Menu ☰, **APIs & Services**, **Library**, search "Google Calendar API", open it, **Enable**.

**b. Create the OAuth client in Personal mode**

1. Open [Google Auth Platform, Personal mode](https://console.cloud.google.com/auth/overview/personal).
2. **Personal mode project**: select your project (if it is not listed, use **Create a new project** there and enable the Calendar API in that one). Personal mode is permanent for the project: it can never switch to Internal or External.
3. **Application type**: **Web application**. Not *Desktop app*: the type cannot be changed later. Picked the wrong one? Open [Clients](https://console.cloud.google.com/auth/clients), **Create client**, **Web application**, and delete the wrong client.
4. **Authorized redirect URIs**: the URL you open the app with, plus `/calendar/google/callback`, e.g. `http://127.0.0.1:8000/calendar/google/callback`. It must match exactly, scheme (`http`/`https`) and host included: `127.0.0.1` and `localhost` are different. Google allows plain `http` for `127.0.0.1` and `localhost`. Unsure? Add both the `http` and the `https` variant.
5. Tick the Terms of Service box, **Continue**.
6. The dialog shows **Client ID** and **Client secret**. Copy both now (or **Download JSON**): Google shows the full secret only once. Lost it? Open the client and use **Add Secret**.

**c. Connect and import**

1. Add the values to `.env.local`:
   ```
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   ```
2. Open `/calendar/google/connect` in the app, sign in with the account that created the project, allow read-only access.
3. The next 7 days are imported right after connecting, then again at most once an hour when the dashboard is opened. **Update now** on the dashboard (or `bin/console app:import-calendar google`) imports immediately.

The first real import removes the demo week; later imports only add new events and keep your verdicts. `bin/console app:import-calendar fixtures` loads the demo week again.

`redirect_uri_mismatch` from Google means the URI in step b.4 differs from the URL you opened the app with: add the exact one shown in the error.

Not allowed to use Personal mode (e.g. your Workspace admin blocks it)? Set up the [Google Auth Platform](https://console.cloud.google.com/auth/overview) with **Get started** instead: audience *Internal* (Workspace organizations) or *External* with publishing status *Testing* and your own account as test user. With *External* Google expires the login after 7 days: just connect again. Then create the Web application client under **Clients** as above.

What happens with your data:

- Fetched fields are limited to what the door check needs. All-day events, declined events, cancelled events, out-of-office and meeting rooms are skipped; Google "focus time" blocks become protected focus blocks.
- Attendee names, emails and phone numbers are replaced by pseudonyms **during import** (same anti-corruption layer as the fixtures) and never stored or sent to the LLM or MCP.
- The refresh token lives in `var/google_token.json` (gitignored, mode 0600). Treat it like a password. "Disconnect" deletes it; you can also revoke the app at myaccount.google.com/permissions.
- Re-importing skips invites that already exist: rescheduled or edited events are not updated yet.
- Before using a real calendar: meeting titles and descriptions can contain sensitive free text that regex redaction does not catch, and attendees are third parties. Check your employer's data protection rules (and your LLM provider's data processing terms) first; use your own calendar only.

### 5. Optional: MCP client

Claude Code in this repo picks up `.mcp.json` automatically (approve the server once). Elsewhere: `claude mcp add meeting-bouncer -- php /absolute/path/to/meeting-bouncer/bin/console mcp:server`

Claude Desktop (`claude_desktop_config.json`) or any STDIO client:

```json
{
  "mcpServers": {
    "meeting-bouncer": {
      "command": "php",
      "args": ["/absolute/path/to/meeting-bouncer/bin/console", "mcp:server"]
    }
  }
}
```

After code changes restart the MCP server (new session or `/mcp` in Claude Code). Then ask: "Bouncer, check my week". Details: [MCP server](#mcp-server).

### Reset

Back to a fresh demo week: `bin/console doctrine:schema:drop --force && bin/console doctrine:schema:create && bin/console app:import-fixtures`.

## LLM provider

The LLM is configured only in `config/packages/ai.yaml` (Symfony AI); the PHP code does not know the provider. Mistral and Anthropic are installed. To use Anthropic, put `ANTHROPIC_API_KEY=...` into `.env.local` and change the `bouncer` agent:

```yaml
agent:
    bouncer:
        platform: 'ai.platform.anthropic'
        model: 'claude-sonnet-5-5'
```

Other providers need their bridge first, e.g. `composer require symfony/ai-open-ai-platform` (also `ai-gemini-platform`, `ai-ollama-platform`, or `ai-generic-platform` for OpenAI-compatible endpoints), then a `platform:` entry with its key and the agent change above. The model must support structured output (the verdict is a JSON schema).

## MCP server

HTTP: `symfony serve`, then `<server URL>/_mcp` (local use only: the bundle's DNS-rebinding check accepts localhost hosts, but there is no authentication, so never expose it publicly). Inspect with `npx @modelcontextprotocol/inspector`.

| Tool | Purpose |
|---|---|
| `list_upcoming_invites(days=7)` | invites with current verdict |
| `check_meeting(inviteId, persona)` | verdict, joke, reason, reply draft |
| `check_week(persona)` | all verdicts + totals |
| `meeting_cost(inviteId)` | EUR with breakdown |
| `set_hourly_rate(hourlyRateEur)` | set the average hourly cost per attendee (1–1000 €) |
| `suggest_slot(inviteId, durationMin)` | up to 3 free slots |
| `precheck_draft(title, description, attendeeCount, durationMin)` | fixes before you send |
| `weekly_report()` | hours and EUR saved |
| `confirm_verdict(inviteId)` | accept the verdict, counts towards savings |
| `override_verdict(inviteId)` | reject the verdict (you attend anyway) |
| `rate_meeting(inviteId, worthIt)` | rate an attended meeting |

Verdicts come as `verdict` (stable code, e.g. `LET_IN`) and `verdictLabel` (display text, e.g. "Let in"). Joke and reason follow the persona's language, the reply draft the invite's language.

Resource `bouncer://rules` returns the current thresholds, template `bouncer://invite/{inviteId}` one invite with its verdict. Prompts `weekly-review` and `draft-check` appear as slash commands in clients such as Claude Code. All output is pseudonymized, write tools only change the local database, never a real calendar.

Without an MCP client: `bin/console app:check-week [persona]` and `bin/console app:precheck "<title>" <attendees> <minutes> ["<description>"]` print the same JSON (`app:precheck` exits 1 when the draft is not ready). Add `--pretty` to `app:check-week` for a coloured terminal table instead of JSON. The MCP output itself has no colours: the MCP client (Claude Code, Claude Desktop) decides how to show it.

## 2-minute demo script

1. In-tray: **Quick sync**, 11 guests, 60 min, no agenda, you are optional: 660,00 € meeting cost.
2. Pick any reviewer (the German UI offers Wiener Grant), open Quick sync, *Ask the bouncer*: Bounce is ticked (optional in a large meeting without agenda), the joke lands on a sticky note.
3. **Approve**: the stamp slams, the polite reply is ready to copy (an invite that gets Go async also gets an async template), the receipt shows person-hours and € saved.
4. Claude Desktop: "Bouncer, check my week" returns all verdicts and totals via MCP.
5. Close on **Pre-check**: paste a draft invite and fix it before it is sent. Prevention beats rejection.

## Architecture

DDD with ports and adapters.

```
src/
  Shared/      kernel: AggregateRoot, Clock, Money, command/query/event bus ports + Messenger adapters
  Screening/   core domain
    Domain/          Invite aggregate, value objects, DoorRule classes, Pseudonymizer, ports (no framework code)
    Application/     commands + handlers, queries + read models, import ACL (InviteFactory)
    Infrastructure/  Doctrine (XML mapping), JSON fixture calendar, Symfony AI verdict adapter
    UI/              Http (Twig), Console, Mcp
  Reporting/   supporting domain: savings ledger fed by Screening's domain events
```

```
CalendarSource --raw--> InviteFactory (Pseudonymizer) --> Invite
Invite --> DoorChecker (8 rules) --> DoorCheck --> VerdictGenerator (Symfony AI) --> Verdict
Confirm --> VerdictConfirmed event --> SavingsProjector --> weekly report (UI banner, MCP)
```

- **Rules first, LLM second.** Cost, attendee count, overlaps, recurrence age are pure PHP. The LLM classifies the purpose, decides borderline cases and writes the texts, through typed structured output.
- **Extend:** a new rule is one class implementing `DoorRule`; a new calendar source implements `CalendarSource`; another LLM provider is a config change (see [LLM provider](#llm-provider)).
- **Tune live:** thresholds in `config/packages/bouncer.yaml`. The average hourly cost per attendee (default 60 €) is set on the dashboard (side panel) or with the MCP tool `set_hourly_rate`; it is stored in `var/hourly_rate_<env>` and applies at once to all open costs. Savings already approved keep the cost they were approved with.
- **Async review:** the dashboard's review button queues one `QueueEvaluation` per invite on the `async` transport. Run `bin/console messenger:setup-transports` once, then one or more `bin/console messenger:consume async --no-debug --memory-limit=100M --time-limit=3600 -vv` workers (in dev, without `--no-debug` the Doctrine query collector fills the 128 MB PHP limit; restart the worker when a limit ends it); the page auto-refreshes while invites are pending. Single-invite Evaluate and the MCP `check_week` stay sync.
- **Scale path (not built):** SQLite to Postgres via `DATABASE_URL`; multi-user by adding an owner id to the aggregate and repositories.
- **Layers are enforced:** `composer arch` (Deptrac) fails when the domain depends on anything outside itself.

## Privacy (GDPR)

- Raw attendee names, emails and phone numbers exist only inside the import adapter. `Pseudonymizer` replaces them (Person A, B, ...) and strips emails, phones and personal URLs **before** an invite exists. The LLM, the database, MCP output and logs never see them; tests assert it.
- Human in the loop: nothing is sent or changed in a real calendar. Every action needs a click.
- No rankings of people. Statistics are per user or team only (works council: AT Paragraph 96 ArbVG, DE Paragraph 87 (1) no. 6 BetrVG).
- Application logs contain invite IDs and exception types only. In dev, Doctrine's debug log also shows SQL parameters (already pseudonymized titles and descriptions); keep `var/log` out of bug reports. Fixtures are invented.
- Redaction is regex based and best effort; real data needs a legal and security review first.

## Development

```bash
composer test    # PHPUnit (mock platform: no network, no API key)
composer stan    # PHPStan level 8
composer arch    # Deptrac layer rules
composer qa      # all three
```
