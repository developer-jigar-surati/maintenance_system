# Sankul: project overview and feature reference

A complete description of what this system is, what every panel in it does and
why, and where its boundaries are. Written to be read by a new developer, a
committee member evaluating it, or an AI asked to compare it against the market
or propose what to build next. The last section is written for that last case
specifically.

Companion documents: [WORKFLOWS.md](WORKFLOWS.md) for who does what in what
order, [TESTING.md](TESTING.md) for how it is verified.

---

## 1. What this is

An internal management system for a managed residential or commercial
community: an apartment complex, a villa project, a gated township, a
commercial park. It is used by the committee that runs the community and the
people who live in it. It is not a public website, not a marketplace, and not a
property portal.

It is a single Laravel application with one database, serving many communities
at once, each walled off from the others.

### The problem it was built for

Three things go wrong in almost every society, and they are the reason this
exists:

1. **Money owed is tracked on paper or in somebody's head.** Nobody can say on
   demand what a flat owes, since when, or what has been chased. Collection
   depends on a treasurer's memory and goodwill.
2. **Receipts are hand-written.** The paper *rasid* is the only proof a
   resident has. It is lost, it is duplicated, numbers skip, and at audit time
   the book does not reconcile with the bank.
3. **Decisions taken in meetings go unrecorded.** The notice period cannot be
   proved, attendance is remembered rather than registered, and a resolution
   passed last year is argued about this year.

Everything in the system traces back to one of those three. Billing is
automatic and scheduled, every rupee received produces a numbered receipt with
a gap-free series, and the governance module keeps the paper trail a bye-law
actually requires.

### What makes it different from a generic billing tool

The design decisions below are the substance of the product. Any comparison
against a competitor should be made on these, not on the feature list.

| Decision | Why it matters |
|---|---|
| Maintenance resolves from the most specific rule that names a flat | Real societies charge A wing 12,000, B wing 11,000 and the GHI block 8,000, and inside each of those a 2BHK and a 3BHK differ again. Most tools force one basis for the whole society. |
| A flat can be settled individually, with a reason and the name of whoever agreed it | AGM concessions and ground floor exemptions are real and used to live in the treasurer's memory. |
| The prepayment discount is typed as money and stored as a percentage | A committee decides "1,20,000 for the year instead of 1,44,000". A percentage is the only form that still means the same thing after the rates change or where flats pay different amounts. |
| Document numbers come from a locked sequence table, per society per financial year | `max(id) + 1` skips a number whenever a transaction rolls back. An auditor checks for gaps. |
| Every scheduled command is idempotent | A missed cron catches up; a double run changes nothing. Bills cannot be raised twice for a period and interest cannot be charged twice for a month. |
| Occupancy is derived from who lives there, never typed | A status field drifts. A move in opens a record, a move out closes one, and the unit's status falls out of that. |
| Collection is offline until a gateway is configured **and** verified | A resident is never shown a pay button that cannot work. |
| Reminder timing and message wording are database rows the committee edits | Not constants in the code. A society that wants three reminders instead of five changes it itself. |
| Who used to live in a flat, and who voted which way, sit behind their own permission | Seeing who lives in 402 today is a directory. Seeing that the previous tenant was asked to leave is the society's record, not a neighbour's business. |
| The 3D site plan is CSS transforms, not WebGL | Every building and every flat stays a real focusable `<button>` that a screen reader can read. A canvas cannot do that without a parallel DOM. |

### Community types supported

One installation serves any mix of: apartment complex, villa project, row
houses, bungalows, gated community, township, plotted development, builder
floors, commercial complex, office park, industrial estate, mixed use,
co-operative housing society, student housing, co-living, other.

Unit types: flat, villa, row house, bungalow, plot, studio, penthouse, duplex,
shop, office, showroom, warehouse, parking, other.

The community type sets the default unit type and the wording, not a separate
code path.

---

## 2. Architecture at a glance

| Layer | Choice |
|---|---|
| Framework | Laravel 13, PHP 8.3+ |
| Interface | Livewire 4 (class components), Alpine 3, Tailwind 4, Vite 8 |
| Access control | spatie/laravel-permission 8, teams keyed on `society_id` |
| Database | MySQL 8 or MariaDB 10.6+, single database |
| Documents | dompdf for invoices and receipts, bacon-qr-code for verification |
| Payments | Razorpay behind a driver interface |

### Tenancy

Every business table carries `society_id`. A `SocietyContext` singleton holds
the society the current request is acting in. A global scope filters reads and
the `BelongsToSociety` trait stamps writes, so forgetting the filter is not
possible by accident. Reporting that deliberately spans societies calls
`withoutGlobalScopes()` explicitly.

Roles are society-scoped as well, so the same person can be a treasurer in one
society and an ordinary resident in another without either leaking. The
platform operator role (`super_admin`) is held outside every society, which is
why the platform console lives on its own route.

### The money services

| Service | Responsibility |
|---|---|
| `ChargeCalculator` | What one charge head costs one unit, and where that figure came from |
| `RateWriter` | Writes a committee's answer about a charge into rate rows, clearing the previous shape first |
| `AdvanceOffer` | What a home saves by paying the year up front, and the reference year to quote against |
| `InvoiceGenerator` | Turns a billing plan into invoices, idempotent per unit and period |
| `LateFeeCalculator` | Accrues interest one period at a time under an `accrual_key` unique per invoice |
| `PaymentRecorder` | Settles money oldest bill first, keeps surplus as unit credit |
| `ReceiptIssuer` | The numbered digital receipt, idempotent per payment |
| `LedgerPoster` | The double entry behind invoices, payments, expenses and interest; refuses to save an unbalanced entry |
| `NumberGenerator` | Gap-free document series under a row lock |
| `FinancialReports` | Statements derived from the journal rather than re-totalled from invoices |

### How a maintenance amount is resolved

Most specific wins. This is the single most important rule in the system:

```
this flat (unit_charge_overrides)
  ->  its building and size together   (charge_rates, scope block_configuration)
  ->  its size of home                 (charge_rates, scope configuration)
  ->  its building                     (charge_rates, scope block)
  ->  the billing plan's rate for the head
  ->  the head's own default rate
```

The resolved rate is then multiplied by whatever the head bills on: a fixed
amount per flat, the flat's area, its bedrooms, its residents or its vehicles.
A unit can also be marked exempt from a head entirely, which removes the line
rather than billing zero.

`ChargeCalculator::explain()` returns the same answer plus the name of
whichever rule decided it, which is what the unit page displays.

### Data model, by group

| Group | Tables |
|---|---|
| Society | societies, society_users, financial_years, number_sequences, site_features |
| Property | blocks, units, unit_residents, parking_slots, vehicles |
| Billing | charge_heads, charge_rates, unit_charge_overrides, billing_plans, billing_plan_charge_head, advance_discounts, invoices, invoice_lines, late_fee_rules |
| Payments | payments, payment_allocations, receipts, adjustments, payment_gateways, bank_accounts |
| Accounting | ledger_accounts, journal_entries, journal_lines, expenses, expense_payments, vendors, budgets |
| Governance | committees, committee_members, meetings, meeting_agenda_items, meeting_attendees, meeting_resolutions, action_items, polls, poll_options, poll_votes |
| Helpdesk | complaints, complaint_categories, complaint_comments, complaint_status_logs |
| Facilities | assets, amc_contracts, maintenance_schedules, work_orders, amenities, amenity_bookings, amenity_blackouts |
| People | staff, staff_attendance, daily_help, daily_help_attendance |
| Gate | gates, visitors, visitor_logs, gate_passes, sos_alerts |
| Communication | notices, notice_reads, documents, emergency_contacts, audit_logs, message_templates, message_dispatches, reminder_rules |

---

## 3. Roles

Thirteen roles. Each society gets its own copy of every role with a default
permission set attached, editable afterwards under Roles and access.

| Role | Who it is | What they get by default |
|---|---|---|
| `super_admin` | Platform operator, not a member of any society | The platform console and the ability to open any society |
| `society_admin` | The person who runs the installation for this society | Everything |
| `president` | Chairman | Everything |
| `secretary` | Secretary | Governance, residents, helpdesk, notices, documents, audit, occupancy and vote history. Reads money, does not manage it |
| `treasurer` | Treasurer | All of money: billing, payments, expenses, accounting, budgets, reports, vendors |
| `accountant` | Hired accountant | The same set as treasurer |
| `manager` | Facility or estate manager | Operations, property, staff, vendors, visitors, notices. Records payments but does not approve them |
| `committee_member` | Ordinary committee member | Read across money, property, governance and operations. Manages nothing |
| `owner` | Flat owner | Their own bills, their own tickets, bookings, notices, polls, directory |
| `tenant` | Tenant | The same as an owner |
| `security_guard` | Guard at the gate | The gate console, visitors, directory, notices, staff list, and nothing else |
| `staff` | Society employee | Work orders, assets, complaints, notices |
| `vendor` | External contractor | Work orders and complaints assigned to them |

Two separations are deliberate:

- **Recording money and approving it are different permissions.** A manager can
  record a payment; only `payment.approve` settles it when the society requires
  approval. An expense is recorded by one person and approved by another.
- **History is its own permission** (`history.view`). Past residents, why they
  left, and who voted which way are held by the society administrator, chairman
  and secretary only. A resident who needs it asks the secretary, who can
  answer from the record. This is enforced in the query, not in the template: a
  past resident is never sent to the browser for somebody without it.

---

## 4. Panel by panel

The sidebar is built from one declaration, so a role that lacks a permission
does not see the item at all rather than seeing a link that leads to a 403.
Sections with nothing in them disappear.

### Platform

#### All societies  `/platform/societies`  (platform operator only)

Every community on the installation, with its unit count, its status and its
administrators.

- Creating a society provisions it in full: its chart of accounts, eight
  default charge heads, a late fee rule, helpdesk categories with SLA targets,
  a reminder schedule, an open financial year, default site features and its
  own copy of every role. It is usable immediately.
- Its first administrator is created with it and finishes the setup from
  inside.
- **Open** switches the operator into that society. A society still onboarding
  opens its setup wizard instead.

*Why it is separate:* a platform operator is not a member of the societies they
administer, so this cannot live inside a society's own navigation.

### Overview

#### Dashboard  `/dashboard`  (everyone except a gate-only guard)

Two completely different screens behind one route, chosen by whether the signed
in person is management or a resident.

**Management sees:** billed against collected with a six month trend chart,
cash and bank balance, the top five defaulters (each a link into that unit),
helpdesk statistics with anything urgent, recent complaints, upcoming meetings,
amenity bookings waiting on a decision, and how many visitors are inside right
now.

**A resident sees:** their own units, what they owe, how many bills are
overdue, their open tickets, the latest notices, upcoming meetings, open polls,
their bookings, and visitors expected at the gate for them.

*The number that matters:* billed against collected over time. A gap that grows
month on month is the one figure a committee has to act on.

### Money

#### Invoices  `/invoices`  (`billing.view`)

Every maintenance bill raised against a unit, with its period, due date, total
and what is still outstanding. Each opens to its lines, its payments, and a PDF.

- Bills are normally raised by a billing plan on a schedule, not typed.
- A draft can still be changed. Once issued it is a document a resident has
  seen, so a correction is a balancing entry rather than an edit. (The
  adjustment model and its ledger postings exist; there is no screen for them
  yet, which is listed under section 7.)
- Outstanding is what is left after every payment allocated to that bill.
- Arrears are carried onto the bill for context but not re-charged, because the
  earlier invoices remain open in their own right.

#### Payments  `/payments`  (`payment.view`, recording needs `payment.record`)

Money received, however it arrived: cash, cheque, UPI, bank transfer, card or
the gateway.

- Record what was actually received; allocation against the oldest unpaid bill
  is automatic.
- More than was owed is held as credit against the unit rather than refused.
- If the society requires approval for offline payments, a recorded payment
  only settles a bill once approved.

#### Receipts  `/receipts`  (`payment.view`)

The digital replacement for the hand-written *rasid*. One receipt per completed
payment, numbered in an unbroken series per financial year, downloadable as a
PDF carrying a QR code that anyone can scan at `/verify/receipt/{uuid}` without
signing in.

A receipt is never renumbered or reused. Cancelling a payment leaves the
receipt visible and marked rather than removing it.

#### Charge heads  `/charge-heads`  (`billing.manage`)

What the society charges for. Eight are seeded: Maintenance, Sinking Fund,
Water, Parking, Corpus, Festival Fund, Late Payment Interest and Amenity
Booking.

Each head has a **basis** that decides how it is worked out: a fixed amount per
flat, a rate per unit of area, per bedroom, per member, per vehicle, or manual.

**Set rates** asks the committee one question, in the words a meeting uses:

| Answer | What gets stored |
|---|---|
| The same for every home | One amount on the head |
| Different for each building | A rate row per block |
| Different by size of home | A rate row per configuration (2BHK, 3BHK) |
| Different by building and size | A grid: a rate row per block and configuration |
| By area | A rate per square foot on the head |

A head split by building or size shows how many rates it has rather than a
single number, because there is no single number.

#### Billing plans  `/billing-plans`  (`billing.manage`)

What gets billed, to whom and how often. A plan carries a set of charge heads,
a cycle (monthly, bi-monthly, quarterly, half-yearly, yearly), how many days
residents get to pay, whether it generates automatically and whether it issues
automatically.

**Run now** raises the bills by hand, which committees want so they can see
them before the first of the month. A plan will not bill the same unit for the
same period twice, so a re-run is safe.

**The prepayment offer** lives here. You type what a year costs when paid in
one go; each building gets its own box underneath, prefilled with what that
building normally pays for the year. Leave a building blank and it takes the
society's offer; fill it in and that wing gets its own deal; set it to zero and
it deliberately gets nothing rather than inheriting. It is typed as money and
stored as a percentage.

#### Expenses  `/expenses`  (`expense.view`, approving needs `expense.approve`)

What the society spends, against which head, with the vendor and the bill
attached. Recording and approving are separate steps, so one person entering an
expense cannot also clear it. Approved expenses post to the ledger and appear
in income and expenditure.

#### Vendors  `/vendors`  (`vendor.view`)

The plumbers, lift companies and security agencies the society pays, with GSTIN
and PAN. A vendor here can be picked on a work order, an expense or an AMC, so
spend against them adds up in one place.

#### Reports  `/reports`  (`report.view`)

Four statements, every one exportable as CSV: **defaulters**, **trial
balance**, **income and expenditure**, **balance sheet**. All are derived from
the same double-entry journal rather than re-totalled from invoices, so they
agree with each other by construction. Figures are for the financial year
currently open.

### Property

#### Units  `/units`  (`unit.view`)

Every flat, villa, shop or plot, what each owes, and its occupancy. Opening one
shows:

- Its invoices, its payments, what it owes and any credit held.
- **Who lives here**, as a history of moves rather than an editable field.
  Moving someone in opens a record; recording a move-out closes one with its
  date and reason. A past resident is kept, never deleted, so an old receipt
  still names whoever actually paid it. The unit's occupancy status (owner
  occupied, rented, vacant) is derived from this.
- **What this home pays**: every charge head that reaches this flat, the
  amount, and where it came from, in plain words ("every home in the society",
  "all of A wing", "every 3BHK", "A wing 3BHK", "set for this home"). An admin
  can set a different amount for this one flat or leave it out of a charge
  entirely, with a reason and optional dates, and the reason and the name of
  whoever agreed it stay on the card. "Use the shared amount" puts it back.
  The box asks for the right thing per head: a per-square-foot head asks for
  the rate and shows what the bill comes to.
- Parking slots and vehicles.

Note that `unit.view` is a committee permission. Residents do not reach this
screen; they see their own position on their dashboard and under Invoices.

#### Site plan  `/site-plan`  (`unit.view`)

The society drawn out: buildings on the site from above, flats inside a
building from the side with floors stacked top first. Every flat is a button.

- The colour control changes what the plan shows: occupancy, dues, or open
  complaints.
- **3D** extrudes buildings by their floor count, so a six-storey block reads
  as taller than a row of villas. It turns, tilts, zooms, and a building's
  floors can be pulled apart to see into the middle of a tower.
- Buildings can be dragged into where they actually stand, and also moved with
  the keyboard, which is why positions are stored rather than inferred. Until
  somebody arranges them they sit on an automatic grid that provably never
  overlaps.

#### Residents  `/residents`  (`resident.view`)

Everyone who lives, or has lived, in the society. Tenancies with an agreement
running out in the next 60 days are flagged at the top. People are moved in and
out from the unit itself, which is what keeps each unit's history straight.
Those with `history.view` also see past residencies and every flat a person has
held.

#### Directory  `/directory`  (`directory.view`)

Who lives where, for residents to find each other. Only current residents
appear. Whether phone numbers are shown to residents is a per-society setting,
off by default. This is the one screen that shows residents to residents.

#### Parking  `/parking`  (`unit.view`)

Slots, who holds them, and the vehicles in them. A slot the society charges for
can carry a charge head so it bills with the maintenance. An unallotted slot
and a slot nobody uses are different things; the allotment record is what
settles a dispute.

### Operations

#### Helpdesk  `/helpdesk`  (`complaint.create` or `complaint.view_all`)

Resident complaints with a clock on each one. The category sets the response
and resolution SLA. Assignment, comments, status history and resident ratings
are all on the ticket. A complaint past its deadline escalates on its own.

Resolving is not closing: the resident confirms, which is what stops a ticket
being marked done while the lift is still broken.

#### Work orders  `/work-orders`  (`work_order.view`)

Jobs given to a vendor or to staff, with the agreed amount and the date wanted
by. A work order can come from a complaint, from a preventive schedule, or be
raised by hand. Completing one can raise the expense, so the cost lands in the
books without being keyed twice.

#### Assets and AMC  `/assets`  (`asset.view`)

Lifts, pumps, generators and fire equipment, with the contracts that keep them
running. Each asset can carry a maintenance schedule that raises the job when
it is due. An AMC records who is under contract, until when, and for how much,
and is flagged before it lapses rather than after.

#### Amenities  `/amenities`  (`amenity.view`, booking needs `amenity.book`)

The clubhouse, hall and courts residents can book. Each sets its own hours,
charge, deposit, how far ahead it can be booked and per-unit caps. Two bookings
cannot overlap: the system refuses the second. Bookings that need committee
approval sit pending. A deposit is held and returned separately from the
booking charge.

#### Staff  `/staff`  (`staff.view`)

People the society employs: guards, housekeeping, the manager, the plumber on
call, with shift, contact, police verification and ID proof, which is what an
inspection asks for. A guard given an account signs in to the gate screen.

### Security

#### Gate  `/gate`  (`gate.operate`)

The guard's screen, built for one-handed phone use. Pick the kind of arrival,
then the company or name, then the flat: three taps and nothing typed. The
resident is told straight away rather than the guard waiting on a phone call.
Waiting, Expected and Inside are the three lists, each with one-tap actions. A
delivery left at the desk with no flat is logged without troubling anybody.

A guard signs in straight to this screen and sees nothing else.

#### Visitors  `/visitors`  (`visitor.manage`)

Everyone who has come through the gate and who is inside right now. This is the
record and the search over it; entries come from the guard's screen.

#### Gate passes  `/gate-passes`  (`visitor.manage`)

Permission to take something out: furniture, appliances, a contractor's tools.
A resident raises the request, the committee approves it, and the approved pass
carries a code the guard checks at `/verify/pass/{token}`. A pass stops working
when it expires. This is what prevents the argument about whether the sofa
leaving the gate was allowed.

### Community

#### Notices  `/notices`  (`notice.view`, publishing needs `notice.manage`)

Circulars with audience targeting (everyone, owners, tenants, committee,
staff), pinning, and read receipts showing who has actually seen it. Publishing
emails it as well as posting it, in the society's own wording. Pin the few that
must stay at the top; pinning everything pins nothing.

#### Meetings  `/meetings`  (`meeting.view`, running them needs `meeting.manage`)

General body and committee meetings: the notice and the record that it was sent
(which is the proof it was given), the agenda, RSVP, proxy attendance,
attendance marked on the day with quorum worked out from it, minutes,
resolutions and action items.

#### Polls  `/polls`  (`poll.view`, running them needs `poll.manage`)

Asking members to decide something without waiting for the next meeting.
Voting can be one per member, one per unit, or weighted by unit area, which
matters for anything financial. Results are counted as votes arrive and shown
when the poll closes. Who voted which way is shown to the chairman, secretary
and society administrator only, so a disputed vote can be settled; a poll set
up as a secret ballot stays secret from them too.

A poll is not a substitute for a resolution where the bye-laws require a
meeting.

#### Documents  `/documents`  (`document.view`, uploading needs `document.manage`)

The society's papers: bye-laws, registration, audited accounts, AGM minutes,
agreements. Filed under a category, with visibility per role, and an expiry
date that is flagged before it lapses.

### Administration

#### Society settings  `/settings`  (`society.settings`)

Three panels:

- **Profile**: name, type, registration number, GSTIN, address, contact, area
  unit (sq.ft., sq.m., sq.yd.), financial year start month, payment mode.
- **Payment gateway**: provider, test or live environment, key id, key secret
  and webhook secret, stored encrypted per society so each collects into its
  own bank account. A society collects offline until a gateway is configured
  **and** verified against the provider.
- **Preferences**: whether an offline payment needs approving, whether
  residents see each other's phone numbers, whether the helpdesk auto-escalates
  and after how many hours, and whether a walk-in visitor needs resident
  approval.

#### Reminders and messages  `/settings/communication`  (`society.settings`)

When the system chases an unpaid bill and the words it uses.

- The **reminder ladder** is rows in the database, not a constant. A step is a
  signed number of days from the due date: `-3` is three days before, `0` is
  the day itself, `7` is a week after. A new society starts with `-3, 0, 7, 21,
  45`. Reminders go out once a day and only on the days named.
- **Eight message templates** are editable: maintenance reminder, payment
  receipt, new bill, notice published, meeting notice, minutes circulated,
  complaint update, visitor at the gate. Each declares the placeholders it
  supplies; the editor previews the finished text against sample data and warns
  about one that is mistyped. A society with no override gets the packaged
  wording, so the system communicates correctly before anyone opens the editor,
  and reverting is a delete.
- Substitution is a plain `{{ token }}` replacement, deliberately not Blade:
  these are edited in a textarea by committee members, and rendering
  user-edited Blade would run whatever they typed.

#### Roles and access  `/settings/roles`  (`society.manage`)

Who can do what, for this society only. Each role carries a set of permissions
and a person can hold more than one. Somebody who administers two societies can
have different powers in each. Keep at least two people with full
administration; one is a single point of failure.

#### Audit log  `/audit-log`  (`audit.view`)

Who changed what and when, with old and new values against the person who made
the change. Not editable, by anyone, deliberately. This is the record that
settles an argument.

### Setup wizard  `/onboarding`  (`society.settings`)

Four steps from empty to billing: identity, structure, charges, collection.
Covered step by step in [WORKFLOWS.md](WORKFLOWS.md).

---

## 5. Automation

One cron entry drives everything:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

| Command | When | What it does |
|---|---|---|
| `billing:run` | 02:00 daily | Raises bills for plans that have come due |
| `billing:accrue-late-fees` | 03:00 daily | Posts interest on overdue bills, after billing so a bill raised today is not immediately penalised |
| `billing:remind` | 10:00 weekdays | Reminds residents on the society's own ladder around the due date |
| `helpdesk:escalate` | hourly | Escalates tickets that have missed their SLA |
| `maintenance:raise-work-orders` | 06:00 daily | Turns preventive schedules into work orders |

Every one is idempotent. `billing:run --dry` reports what would be billed
without writing; `billing:remind --date=` replays a day's schedule without
sending twice.

Messages leave by one door, which records what was sent, to whom and when,
under a unique dedupe key that stops a duplicate in the database rather than in
each caller. Email is delivered. SMS and WhatsApp are recorded as queued until
a provider is connected, rather than reported as sent.

---

## 6. Interface

- **System font stack with Apple's faces first**, so on iOS and macOS it renders
  in SF Pro and falls back to Inter elsewhere.
- **Dark mode is a selected palette, not an inversion**, applied by an inline
  script before first paint so there is no flash of the wrong theme.
- **Tables become cards on a phone.** From `md` up a list is an ordinary
  semantic `<table>`; below that the header row is hidden and each row becomes a
  stacked card with every cell captioned by its own label. One markup, two
  presentations, which is what makes an eight-column financial table readable at
  390px.
- **Accessibility is in the primitives**: a skip link, one consistent focus
  ring, `aria-current` on the active nav item, fields that wire their own
  labels, hints and errors through `aria-describedby`, status carried by a text
  label as well as a hue, polite live regions for toasts, and
  `prefers-reduced-motion` and `prefers-contrast` honoured.
- **Every screen explains itself.** A help control in the topbar opens a panel
  for the screen you are on: what it is for, the steps that matter, and the
  things people get wrong. A detail screen falls back to its list's guide.
- **Client-side validation mirrors the server rules**, with real sentences
  rather than browser defaults, and a styled confirm dialog replaces
  `window.confirm` on anything destructive.
- `public/build` is committed, because this app is normally run straight from a
  clone and a pull that brings new templates but not their CSS looks like a
  broken interface rather than a missing build step.

---

## 7. Where the boundaries are

Stated plainly, because an honest gap list is more useful than a feature list
for deciding what to build next.

**Built and working**

Billing and collection end to end, double-entry accounting and the four
statements, receipts with QR verification, the full property and occupancy
record, helpdesk with SLA, work orders, assets and AMC, amenity booking, gate
and visitor management, notices, meetings, polls, documents, the reminder
engine with editable templates, role-scoped access, the platform console, and
the 2D and 3D site plan.

**Partly built**

| Area | State |
|---|---|
| Online payments | Razorpay driver written behind an interface; the checkout flow and webhook are in place. Other providers are one class each |
| SMS and WhatsApp | Dispatches are recorded and queued; no provider is connected, and nothing claims to have been delivered |
| Budgets | Table and model exist; the screen does not |
| SOS alerts | Table and model exist; the screen does not |
| Daily help attendance | Table and model exist; the screen does not |
| Adjustments and write-offs | Table and model exist, and the ledger handles them; there is no screen, so a correction is made by recording a balancing entry |
| Audit log | The viewer is built and the table exists; most services do not yet write rows to it |

**Not built**

- No mobile app. The interface is responsive and works on a phone browser.
- No resident self-service portal distinct from the main app. Residents sign in
  to the same application and see a resident's version of it.
- No accounting export to Tally or Zoho Books.
- No bank statement import or auto-reconciliation.
- No GST return preparation, though GSTIN and tax rates are recorded.
- No multi-language interface. Copy is English only.
- No tenant onboarding workflow with police verification submission.
- No parking allotment auction or rotation.
- No electricity or water sub-meter readings.
- No visitor pre-approval from a resident's phone as a separate quick action
  beyond the expected-visitor list.

---

## 8. For an AI asked to review, compare or extend this

This section exists so this file can be pasted into another model with a useful
question attached.

### Context to give the model

Paste sections 1 to 7 above. They contain the product's scope, its architecture,
its domain rules, every panel and an honest gap list. Then add one of the
prompts below.

### Prompt: compare against the market

> Below is a complete description of an internal society and community
> management system built for the Indian market. Compare it against the
> established products in this category (examples: ADDA, MyGate, NoBrokerHood,
> ApnaComplex, Society123, Neighbium, Common Areas, Buildium, AppFolio for the
> adjacent US market).
>
> For each competitor, tell me: what they have that this does not, what this has
> that they do not, and where this system's design choices are better or worse
> for a committee of volunteers rather than a professional property manager.
> Be specific about features, not positioning. Ignore pricing and go-to-market.

### Prompt: what to build next

> Below is a complete description of a society management system, including an
> honest list of what is not built. Propose the next ten things to build, ranked
> by how much they would change the daily experience of (a) a treasurer, (b) a
> resident, (c) a security guard. For each, say which existing table or service
> it would extend, whether it needs a new permission, and what the smallest
> useful version looks like. Prefer depth in what exists over new modules.

### Prompt: challenge the domain model

> Below is a description of how a society management system resolves a
> maintenance amount for one flat, how it numbers receipts, and how it records
> occupancy. Challenge those three designs. Where would they break in a real
> society of 400 flats across six wings with mixed ownership, commercial units
> on the ground floor and a separate sinking fund per wing? What is missing?

### Dimensions a fair comparison should use

| Dimension | What to ask |
|---|---|
| Billing flexibility | Can maintenance differ by building, by size, by both, and per flat? Can one flat be exempted with a reason on the record? |
| Prepayment | Can a yearly discount be recorded, and can it differ per building? |
| Receipt integrity | Is the series gap-free per financial year? Is a receipt verifiable without signing in? |
| Accounting | Is it double entry, and do the statements derive from the journal or get re-totalled from invoices? |
| Idempotency | Can a bill run or an interest run be repeated safely? |
| Collection | Does it work with no payment gateway at all? Is the gateway per society? |
| Communication | Can the committee change when reminders go and what they say, without a developer? |
| Privacy | Is past occupancy and vote history behind a separate permission from the directory? |
| Governance | Is the meeting notice period provable? Is quorum derived from attendance? |
| Accessibility | Does the 3D view remain keyboard and screen reader usable? |
| Tenancy | Can one person hold different roles in two societies without leaking? |
| Self-hosting | Can a single society run it on its own server with one cron entry? |

### Facts an evaluating model should not have to guess

- 209 automated tests, run against MySQL rather than SQLite, so anything
  engine-specific is caught in the suite.
- One database, scoped by `society_id`, with a global scope and a write-stamping
  trait rather than per-tenant databases.
- Roles are per society; the platform operator role is held outside all of them.
- No background queue is required for the system to function; mail and
  notifications are better off a queue but the app works without one.
- The whole thing runs from a clone with `php artisan serve` and one cron line.
