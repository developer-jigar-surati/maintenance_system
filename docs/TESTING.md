# Testing

How this system is verified, what the suite actually guarantees, and what to
check by hand before a release.

Companion documents: [PROJECT-OVERVIEW.md](PROJECT-OVERVIEW.md),
[WORKFLOWS.md](WORKFLOWS.md).

---

## 1. Running the suite

```bash
php artisan test                    # everything
php artisan test --filter=Billing   # one group
php artisan test --filter=PerHomeAmountTest
php artisan test tests/Feature/Billing/LateFeeTest.php
```

**209 tests, 1,274 assertions.** A full run takes about two minutes.

### Before the first run

The suite runs against a **real MySQL database**, not SQLite, so anything
engine-specific is caught here rather than in production. Create it once:

```sql
CREATE DATABASE sankul_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Host, username and password come from `.env`. Only the database name is
overridden, in `phpunit.xml`. Nothing else needs configuring: mail goes to an
array driver, the queue runs synchronously, the cache and session are in
memory, and bcrypt drops to 4 rounds so hashing does not dominate the run.

### Why not SQLite

Four things in this system behave differently on SQLite and would pass there
while failing in production: `enum` columns, the `FOR UPDATE` row lock behind
gap-free numbering, `decimal` arithmetic in aggregates, and index handling when
a foreign key shares an index with a unique constraint. The last of those bit
us for real during a migration. Running on the production engine is not
optional.

### The other checks

```bash
vendor/bin/pint --test   # formatting, must be clean
npm run build            # must succeed; commit the output with your change
```

---

## 2. What the suite guarantees

Grouped by what would be expensive to get wrong. Every test name below is a
sentence describing a guarantee that currently holds.

### Tenancy: 6 tests  `tests/Feature/Tenancy/SocietyIsolationTest.php`

That one society can never read another's data by accident.

- A query only returns rows from the active society
- `society_id` is stamped on create without being passed
- An explicit `society_id` is respected
- Scoping can be suspended for platform-wide reporting, and is restored afterwards
- Invoices of another society are not visible

### Access control: 20 tests

`Access/PermissionTest.php` (9) and `Access/HistoryVisibilityTest.php` (11).

Permissions:

- A resident cannot reach the financial reports; a treasurer can
- A guard reaches the gate but not the books
- A super admin passes every gate
- Roles are scoped to a society, so the same person differs between two
- A treasurer holds the money permissions and not the rest
- A resident sees only their own invoices and cannot open another unit's
- Guests are sent to the login page

History and votes, which are their own permission:

- The office bearers can read a unit's past; a manager, a treasurer and a
  committee member cannot
- **A past resident is never sent to the browser at all** for somebody without
  the permission. Asserted against the raw HTML, because a name hidden behind
  an `@if` is still there to read
- The residents list hides past residencies, and refuses the filter that would
  show them
- The secretary can see who voted which way; a resident cannot see how a
  neighbour voted; a secret ballot stays secret from the office bearers too

### Billing arithmetic: 42 tests

`ChargeRateScopeTest` (12), `PerHomeAmountTest` (8), `AdvanceOfferTest` (10),
`InvoiceGenerationTest` (10) and part of the rest.

How much a flat is charged:

- One amount covers every home
- A building can charge its own amount, and a size of home can charge its own
- A building and a size together beat either on its own
- The more specific statement always wins
- A flat the grid does not name falls back sensibly
- Changing the committee's answer clears the previous answer's rates, so a
  society that moves from per-building to one amount is not left with old rates
  quietly still billing people
- A rate for one society never reaches another

One home settled by hand:

- An amount set for one home beats its building
- A home can be left out of a charge altogether
- An amount only applies while it is in force (effective dates are honoured)
- The card says where each amount came from
- A home can be put back on the shared amount
- An amount is required unless the home is marked as not charged
- A resident cannot change what their home pays

Paying the year up front:

- A home is told what it saves
- A building can be given a different deal
- **A building told it gets nothing does not inherit the society's offer**
- A yearly plan offers nothing, because it is already one bill
- The reference year follows what each building actually pays
- A typed yearly amount is stored as a percentage, and shown back as money

Invoices:

- Per-square-foot charges multiply rate by area; fixed charges do not
- Several heads sum onto one invoice
- **A second run for the same period bills nobody twice**
- Units marked not billable are skipped
- A unit exemption removes the charge; a unit override wins over the default
- Tax is added when the head is taxable
- Arrears from earlier bills are carried onto the next
- A draft plan produces draft invoices

### Interest: 11 tests  `Billing/LateFeeTest.php`

- Annual interest is charged one month at a time; a monthly rate is charged directly
- A flat penalty ignores the balance
- **Running the same period twice does not charge twice**
- Nothing accrues during the grace period, below the threshold, or on a paid invoice
- Interest is capped at the maximum
- Simple interest does not compound on earlier interest; compound interest does
- The invoice total grows by exactly the interest posted

### Payments and receipts: 18 tests

`Payments/PaymentAllocationTest.php` (10) and `Billing/NumberSequenceTest.php` (7),
plus `PaymentModeTest` partly.

- A payment settles the oldest bill first, and one payment can settle several
- An overpayment is kept as credit rather than refused, and applied to the next bill
- An offline payment waits for approval when the society requires it; approving
  settles the bill and issues the receipt; a rejected payment leaves the bill
  untouched; a payment cannot be approved twice
- **Receipt numbers are sequential and gap-free**, one receipt per payment
- Numbers increment without gaps, each society has its own series, series are
  independent, the number carries the financial year, a calendar-year society
  uses a plain year, peeking does not consume a number, and the ticket series
  never resets

### Accounting: 8 tests  `Billing/LedgerIntegrityTest.php`

- Every journal entry balances, and an unbalanced entry is refused outright
- The trial balance agrees and the balance sheet ties out
- Billing creates a receivable and recognises income; collecting reduces the receivable
- **An advance is held as a liability, not income**
- A reversal cancels the original

### Payment mode: 8 tests  `Billing/PaymentModeTest.php`

- A new society collects offline only
- **Choosing online alone does not enable it**, nor does an inactive gateway,
  nor an active one with no keys
- An active configured gateway enables online
- An online-only society does not accept offline
- Gateway credentials are encrypted at rest
- An unknown provider is rejected

### Scheduled commands: 8 tests  `Billing/ScheduledBillingTest.php`

- The billing command raises bills for due plans and leaves plans not yet due alone
- A dry run writes nothing
- An inactive plan is skipped
- **Running the command twice does not double bill**
- It bills every society it finds, and the run can be limited to one
- The accrual command is safe to run repeatedly

### Messaging: 24 tests

`ReminderScheduleTest` (9), `TemplateRenderingTest` (8), `AnnouncementTest` (7).

- A new society starts with a reminder ladder
- **A reminder goes out only on a day the schedule names, and never twice in a day**
- The committee can change when reminders go out; a step switched off sends nothing
- A bill with nobody to write to is skipped, not failed; a paid bill is never chased
- One society's schedule does not reach another
- Placeholders are replaced whatever spacing is typed, and one with no value
  leaves no machinery showing
- **A template is never executed as code**
- The packaged wording is used until a society writes its own; deleting the
  override restores it
- A mistyped placeholder is reported rather than sent
- **Every packaged template only uses placeholders it declares**
- A receipt reaches the resident who paid, and is not sent twice for the same payment
- A notice reaches only the audience it names
- A resident is told when a visitor arrives; a delivery left at the gate troubles nobody
- Announcements never cross from one society into another

### Property: 25 tests

`OccupancyTest` (12) and `SitePlanTest` (13).

Occupancy:

- **A move-out closes the record rather than deleting it**
- A unit's status follows who actually lives there; an empty unit reads vacant;
  a unit under construction is not relabelled by occupancy
- **Bills never lose their destination when someone leaves**, and only one
  person at a time receives a unit's bills
- A sale hands the unit over without leaving it ownerless
- A unit keeps a readable history; a person's history follows them between flats
- A residency reads its length the way a person would say it
- The billing contact is readable whichever relation was eager loaded
- History does not leak between societies

Site plan:

- **Buildings nobody has positioned are laid out without overlapping**, proven
  for 1 to 12 buildings
- The automatic layout leaves the landmark band clear
- A positioned building keeps its position while an unpositioned one is placed
- Floors are stacked top first, and read the way people name them
- Every view gives a unit a tone **and a meaning in words**
- The dues view separates paid up from what is owed
- Both views draw the same buildings from the same figures
- **A flat stays a labelled control in the 3D view**
- An unknown view mode falls back to the flat plan
- A dragged building cannot be pushed off the plot
- A site plan shows only its own society's buildings

### Facilities: 10 tests  `Facilities/AmenityBookingTest.php`

- A valid slot books; an overlapping one is refused; a back-to-back one is allowed
- Booking too far ahead, shorter than the minimum, on a blackout or on a closed
  day is refused
- A unit cannot exceed its concurrent booking cap
- Hourly charging multiplies by the hours booked
- An amenity needing approval starts as pending

### Platform: 14 tests

`SocietyConsoleTest` (9) and `CreateSocietyCommandTest` (5).

- A platform operator can open the console; a society administrator and a
  resident cannot
- The console lists every society, and **unit counts are per row, not per
  active society**
- Creating a society provisions it completely
- An existing account is reused, not duplicated
- Creation requires a name and an administrator
- The command creates and provisions a society, its administrator can run it,
  it can mint a platform operator, the promotion command grants and revokes,
  and each society gets its own code

### Interface and help: 13 tests

`Interface/FormMarkupTest.php` (8) and `Help/ModuleGuideTest.php` (5). These are
guards against regressions that are invisible until a user hits them.

- **Every view compiles.** Added after a bulk edit broke four screens at once
- Every form asks the browser to check it first, and none is left to the
  browser's own validation bubbles
- Every confirmation says what will happen, and nothing destructive falls back
  to `window.confirm`
- Exactly one menu item is marked current, and a detail page highlights the
  list it belongs to
- **No em dashes are left in the interface**
- Every help guide points at a route that exists
- Every screen a committee uses explains itself
- A guide says what the screen is for before anything else
- A detail screen falls back to its list's guide
- A screen with no guide shows no panel rather than an empty one

### Money formatting: 5 tests  `Unit/MoneyTest.php`

- Grouping follows the lakh convention (1,23,456.00)
- Negative amounts keep their sign
- The compact form uses lakh and crore
- Amount in words matches the receipt convention, with paise spelled out

### Lazy loading is prevented throughout

`Model::preventLazyLoading()` is on outside production, which includes the test
run. A relation read without being eager loaded throws rather than quietly
firing an extra query, so an N+1 shows up as a failure.

One test in `OccupancyTest` asserts it explicitly, because `residents` and
`activeResidents` are two relations over overlapping rows and a helper that
reads one while a screen eager loaded the other is a real bug that has
happened. It saves the current setting and puts it back; it used to restore a
hard `false`, which left every test that ran after it with the check silently
switched off.

---

## 3. Writing a test

### Helpers on `Tests\TestCase`

| Helper | What it gives you |
|---|---|
| `makeSociety(array $attributes = [])` | A fully provisioned society, made the active one. Charge heads, roles, chart of accounts and financial year are already there |
| `makeUser(Society $s, string $role, array $attributes = [])` | A user attached to the society with that role |
| `makeUnit(Society $s, array $attributes = [])` | A billable flat with 1000 sq.ft. of carpet area |
| `makeBlock(Society $s, string $name = 'A')` | A wing |
| `setChargeRate(string $code, float $rate, string $basis)` | Sets a rate on one of the seeded heads |
| `actingWithinSociety(Society $s)` | Points both the model scope and the permission team at a society |

`tearDown` forgets the society context, so a test cannot leak its active
society into the next one.

### The shape of a test

```php
class PerHomeAmountTest extends TestCase
{
    use RefreshDatabase;   // not optional: see below

    public function test_an_amount_set_for_one_home_beats_its_building(): void
    {
        $society = $this->makeSociety();
        $head = $this->maintenance();
        $block = $this->makeBlock($society, 'A');

        $settled = $this->makeUnit($society, ['unit_number' => '101', 'block_id' => $block->id]);

        // ... arrange rates and overrides ...

        $this->assertSame(9000.0, app(ChargeCalculator::class)->for($settled, $head)['rate']);
    }
}
```

Testing a Livewire screen:

```php
Livewire::actingAs($this->makeUser($society, Role::SOCIETY_ADMIN))
    ->test(UnitCharges::class, ['unit' => $unit])
    ->call('edit', $head->id)
    ->set('amount', 9000)
    ->call('save')
    ->assertHasNoErrors();
```

And the negative case, which matters as much:

```php
Livewire::actingAs($this->makeUser($society, Role::OWNER))
    ->test(UnitCharges::class, ['unit' => $unit])
    ->call('edit', $head->id)
    ->assertForbidden();
```

### Rules

1. **Always `use RefreshDatabase`.** A test class without it leaks rows into
   every later test and the failure appears somewhere else entirely. This has
   happened once and cost an afternoon.
2. **Name the test as the guarantee**, in a sentence a committee member would
   understand: `test_a_building_told_it_gets_nothing_does_not_inherit_the_society_offer`,
   not `test_advance_discount_zero`. The suite doubles as documentation, and
   section 2 of this file is generated from reading those names.
3. **Assert the negative too.** For anything permission-shaped, test the role
   that must not reach it.
4. **Use real money figures from real societies.** 12,000 a month and 1,20,000
   for the year reads as a decision somebody made; 100.00 does not.
5. **Decimal columns come back as strings.** Compare with
   `(string) $model->rate === '1400.0000'` or cast explicitly. `assertSame` on a
   float against a decimal cast will fail in a confusing way.
6. **A percentage round trip is lossy.** 16.67% of 1,44,000 is 1,19,995.20, not
   the 1,20,000 that was typed. Assert what the code actually produces and say
   why in a comment.

---

## 4. Browser verification

The test suite does not render the interface in a browser. Anything that
touches layout, Alpine, Livewire round trips or the 3D view gets driven in
Chromium with Playwright before it is committed.

Chromium is pre-installed; do not run `playwright install`.

```js
import { chromium } from 'playwright';

const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
```

The script must live in the project root, because `playwright` resolves from
`node_modules` there.

### What a verification run should cover

1. **Sign in as each affected role** and visit each affected screen. A screen
   that 500s for a resident and works for a treasurer is the usual failure.
2. **Collect console errors and page errors** and assert there are none. Most
   Alpine mistakes surface only here.
3. **Drive the interaction, do not just screenshot it.** Open the modal, submit
   it empty to see the validation message, fill it, save, and read the result
   back off the page.
4. **Desktop and phone.** 1440 x 1000 and 390 x 844 with `isMobile: true`.
5. **Light and dark**, via `window.setThemePreference('dark')`.
6. **Read the text back**, not just the pixels. `innerText` on the card catches
   "Every monthly", a missing space before a full stop, and
   "12 bills normally comes to" in a way a screenshot does not.

```js
const page = await ctx.newPage();
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
page.on('pageerror', (e) => errors.push(e.message));
```

### The standing smoke run

A broader script walks every screen in the sidebar as a treasurer, the five
detail pages, a handful as a resident on a phone, and the gate console as a
guard, asserting each returns 200 and contains none of `Whoops`, `SQLSTATE`,
`Undefined variable` or `Call to a member function`. Run it after anything that
touches shared layout, navigation or a UI primitive.

---

## 5. Manual checklist before a release

Automated tests do not cover judgement. Walk these by hand.

### As a platform operator

- [ ] Create a society. It lands with charge heads, roles, a chart of accounts,
      a reminder ladder and an open financial year
- [ ] Its administrator can sign in and is taken to the setup wizard
- [ ] Opening a society switches context; the other society's data is not visible

### As a society administrator

- [ ] The wizard runs end to end: identity, structure, charges, collection
- [ ] `A, B` and `101-104` produce eight units in two blocks
- [ ] Settings saves; gateway keys are not readable back in plain text
- [ ] The reminder ladder can be changed and a template previewed
- [ ] Roles can be edited and the change takes effect on the next request

### As a treasurer

- [ ] Rates can be set by building, by size, and by both at once
- [ ] A flat can be given its own amount, and put back on the shared one
- [ ] The prepayment offer can be set society-wide and per building
- [ ] **Run now** raises bills; a second run raises nothing
- [ ] An invoice PDF downloads and reads correctly
- [ ] A payment records, allocates oldest first, and issues a receipt
- [ ] The receipt PDF's QR code opens the verification page while signed out
- [ ] All four reports render and export as CSV

### As a manager

- [ ] A resident can be moved in and out; the unit's status follows
- [ ] A complaint can be raised, assigned, commented on and resolved
- [ ] A work order can be raised from a complaint
- [ ] An amenity booking clashes correctly

### As a resident

- [ ] The dashboard shows their own position only
- [ ] They cannot reach `/units`, `/reports` or another flat's invoice
- [ ] They can raise a complaint, book an amenity and vote in a poll

### As a guard

- [ ] Signing in lands on the gate console, with no other navigation
- [ ] An arrival can be logged in three taps on a 390px screen
- [ ] A gate pass code verifies

### Across the interface

- [ ] Dark mode on every screen touched, with no flash of the wrong theme
- [ ] Phone width on every screen touched; tables read as cards
- [ ] Keyboard only: tab through a form, open a modal, close it with Escape,
      and confirm focus returns where it was
- [ ] The help panel opens on every screen and describes that screen

---

## 6. Troubleshooting

| Symptom | Cause |
|---|---|
| `SQLSTATE[HY000] [2002]` | MySQL is not running, or `.env` points somewhere else |
| `Base table or view not found` in tests | `sankul_test` does not exist, or a migration failed halfway. Drop and recreate it |
| Tests pass alone and fail together | A test class is missing `use RefreshDatabase` |
| `Attempted to lazy load [x]` | A relation is read in a view without being eager loaded. Add it to the `with()` in the component's `render()` |
| A view renders but the styling is wrong | `npm run build` was not run, or `public/build` was not committed |
| `Undefined variable $method` in a Livewire view | A public property named `$method` collides with Livewire's own update payload key. Rename it |
| A modal never opens from a Livewire dispatch | Alpine sends the name as a string, Livewire wraps it. The modal component reads both; a custom one must too |
