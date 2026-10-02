# Sankul - Community Maintenance Platform

Maintenance billing, digital receipts, governance and operations for managed
residential and commercial communities.

Built for the problem most societies actually have: money owed is tracked on
paper or in someone's head, receipts are hand-written, and decisions taken at
meetings go unrecorded. This replaces all three with a system that bills on a
schedule, issues a numbered receipt for every rupee received, and keeps the
books in double entry so the AGM statements are derived rather than assembled.

Not apartment-only. A society may be an apartment complex, a villa project,
row houses, a gated community, a township, a plotted development, a commercial
complex, an office park, student housing or co-living - each with its own
billing basis, and all on one installation.

## Documentation

| Document | What is in it |
|---|---|
| [docs/PROJECT-OVERVIEW.md](docs/PROJECT-OVERVIEW.md) | What the system is, its architecture, every panel and what it means, the domain rules that make it different, an honest list of what is not built, and a section written for pasting into an AI to compare it against the market |
| [docs/WORKFLOWS.md](docs/WORKFLOWS.md) | Who does what in what order: the platform operator creating a society, the administrator setting it up, loading the property, the first bill run, and the working rhythm of each role |
| [docs/TESTING.md](docs/TESTING.md) | How to run the suite, what each of its 218 tests guarantees, conventions for writing new ones, browser verification, and a manual checklist per role |

## Stack

**Requires PHP 8.3 or newer, MySQL 8 (or MariaDB 10.6+), Composer 2 and Node 20+.**

| | |
|---|---|
| Framework | Laravel 13 on PHP 8.3+ |
| Front end | Livewire 4, Alpine 3, Tailwind 4, Vite 8 |
| Access control | spatie/laravel-permission 8, teams keyed on `society_id` |
| Documents | dompdf for invoices and receipts, bacon-qr-code for verification |
| Payments | Razorpay, behind a driver interface |
| Database | MySQL 8 / MariaDB 10.6+ |

## Getting started

Create the two databases first - one for the app, one for the test suite:

```sql
CREATE DATABASE sankul      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE sankul_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then:

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
# set DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env

php artisan migrate
php artisan db:seed          # two fully populated demo societies

npm run build
php artisan serve
```

Then sign in at `/login` with any of these (password `password`):

| Role | Email | What they see |
|---|---|---|
| Platform operator | `super@sankul.test` | Every society |
| Society admin / treasurer | `treasurer@sankul.test` | Full society, including money |
| Secretary | `secretary@sankul.test` | Governance, helpdesk, residents |
| Manager | `manager@sankul.test` | Operations, no accounting controls |
| Security guard | `guard@sankul.test` | The gate console and nothing else |
| Resident (owner) | `owner@sankul.test` | Their own bills, tickets and bookings |
| Resident (tenant) | `tenant@sankul.test` | The same, as a tenant |

The seed builds two deliberately different societies: a 72-flat apartment
complex billed per square foot, and a 16-villa gated community billed a flat
amount per villa - with five months of real billing history behind both.

### A note on the dependency lock

`composer.json` pins `config.platform.php` to `8.3.0`, so Composer resolves
dependencies as though running the oldest PHP this project supports. Without
it, a lock file built on 8.4 pulls in Symfony 8.x, which hard-requires PHP
8.4.1 and then refuses to install for anyone on 8.3.

Leave the pin in place. It does not stop the app running on 8.4 or later - it
only keeps the lock file installable across every supported version. If you
later drop 8.3 support, raise both the pin and the `php` constraint together.

## Creating a society

### From the platform console

Sign in as a platform operator and open **Platform → All societies**. That
screen lists every community on the installation and creates new ones: the
community's own details, plus its first administrator. Creating a society
provisions it fully - chart of accounts, default charge heads, helpdesk
categories with SLA targets, an open financial year and its own copy of every
role - so it is usable immediately. **Open** jumps into a society; a society
still onboarding opens its setup wizard instead.

The demo seed ships one operator: `super@sankul.test` / `password`.

### From the command line

For the first society on a fresh installation, when there is no operator to
sign in as yet:

```bash
php artisan society:create \
    --name="Sunrise Enclave" \
    --type=gated_community \
    --city=Pune \
    --admin-name="Priya Kulkarni" \
    --admin-email=priya@sunrise.test \
    --payment-mode=both \
    --super-admin
```

Run it with no options and it prompts for each one. `--super-admin` also makes
that administrator a platform operator, which is how you bootstrap access to
the console. The command prints a generated password when you do not supply
one.

To promote an existing account later:

```bash
php artisan user:super-admin you@example.com
php artisan user:super-admin you@example.com --revoke
```

A platform operator is not a member of the societies they administer. They can
reach any of them, but their role is held outside all of them - which is why
the console lives on its own route rather than inside a society.

## How it is put together

### One database, scoped by society

Every business table carries `society_id`. A `SocietyContext` singleton holds
the society the current request is acting in; a global scope filters reads and
the `BelongsToSociety` trait stamps writes. Reporting that deliberately spans
societies goes through an explicit `withoutScope()` call rather than by
forgetting the filter.

Roles are society-scoped too, so the same person can be a treasurer in one
society and an ordinary resident in another without either leaking.

### Money

- **`ChargeCalculator`** resolves a rate by specificity - a unit-level override
  beats the plan's rate, which beats the head's default - and multiplies by
  whatever the head bills on: area, bedrooms, residents or vehicles.
- **`InvoiceGenerator`** turns a billing plan into invoices. A run is
  idempotent per unit and period, because bill runs get triggered by both the
  scheduler and impatient committee members.
- **`LateFeeCalculator`** accrues interest additively, one period at a time.
  Each posting carries an `accrual_key` unique per invoice, so re-running a
  month cannot charge a resident twice.
- **`PaymentRecorder`** settles money oldest-bill-first and keeps any surplus
  as unit credit rather than refusing the payment.
- **`ReceiptIssuer`** produces the numbered digital receipt - the replacement
  for the hand-written *rasid* - and is idempotent per payment.
- **`LedgerPoster`** writes the double-entry side of invoices, payments,
  expenses and interest, and refuses to save an unbalanced entry outright.

Document numbers come from a `number_sequences` table under a row lock, per
society and per financial year, so every series is gap-free. An auditor will
ask; `max(id) + 1` would skip numbers whenever a transaction rolled back.

### Offline or online, per society

A society collects offline until a payment gateway is configured **and**
verified against the provider. Only then does online collection switch on, so
a resident is never shown a checkout button that cannot work. Credentials are
encrypted per society, so each collects into its own bank account.

`GatewayManager` resolves a driver behind `PaymentGatewayDriver`; adding
Cashfree, PayU or Stripe means one class and one line, with nothing in the
billing code changing. The gateway is always re-queried for the real amount
rather than trusting what the browser posts back.

## Modules

**Money** - charge heads, billing plans, invoices, payments with an offline
approval queue, receipts, adjustments and write-offs, expenses and vendor
bills, double-entry accounting, budgets, and reports (outstanding dues, trial
balance, income and expenditure, balance sheet), each exportable as CSV.

**Property** - blocks and wings, units of every type, owners and tenants with
agreement tracking, parking allotment, vehicles, and a resident directory with
per-society privacy controls. Who used to live in a flat is behind its own
permission, held by the society administrator, chairman and secretary only.
Occupancy is recorded as moves rather than
edits: moving in opens a record, moving out closes one with its date and
reason, and a sale hands the unit over on a single day, so every unit keeps a
readable history and a past receipt still names whoever paid it. A unit's
occupancy status is derived from who lives there, never typed. A **site plan**
draws the society from above and each building from the side, coloured by
occupancy, dues or open complaints, with every flat a button - flat, or in
**3D**, where buildings are extruded by their floor count and a building's
floors can be pulled apart to see into the middle of a tower.

**Governance** - committees and office bearers, meetings with agenda, notice
periods, RSVP, proxy attendance, quorum tracking, minutes and resolutions;
action items; and polls that can be one vote per person, one per unit, or
weighted by unit area.

**Operations** - a helpdesk with per-category SLA clocks, assignment,
escalation and resident ratings; work orders raised from tickets, preventive
schedules or by hand; assets with AMC contracts and expiry reminders;
amenity booking with clash detection and per-unit caps; staff and attendance.

**Security** - a gate console built for one-handed phone use, visitor
pre-approval with a quotable code, walk-in logging with resident approval,
material and move-in/out gate passes verified by QR, domestic help records,
and SOS alerts.

**Communication** - notices with audience targeting and read receipts, a
document vault with per-role visibility, emergency contacts, and an audit log.
The **reminder schedule** and the **wording of every automated message** are
the committee's to edit, and every message sent is recorded against its
recipient.

## Automation

One cron entry drives everything:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

| Command | When | What it does |
|---|---|---|
| `billing:run` | 02:00 daily | Raises bills for plans that have come due |
| `billing:accrue-late-fees` | 03:00 daily | Posts interest on overdue bills |
| `billing:remind` | 10:00 weekdays | Reminds residents on the society's own schedule around the due date |
| `helpdesk:escalate` | hourly | Escalates tickets that have missed their SLA |
| `maintenance:raise-work-orders` | 06:00 daily | Turns preventive schedules into work orders |

Every one is idempotent: a missed run catches up, and a double run changes
nothing. `billing:run --dry` reports what would be billed without writing, and
`billing:remind --date=` replays a day's schedule without sending twice.

### Reminders and message wording

The reminder ladder is rows in the database, edited under **Settings →
Reminders & messages**, not a constant in the code. A step is a signed number
of days relative to the due date - `-3` is three days before, `7` is a week
after - and reminders go out only on the days a society names. A new society
starts with `-3, 0, 7, 21, 45`, which a committee can then add to, switch off
or delete.

Eight messages carry editable templates: maintenance reminder, payment
receipt, new bill, notice published, meeting notice, minutes circulated,
complaint update, and visitor at the gate. Each declares the placeholders it
supplies; the editor previews the finished text against sample data and warns
about a placeholder that is mistyped. A society with no override gets the
packaged wording, so the system communicates correctly before anyone opens the
editor, and reverting is a delete.

Substitution is a plain `{{ token }}` replacement, deliberately not Blade:
these are edited in a textarea by committee members, and rendering user-edited
Blade would run whatever they typed.

Everything leaves by one door, which records what was sent, to whom and when,
and a unique dedupe key stops a duplicate in the database rather than in each
caller. Email is delivered; SMS and WhatsApp are recorded as queued until a
provider is connected, rather than reported as sent.

## Interface

The system font stack puts Apple's own faces first, so on iOS and macOS the
interface renders in SF Pro and falls back to Inter elsewhere. Dark mode is a
selected palette rather than an inversion, applied by an inline script before
first paint so there is no flash of the wrong theme.

**Tables become cards on a phone.** From `md` up, a list is an ordinary
semantic `<table>`. Below that the header row is hidden and each row becomes a
stacked card with every cell captioned by its own label - one markup, two
presentations, which is what makes an eight-column financial table readable at
390px without a second template.

Accessibility is in the primitives rather than retrofitted: a skip link, one
consistent focus ring, `aria-current` on the active nav item, form fields that
wire their own labels, hints and errors together through `aria-describedby`,
status carried by a text label as well as a hue, polite live regions for
toasts, and `prefers-reduced-motion` and `prefers-contrast` honoured.

**The 3D plan is CSS transforms, not WebGL.** Every building and every flat
stays a real `<button>` that takes focus and reads out to a screen reader,
which a canvas cannot do without maintaining a parallel DOM; it costs no
dependency and runs on the kind of phone a guard actually carries. Roof
labels are billboarded so they stay upright as the site turns, cells are
sized so the tilt still leaves a 44px target, and `prefers-reduced-motion`
removes the transitions.

**Every screen explains itself.** A help control in the topbar opens a panel
for the screen you are on: what it is for, the steps that matter, and the
things people get wrong. A detail screen falls back to its list's guide, and
the guides are tested against the router so one keyed on a route that no
longer exists fails the build.

Dashboard charts use a two-hue categorical palette validated against the app's
own card surfaces - worst-pair colour-vision-deficiency ΔE 24.7 in light and
26.8 in dark, against a target of 8 - and ship with a legend, hover tooltips
and a table view, so nothing depends on colour alone.

### A note on the compiled assets

`public/build` is committed. This app is normally run straight from a clone
with `php artisan serve`, and a pull that brings new templates but not the CSS
they depend on does not look like a missing build step - it looks like a
broken interface. Committing the output means `git pull` is enough.

If you change anything under `resources/css` or `resources/js`, run
`npm run build` and commit the result with your change. If you add a deploy
pipeline that builds on the server, re-ignore the directory.

## Tests

```bash
php artisan test
```

The suite runs against the `sankul_test` database on MySQL - the same engine
as production, so anything engine-specific is caught here rather than in
production. Host and credentials come from `.env`; only the database name is
overridden in `phpunit.xml`.

218 tests covering the parts that would be expensive to get wrong: tenancy
isolation, per-square-foot and fixed billing arithmetic, idempotent bill runs,
simple versus compound interest and the guarantee against double-charging,
oldest-first payment allocation and overpayment credit, gap-free receipt
numbering across financial years, ledger balance and statement integrity,
role-scoped permissions, amenity booking rules, the scheduled commands, and
the platform console - including that a society's unit count is read per row
rather than per active society.

Also: that a reminder goes out only on a day the schedule names and never
twice in a day, that a template is never executed as code, that every packaged
template uses only placeholders it declares, that a move-out closes a record
rather than deleting it and never leaves a unit without a billing contact,
that the site plan's automatic layout provably never overlaps for 1 to 12
buildings, that every help guide points at a route that exists, and that a
past resident is never sent to the browser at all for someone without the
permission - asserted against the raw HTML, because a name hidden behind an
`@if` is still there to read.

Lazy loading is prevented throughout the run, so an N+1 fails rather than
quietly firing an extra query. One test says so explicitly, to pin the class of
bug where a screen eager loads `residents` and a helper reads
`activeResidents`.

## Production notes

Point `.env` at your production database, then:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Run `php artisan queue:work` under a supervisor so mail and notifications are
delivered off the request cycle, and point the scheduler at cron as above.

### A note on the previous version

This repository previously held a Laravel 7 application whose `.env` was
committed, containing a live database password, a mail password and the
application key. That file is removed and `.env` is now ignored, but **those
values remain in earlier commits and should be rotated.**
