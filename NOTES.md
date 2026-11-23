# Notes

A buyer saves a search. When a matching listing first goes live they get an alert on their alerts page, and an email on the timing they chose.

## Decisions

- **A match** is a listing the saved search would show on the listings page. I added minimum price, maximum bedrooms and minimum bathrooms.
- **A listing is new** the first time it becomes live. Coming back from under offer does not alert again.
- **Alerts and emails are separate.** The alert is a database row, which is all the task asks for. I added an email because "don't spam" is really about email. Each search is emailed instantly, daily or weekly, and daily and weekly searches share one email a day.
- **Backfill: no.** The buyer has just seen the listings that are already live.
- **Duplicates: two alerts, one email.** The alerts page shows the listing once for each search that found it. The email shows it once.

A queued job creates the alerts, using one query per new listing to find the searches it fits. A scheduled command sends the daily emails at 08:00, and the weekly ones on Mondays.

## Found in the existing code

`auth()->user()` returns `null`, although the README says it returns the demo user, so I used `$request->user()`. I also fixed an N+1 problem on the listings page: it ran one query per listing to load the branch, and now loads all the branches in one query.

## Left out, and next

I left out unsubscribing, marking alerts as read and editing a saved search. Next I would add an "off" option for emails, group instant emails together, and alert buyers when a listing's price drops into their range.

## Where it would not hold up yet

- Matching looks at every saved search, so it slows down as they grow.
- An instant search sends one email for each new listing.
- An alert is marked as emailed when its email is queued, so a failed delivery is not sent again.
- A bulk update of listings skips the observer, so it creates no alerts.

## How I used AI

I worked alongside Claude Code and gave it exact instructions on the architecture and the features. First I installed Laravel Boost and created rules for the project's standards. I read and verified every line of code and manually tested all the features. The result is how I would write the code myself.

[`MANUAL_TESTING.md`](MANUAL_TESTING.md) walks through every part of the feature step by step.
