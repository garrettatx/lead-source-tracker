# Research Inputs (pasted by Garrett, 2026-09-29), condensed

AI research (Perplexity and one other tool), unverified. How each point was used is in `../v2-plan.md` under
Research Reconciliation.

1. **Attribution model and plugin design.** Combined first and last touch; ft_/lt_ schema plus click
   IDs (gclid, gbraid, wbraid, msclkid), conversion page and time, normalized channel, separate
   reported source; one plugin with form adapters (Formidable, Kadence, Gravity, Fluent, WPForms;
   CF7 and Ninja lower); no separate WP Engine plugin, JS-first on cached hosts (cites WP Engine's
   cookies/sessions and cache support pages, including that Edge Full Page Cache may not respect
   custom cookie exclusions).
2. **Full spec.** Canonical schema (~40 fields), storage keys, first-touch and last-touch rules,
   derived summary preferring paid clicks, channel taxonomy (Paid Search, Organic Search, Local/Maps,
   Paid Social, Organic Social, Referral, Email, SMS, Marketplace, Direct, Other), consent modes and
   hooks, JS events, PHP hooks, optional REST endpoints, retention defaults (ft 90, lt 30 rolling,
   click IDs 90), JSON export payload, QA cases.
3. **Storage contract.** localStorage primary (`acw_v1_ft`, `_lt`, `_ids`, `_meta`), sessionStorage
   for session and a submit guard, one optional small cookie; exact JSON shapes with UTC and Unix
   timestamps; direct overwrites last touch only after an inactivity threshold; audit checklist
   (versioned payloads, expiry, malformed JSON and quota handling, escape values, admin-only debug).
