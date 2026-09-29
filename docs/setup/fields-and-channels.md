# Fields and Channels

## Attribution Rules

The plugin keeps two views of a visitor: the **last non-direct touch** (what brought them back to
convert) and the **first touch** (what introduced them). Ad click IDs are kept separately for ad
platform imports.

| Arrival | Result |
|---|---|
| UTM parameters or an ad click ID | New touch. The whole last-touch record is replaced; fields this visit lacks are cleared |
| An external referrer, no parameters | New touch, classified from the referrer |
| The site itself as referrer | Nothing changes. UTMs on internal links are ignored |
| A referrer on the exclude list | Nothing changes |
| Direct (no referrer, no parameters) | Recorded only when there's no live last touch |
| Any new touch when no first touch exists | The first-touch line is written, once |
| An ad click ID | Stored for 90 days whatever later visits do. A new Google click ID replaces older Google ones |

Direct never overwrites a real source: someone who found the site on Google and typed the URL a
week later is a Google lead. GA4 and Google Ads use the same rule.

## Fields

| Key | Holds | Kept |
|---|---|---|
| `source` | Lowercase source name: `google`, `chatgpt`, `facebook`, a referring domain | last touch |
| `medium` | `organic`, `cpc`, `social`, `ai-assistant`, `email`, `referral`, `none`, or the UTM value | last touch |
| `campaign`, `term`, `content` | UTM values from the touch | last touch |
| `channel` | Grouped channel, below | last touch |
| `referrer` | Referring page, origin and path only | last touch |
| `landing_page` | Page the touch landed on; only UTM parameters are kept from its query string | last touch |
| `timestamp` | When the touch happened, site time zone | last touch |
| `first_touch` | One line: channel · source / medium · landing path · date | written once |
| `gclid`, `gbraid`, `wbraid`, `msclkid` | Ad click IDs, format-checked | click window |
| `click_time` | When the click ID arrived | click window |
| `source_medium` | `source / medium`, computed | not stored |
| `form_page` | Page the form was on, computed at submit | not stored |

Values are capped (200 characters, 500 for URLs), and angle brackets, quotes and control characters
are removed.

## Channels

Checked in this order; the first match wins. Names follow GA4's default channel groups.

| Channel | Rule |
|---|---|
| Direct | Source `direct`, medium `none` |
| Paid Social | A paid medium (`cpc`, `paid`, `paid-social`, `cpm`...) with a social source |
| Paid Search | An ad click ID, or medium `cpc`, `ppc`, `paid-search`, `sem` |
| Google Business Profile | Source `google` with the site's `gbp_campaign`, or medium `local` or `gbp` |
| Display | Medium `display`, `banner`, `cpm`, `cpv` |
| Paid Other | Medium `paid` |
| AI Assistant | An AI source or referrer, or medium `ai-assistant` |
| Email | Webmail referrer, or medium `email`, `e-mail`, `newsletter` |
| SMS | Medium `sms` or `text` |
| Marketplace | A referrer on the site's `marketplaces` list |
| Organic Search | A search engine, or medium `organic` |
| Organic Social | A social site, or medium `social` and its variants |
| Referral | Any other site |
| Other | A medium nothing above recognizes |

## Source Lists

Search, AI, social and webmail hosts are in the plugin's default config
(`gd_ls_default_config()`). A host matches its pattern exactly or as a subdomain, never as part of
another name. Specific hosts are checked before general ones, so `gemini.google.com` is AI and
`mail.google.com` is email, not Google search. Review the AI list each quarter.

**ChatGPT** adds `utm_source=chatgpt.com` to links it cites. The plugin maps that to source
`chatgpt`, medium `ai-assistant`.
