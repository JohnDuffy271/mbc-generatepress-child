# Newsletter: how to make an issue

For the people with the **Newsletter editor** role. You need to be signed in to the site's admin area.

## Make the issue

1. In the left menu choose **Newsletter → Add new issue**.
2. Give it a **title** (for example "Moortown news, 26 October"). Add a **subtitle** if you want one; leave it empty and none is shown.
3. Write a short **intro** in the box (paragraphs, bold, italic, links and lists only).
4. On the right, **Set header image** if you have one for this issue. If you do not, the standard "MBC News" header is used.
5. In **Posts in this issue** the list is filled in for you: the news posts published since the last issue (up to 6), with any sticky (pinned) posts first.
   - **Up / Down** change the order. **Remove** takes a post out of this issue (the post itself is not deleted).
   - **Add a post** puts another recent post in.
   - **Refill from recent posts** (next to **Add a post**) starts the list again from the posts published since the date in the **Posts since** box, which starts as the date of the last issue. Change the date to refill from any day, for example to rebuild a test issue. Sticky posts come first, then the newest, up to 6. Anything you changed on the screen is lost.
   - If there are no posts since that date you see "No new posts since <date>. Choose an earlier date or add posts below." and your list is left as it was.
   - Refill is on every issue, including a brand-new one (a new issue is filled in for you anyway).
6. Press **Save Draft**. You can keep editing as long as you like.

The list is saved with the issue. Posts you publish later do not change an issue you have saved.

## Look at it

- **Preview** shows the web version while it is a draft.
- The **Email link** box (top right) holds the address Mailchimp reads. It works from the first **Save Draft**: before the issue is published the address uses the issue's number (`/newsletter/email/123/?key=...`), after that its name; the old link keeps working after you publish. **Copy email URL**, then open it in a new tab to see the email.

## Send it

1. Press **Publish** when the content is final. The web version then appears in the newsletter archive at `/newsletter/`.
   You can also import into Mailchimp *before* publishing: the email link works for drafts.
2. In Mailchimp start a new campaign, choose **Code your own → Import from URL** and paste the **email URL**.
3. Send yourself a test first, check it, then send as usual.

## If the email link gets out

The link contains a secret key. Anyone who has it can see the email, even before it is published (it is not shown in search engines). If it goes to the wrong person, press **Regenerate key**: the old link stops working at once. Copy the new link into Mailchimp.

## Good to know

- The pre-filled list only uses posts in the **News** category. **Add a post** offers any of the 60 most recent posts.
- A post that is unpublished or deleted after the issue was saved is left out of the web version and the email automatically.
- Nothing here sends email. Sending happens in Mailchimp.
