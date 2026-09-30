<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * What each screen is for, written for the person using it.
 *
 * A society committee is volunteers, not operators: a treasurer who took the
 * job last month has to work out what a charge head is, why a bill will not
 * issue, and what happens if they press the button. Documentation on a
 * website does not get read; an answer on the screen does.
 *
 * Each entry is deliberately short. "What this is for" in a sentence, the
 * handful of steps that matter in order, and the one or two things people
 * actually get wrong.
 */
class ModuleGuide
{
    /**
     * The guide for the screen being viewed, or null if it has none.
     *
     * @return array<string, mixed>|null
     */
    public static function forCurrentRoute(): ?array
    {
        $name = Route::currentRouteName();

        return $name === null ? null : self::for($name);
    }

    /**
     * The guide for a named route.
     *
     * @return array<string, mixed>|null
     */
    public static function for(string $route): ?array
    {
        $guides = self::all();

        if (isset($guides[$route])) {
            return $guides[$route] + ['key' => $route];
        }

        // `invoices.show` falls back to `invoices.index`: a detail screen and
        // its list are the same subject, and someone reading a single bill
        // needs the same explanation as someone reading the list.
        $group = str_contains($route, '.') ? explode('.', $route)[0] : $route;

        foreach ($guides as $key => $guide) {
            if (str_starts_with($key, $group.'.')) {
                return $guide + ['key' => $key];
            }
        }

        return null;
    }

    /**
     * @return array<string, array{
     *     title: string, summary: string,
     *     steps: array<int, string>, watch: array<int, string>
     * }>
     */
    public static function all(): array
    {
        return [
            'dashboard' => [
                'title' => 'Dashboard',
                'summary' => 'Where the society stands today: what is owed, what is open, and what is coming up.',
                'steps' => [
                    'Collection tells you what has been billed against what has come in. A gap that grows month on month is the number to act on.',
                    'Defaulters is the shortlist to chase. Opening one takes you to that unit and everything it owes.',
                    'Anything under Helpdesk marked urgent has a clock running against it.',
                ],
                'watch' => [
                    'What you see depends on your role. A treasurer sees money, a resident sees their own bill.',
                ],
            ],

            'invoices.index' => [
                'title' => 'Invoices',
                'summary' => 'Every maintenance bill raised against a unit, and what is still outstanding on it.',
                'steps' => [
                    'Bills are normally raised by a billing plan on a schedule, not typed one by one.',
                    'A draft can still be changed. Once issued, it is a document a resident has seen, so a correction is a credit note rather than an edit.',
                    'Outstanding is what is left after every payment allocated to that bill.',
                ],
                'watch' => [
                    'A bill nobody can be sent - a unit with no billing contact - still raises. Check under the unit who it is addressed to.',
                    'Interest is added by the late-fee run, once per period, so running it twice does not charge twice.',
                ],
            ],

            'payments.index' => [
                'title' => 'Payments',
                'summary' => 'Money received, however it arrived: cash, cheque, UPI, bank transfer or the gateway.',
                'steps' => [
                    'Record what was actually received. The system allocates it against the oldest unpaid bill first.',
                    'More than was owed is held as credit against that unit, not refused.',
                    'A receipt is issued as soon as the payment is complete, and is sent to the resident.',
                ],
                'watch' => [
                    'If offline payments need approval, a recorded payment only settles a bill once it is approved.',
                    'A receipt number comes from a gap-free series. That is why a payment cannot simply be deleted.',
                ],
            ],

            'receipts.index' => [
                'title' => 'Receipts',
                'summary' => 'The digital replacement for a hand-written rasid: numbered, tied to a payment, and verifiable.',
                'steps' => [
                    'Every completed payment has exactly one receipt.',
                    'The PDF carries a QR code that anyone can scan to confirm the receipt is genuine.',
                    'Numbers run in an unbroken series per financial year, which is what an auditor checks.',
                ],
                'watch' => [
                    'A receipt is never renumbered or reused. Cancelling a payment leaves the receipt visible and marked, rather than removing it.',
                ],
            ],

            'charge-heads.index' => [
                'title' => 'Charge heads',
                'summary' => 'The things a society charges for: maintenance, water, sinking fund, parking.',
                'steps' => [
                    'Each head has a basis - a fixed amount per flat, a rate per square foot, or per member - and that decides how it is worked out.',
                    'A per-square-foot head multiplies its rate by the unit\'s area, so a larger flat pays more.',
                    'A unit that is charged differently from the rest gets an override on the unit itself, not a new head.',
                ],
                'watch' => [
                    'Changing a rate affects bills raised from now on. It does not rewrite bills already issued.',
                ],
            ],

            'billing-plans.index' => [
                'title' => 'Billing plans',
                'summary' => 'What gets billed, to whom, and how often. This is what makes billing automatic.',
                'steps' => [
                    'Pick the charge heads the plan raises and the cycle: monthly, quarterly or yearly.',
                    '"Due after" sets how long residents have to pay from the bill date.',
                    'With auto-generate on, the scheduled run raises the bills. Nobody has to remember the first of the month.',
                ],
                'watch' => [
                    'A plan will not bill the same unit for the same period twice, so a re-run is safe.',
                    'Auto-issue sends bills out as soon as they are raised. Leave it off if the committee reviews them first.',
                ],
            ],

            'expenses.index' => [
                'title' => 'Expenses',
                'summary' => 'What the society spends, against which head, and who approved it.',
                'steps' => [
                    'Record the vendor, the amount and the head it belongs to.',
                    'Approval is a separate step from recording, so one person entering an expense cannot also clear it.',
                    'Approved expenses post to the ledger and appear in the income and expenditure report.',
                ],
                'watch' => [
                    'Attach the bill. An expense without a document is the first thing an auditor asks about.',
                ],
            ],

            'reports.index' => [
                'title' => 'Reports',
                'summary' => 'The statements a society has to produce: collection, income and expenditure, defaulters, ledger.',
                'steps' => [
                    'Everything is drawn from the same double-entry ledger, so the figures agree with each other.',
                    'Collection efficiency is what came in against what was billed, over a period you choose.',
                    'Every report exports, which is what the auditor and the AGM pack need.',
                ],
                'watch' => [
                    'Figures are for the financial year you have open. Check the year before you read a total as wrong.',
                ],
            ],

            'units.index' => [
                'title' => 'Units',
                'summary' => 'Every flat, villa, shop or plot in the society, and what each one owes.',
                'steps' => [
                    'A unit belongs to a block or wing, and its area is what per-square-foot charges are worked out from.',
                    'Opening a unit shows its bills, payments and who lives there.',
                    'A unit that should not be billed - a society office, say - can be marked not billable.',
                ],
                'watch' => [
                    'Occupancy is worked out from who lives there, so it is never typed and never drifts.',
                    'Past residents are shown to the chairman, secretary and society administrator only. Anyone else sees who lives there now.',
                ],
            ],

            'site-plan.index' => [
                'title' => 'Site plan',
                'summary' => 'The society drawn out: buildings on the site, flats inside a building, flat or in 3D.',
                'steps' => [
                    'Pick a building to see its floors stacked, top floor first, the way the building stands.',
                    'Tap any flat for who lives there, what it owes and what is open.',
                    'The colour control changes what the plan is showing you: occupancy, dues, or open complaints.',
                    '3D adds height, so a six-storey block reads as taller than a row of villas. Turn it, tilt it, and pull the floors apart to see into the middle of a tower.',
                ],
                'watch' => [
                    'Until somebody arranges them, buildings are on an automatic grid rather than where they actually stand.',
                    'Arranging is done on the flat plan, because positions are typed rather than dragged so they work with a keyboard.',
                ],
            ],

            'residents.index' => [
                'title' => 'Residents',
                'summary' => 'Everyone who lives, or has lived, in the society.',
                'steps' => [
                    'People are moved in and out from the unit itself, which keeps each unit\'s history straight.',
                    'Tenancies with an agreement running out in the next 60 days are flagged at the top.',
                    'The chairman, secretary and society administrator also see past residencies and, on any row, every flat that person has had.',
                ],
                'watch' => [
                    'A past resident is kept, not deleted, so an old receipt still names whoever actually paid it.',
                    'Who used to live in a flat is the society\'s record, not a neighbour\'s business. A resident who needs it asks the secretary.',
                ],
            ],

            'complaints.index' => [
                'title' => 'Helpdesk',
                'summary' => 'Complaints from residents, with a clock on each one.',
                'steps' => [
                    'A complaint\'s category sets how long there is to respond and to resolve it.',
                    'Assigning it to someone starts their name against it; the resident is told each time it moves.',
                    'A complaint past its deadline escalates on its own rather than sitting quietly.',
                ],
                'watch' => [
                    'Resolving is not closing. The resident confirms, which is what stops a complaint being marked done while the lift is still broken.',
                ],
            ],

            'vendors.index' => [
                'title' => 'Vendors',
                'summary' => 'The plumbers, lift companies and security agencies the society pays.',
                'steps' => [
                    'A vendor here can be picked on a work order, an expense or an AMC, so spend against them adds up.',
                    'Record the GSTIN and PAN: they are needed on any bill the society claims against.',
                ],
                'watch' => [
                    'Keep one record per vendor. Two spellings of the same company split their history in half.',
                ],
            ],

            'parking.index' => [
                'title' => 'Parking',
                'summary' => 'Slots, who holds them, and the vehicles in them.',
                'steps' => [
                    'A slot is allotted to a unit, and vehicles are recorded against that unit.',
                    'A slot the society charges for can carry a charge head, so it bills with the maintenance.',
                ],
                'watch' => [
                    'An unallotted slot and a slot nobody uses are different things. Allotment is the record that settles a dispute.',
                ],
            ],

            'directory.index' => [
                'title' => 'Directory',
                'summary' => 'Who lives where, for residents to find each other.',
                'steps' => [
                    'Only current residents appear.',
                    'Whether residents can see each other\'s phone numbers is a society setting, off by default.',
                ],
                'watch' => [
                    'This is the one screen that shows residents to residents. Treat the phone setting as a privacy decision.',
                ],
            ],

            'staff.index' => [
                'title' => 'Staff',
                'summary' => 'People the society employs: guards, housekeeping, the manager, the plumber on call.',
                'steps' => [
                    'Record the role, the shift and the contact number.',
                    'Police verification and ID proof are recorded here, which is what an inspection asks for.',
                    'A guard given an account signs in to the gate screen.',
                ],
                'watch' => [],
            ],

            'gate-passes.index' => [
                'title' => 'Gate passes',
                'summary' => 'Permission to take something out: furniture, appliances, a contractor\'s tools.',
                'steps' => [
                    'A resident raises the request; the committee approves it.',
                    'An approved pass carries a code the guard checks at the gate.',
                    'A pass is good until it expires, then it stops working on its own.',
                ],
                'watch' => [
                    'This is what prevents the argument about whether the sofa leaving the gate was allowed.',
                ],
            ],

            'onboarding.index' => [
                'title' => 'Setting up',
                'summary' => 'The handful of things a new society needs before it can raise its first bill.',
                'steps' => [
                    'Add your blocks and units, since everything is billed against a unit.',
                    'Link residents to units, and mark who bills go to.',
                    'Set your charge head rates, then create a billing plan and let it run.',
                ],
                'watch' => [
                    'Opening balances go in before the first bill. Afterwards, the arrears figure will be wrong.',
                ],
            ],

            'work-orders.index' => [
                'title' => 'Work orders',
                'summary' => 'Jobs given to a vendor or to staff, with what was agreed and what it cost.',
                'steps' => [
                    'A work order can come from a complaint or from scheduled maintenance.',
                    'Record the vendor, the agreed amount and the date it is wanted by.',
                    'Completing one can raise the expense, so the cost lands in the books without being keyed twice.',
                ],
                'watch' => [],
            ],

            'assets.index' => [
                'title' => 'Assets & AMC',
                'summary' => 'The lifts, pumps, generators and fire equipment, and the contracts that keep them running.',
                'steps' => [
                    'Each asset can carry a maintenance schedule, which raises the job when it is due.',
                    'An AMC records who is under contract, until when, and for how much.',
                    'A contract about to expire is flagged before it lapses, not after.',
                ],
                'watch' => [
                    'Fire equipment and lifts usually have statutory inspection dates. Record them here so the reminder exists.',
                ],
            ],

            'amenities.index' => [
                'title' => 'Amenities',
                'summary' => 'The clubhouse, hall and courts residents can book.',
                'steps' => [
                    'Each amenity sets its own hours, charge and deposit.',
                    'Two bookings cannot overlap on the same amenity - the system refuses the second.',
                    'Bookings that need committee approval sit as pending until somebody decides.',
                ],
                'watch' => [
                    'A deposit is held and returned separately from the booking charge.',
                ],
            ],

            'gate.index' => [
                'title' => 'Gate',
                'summary' => 'The guard\'s screen. Log who arrives in three taps and nothing typed.',
                'steps' => [
                    'Pick what kind of arrival it is, then the company or the name, then the flat.',
                    'The resident is told straight away; the guard does not wait on a phone call.',
                    'Waiting, Expected and Inside are the three lists that matter, each with one-tap actions.',
                ],
                'watch' => [
                    'A delivery left at the desk with no flat is logged without troubling anybody.',
                ],
            ],

            'visitors.index' => [
                'title' => 'Visitors',
                'summary' => 'Everyone who has come through the gate, and who is inside right now.',
                'steps' => [
                    'Entries come from the guard\'s screen; this is the record and the search over it.',
                    'Someone still showing as inside at the end of the day was probably not checked out.',
                ],
                'watch' => [],
            ],

            'notices.index' => [
                'title' => 'Notices',
                'summary' => 'Circulars to residents, and proof of who has read them.',
                'steps' => [
                    'Choose the audience - everyone, owners, tenants, committee or staff - and only they receive it.',
                    'Publishing emails it as well as posting it, in the society\'s own wording.',
                    'Read receipts show who has actually seen it.',
                ],
                'watch' => [
                    'Pin the few notices that must stay at the top. Pinning everything pins nothing.',
                ],
            ],

            'meetings.index' => [
                'title' => 'Meetings',
                'summary' => 'General body and committee meetings: the notice, the agenda, attendance, resolutions and the minutes.',
                'steps' => [
                    'Create the meeting with its agenda, then send the notice - bye-laws usually set a minimum number of days.',
                    'Attendance is marked on the day, and quorum is worked out from it.',
                    'Record the minutes and the resolutions passed; circulating them sends them to every member.',
                ],
                'watch' => [
                    'The record of the notice being sent is the proof that it was given. That is why it is kept.',
                ],
            ],

            'polls.index' => [
                'title' => 'Polls',
                'summary' => 'Asking members to decide something without waiting for the next meeting.',
                'steps' => [
                    'Set the options and when voting closes.',
                    'Voting can be one per member or one per unit, which matters for anything financial.',
                    'Results are counted as votes come in and shown when the poll closes.',
                ],
                'watch' => [
                    'A poll is not a substitute for a resolution where the bye-laws require a meeting.',
                    'Who voted which way is shown to the chairman, secretary and society administrator only, so a disputed vote can be settled. A poll set up as a secret ballot stays secret from them too.',
                ],
            ],

            'documents.index' => [
                'title' => 'Documents',
                'summary' => 'The society\'s papers: bye-laws, registration, audited accounts, AGM minutes, agreements.',
                'steps' => [
                    'Filing under the right category is what makes a document findable two committees later.',
                    'Visibility decides who can open it - everyone, owners, the committee, or admins only.',
                    'A document with an expiry date is flagged before it lapses.',
                ],
                'watch' => [],
            ],

            'settings.index' => [
                'title' => 'Society settings',
                'summary' => 'How this society runs: its details, how it collects money, and its preferences.',
                'steps' => [
                    'A society collects offline until a payment gateway is added and verified.',
                    'With a gateway live, residents can pay in the app and the committee can still record cash.',
                    'Preferences decide things like whether an offline payment needs approving.',
                ],
                'watch' => [
                    'Gateway keys are stored encrypted. Use test keys until you have put a real payment through.',
                ],
            ],

            'settings.communication' => [
                'title' => 'Reminders & messages',
                'summary' => 'When the system chases an unpaid bill, and the words it uses.',
                'steps' => [
                    'A step is a number of days from the due date: negative is before, 0 is the day itself.',
                    'Reminders go out once a day and only on the days you name here.',
                    'Every message can be rewritten in your own words; the editor previews it before you save.',
                ],
                'watch' => [
                    'A resident reminded every morning stops reading reminders. Fewer, firmer steps work better.',
                    'SMS and WhatsApp are recorded but not delivered until a provider is connected.',
                ],
            ],

            'settings.roles' => [
                'title' => 'Roles & access',
                'summary' => 'Who can do what. Roles belong to this society only.',
                'steps' => [
                    'Each role carries a set of permissions, and a person can hold more than one.',
                    'Changing a role changes it for everyone who holds it here.',
                    'Somebody who administers two societies can have different powers in each.',
                ],
                'watch' => [
                    'Keep at least two people with full administration. One is a single point of failure.',
                ],
            ],

            'audit.index' => [
                'title' => 'Audit log',
                'summary' => 'Who changed what, and when. Written automatically and not editable.',
                'steps' => [
                    'Every meaningful change records the old and new values against the person who made it.',
                    'Filter by person, by date or by what was touched.',
                ],
                'watch' => [
                    'This is the record that settles an argument. It cannot be edited, by anyone, deliberately.',
                ],
            ],

            'platform.societies' => [
                'title' => 'All societies',
                'summary' => 'Every society on this installation. Only platform operators see this.',
                'steps' => [
                    'Creating a society sets up its accounts, charge heads, roles and reminder schedule automatically.',
                    'Its first administrator is created with it; they finish setting it up from inside.',
                    '"Open" switches you into that society to work in it.',
                ],
                'watch' => [
                    'Societies are kept apart. Working in one, you cannot see another\'s data.',
                ],
            ],
        ];
    }
}
