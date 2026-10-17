# Contact form plan: Fluent Forms (free) + FluentSMTP (not installed)

**PARKED (17 Oct 2026): live has no form; revisit later.** Parked, not cancelled.

## John's decisions (17 Oct 2026; these win wherever the plan below differs)
- **Sending:** Rockhost SMTP through a dedicated `website@` mailbox, via FluentSMTP.
- **Retention:** entries kept 90 days, then deleted (the plan below proposes 12 months).
- **Spam:** honeypot only. No Turnstile unless spam appears (the plan below
  proposes Turnstile).
- **Consent:** no consent checkbox (the plan below has one).
- **Privacy:** a short privacy line sits under the submit button, linking to the Privacy Notice.
- Everything else below stands as the starting plan, including the fields,
  recipient to confirm, theme integration and the checks.

---

Status: **plan only, for John's OK.** Nothing is installed. Written 17 Oct 2026.
Items marked *check* are plugin features I could not confirm without
installing it; confirm each on the sandbox before relying on it.

## What exists today
- **Elementor Pro forms: none found.** A scan of the backed-up Elementor data
  of every converted page and the footer template #208 found no `form`
  widget (widgets present: text-editor, html, image, gallery, shortcode). The
  only form is the footer newsletter signup, which is MC4WP form 206 (unaffected).
- Contact Us (211) has no form; the live Contact page (modified 25 Nov 2025)
  gives the address, the office number and the office email, and no form.
- Not checked (needs wp-admin): whether Elementor Pro's Submissions screen
  holds past entries from a form on a page that no longer exists. Please look
  at Elementor > Submissions once. If it holds entries, export them before
  Elementor Pro is deactivated.
- So this is a new form, not a replacement. Nothing needs migrating.

## The form (Contact Us, one form)
| Field | Type | Required | Note |
|---|---|---|---|
| Your name | text | yes | `autocomplete="name"` |
| Email address | email | yes | `autocomplete="email"`; used as Reply-To |
| Phone | tel | no | optional |
| What is it about? | select: General question, Visiting for the first time, Room hire, Pastoral care, Something else | no | helps the office route it |
| Message | textarea | yes | |
| Consent | checkbox | yes | "I agree to the church using these details to reply to me (see the Privacy Notice)." Not pre-ticked |
| Honeypot | hidden | n/a | see spam protection |

Success message: "Thank you. We will reply as soon as we can (the office is
not always staffed)." No newsletter opt-in here; the footer form covers that.

## Recipients
- To: the church office address, `admin@moortownbaptistchurch.org.uk` (the
  address on Contact Us). **John or John S to confirm** this is the one the
  office reads, and whether a second person should be copied.
- From: a site-domain address (for example `website@moortownbaptistchurch.org.uk`)
  so SPF and DKIM pass; **Reply-To: the visitor's email**, so Reply works.
- No auto-reply to the visitor in the first version (avoids the site being
  used to send mail to third parties).

## FluentSMTP (delivery)
Without it, WordPress mail from shared hosting often lands in spam or is lost.
Options, in order of preference:
1. Rockhost's SMTP with a real mailbox (for example the office or a
   `website@` mailbox): simplest, same domain, needs the mailbox password.
2. An API provider with a free tier (Brevo, Mailgun and similar): better
   reporting; one more account to run.
Either way: SPF and DKIM for the domain must include the sender (Rockhost
can say what the DNS already has). **FluentSMTP stores the credentials in the
database**, so use a mailbox made for this, never the office login. The plugin
logs sent mail (*check* its retention setting; the log holds the visitor's
message, so keep it short or off). I will not store or print any password;
John enters it in wp-admin.

## Spam protection
1. **Honeypot** (built into Fluent Forms; *check* it is on per form).
2. **Cloudflare Turnstile** (Fluent Forms supports it in the free plugin,
   *check*). Needs a free Cloudflare account and a site key and secret key
   (the site need not be proxied by Cloudflare). It is invisible to most visitors.
3. Server-side checks: required fields, a sensible length limit on the
   message, and Fluent Forms' own rate limiting (*check*). No CAPTCHA puzzle.
Privacy effect: Turnstile loads a script from Cloudflare and shares the
visitor's IP address with it. The Privacy Notice must say so, and the cookie
review for the Cookies page should include it.
The script loads only on the Contact page (*check* that Fluent Forms enqueues
assets only where a form exists), to keep the page weight measurements honest.

## Where entries are stored
- Fluent Forms keeps every submission in the WordPress database (its own
  tables) and emails it. Entries are visible in wp-admin to Administrators.
- **Decision for John and the church:** how long to keep them. Proposal: 12
  months, then delete (*check* the free plugin's auto-delete setting; if
  absent, a monthly clean-up by Claude's script, or turn entry storage off and
  rely on email only, which is the lowest-risk choice).
- The "Newsletter editor" role (John S) should **not** see entries; keep
  access to Administrators and anyone the church names (*check* the plugin's
  access setting).
- Entries are personal data, so they are covered by the church's data
  retention policy and the Plesk backups.

## Privacy notice wording (draft for the church's data protection lead)
"If you use the contact form on our website we collect your name, email
address, optional phone number and your message, so that we can reply. We
keep them for up to 12 months and then delete them. We do not use them for
anything else, and do not add you to our newsletter unless you sign up
separately. The form is protected from spam by Cloudflare Turnstile, which
receives your IP address. Your message is stored on our website and
emailed to the church office through [the mail provider]. You can ask us to
see or delete your details by contacting the church office."
The notice must also name Mailchimp (Intuit) as processor of the newsletter
list, and the US transfer (existing cutover item). The [bracketed] provider
depends on the FluentSMTP option chosen.

## Theme integration
- Fluent Forms ships its own CSS. Plan: switch off its default styling where
  possible (*check*) and style the form in `css/` with the existing input
  and button tokens (`--mbc-input-*`, `--mbc-button-*`, `--mbc-focus*`), the
  same as the footer form. No hard-coded colours.
- Checks to add in `~/src/mbc-tests/`: `contact_form_check.py` (labels,
  tab order, required messages, focus ring and contrast in all five looks at
  360 and 1280px, no Elementor files, no console errors). Like the footer
  form check it **must not submit** to production mail: a real submission is
  tested once by hand, to John's own address.
- Contact Us text and the `contact-placeholder` pattern would gain the form;
  the form itself lives in the database (Fluent Forms), so its settings are
  recorded in CLAUDE.md the way MC4WP form 206 is, so it can be rebuilt.
- Plugin footprint: two plugins to keep updated, replacing no existing one.

## Steps once approved
1. John installs Fluent Forms and FluentSMTP on the **sandbox** (CLAUDE.md:
   ask before installing; the sandbox only).
2. John adds the mailbox credentials and (if wanted) the Turnstile keys.
3. Claude builds the form to the table above, sends **one** test to John's own
   address, and checks the headers (From, Reply-To, SPF/DKIM pass).
4. Claude adds the form to Contact Us (and the pattern), the styles and the
   check script; records the settings in CLAUDE.md.
5. Cutover: repeat on live, update the Privacy Notice, tick the item.

## Decisions needed from John
1. OK to proceed with Fluent Forms + FluentSMTP on the sandbox?
2. Recipient address (and any copy).
3. SMTP: Rockhost mailbox or an API provider?
4. Entry retention: 12 months, or email only with no stored entries?
5. Turnstile account: who creates it (a church Cloudflare login, not a personal one)?
