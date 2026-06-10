<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shared prompt builder — 3 outreach types, expert cold email style.
 * 3-4 short paragraphs. Pain-first. Location + Services used throughout.
 */
class SEO_Outreach_Prompt {

    public static function build( array $lead, array $ps_data, string $cal_link ): string {
        $type = $lead['outreach_type'] ?? 'seo';
        switch ( $type ) {
            case 'ads':        return self::build_ads_prompt( $lead, $ps_data, $cal_link );
            case 'no_website': return self::build_no_website_prompt( $lead, $cal_link );
            default:           return self::build_seo_prompt( $lead, $ps_data, $cal_link );
        }
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    private static function clean_name( array $lead ): string {
        $raw = trim( $lead['business_name'] ?? '' );
        $raw = str_replace( [ '_', '-' ], ' ', $raw );
        $raw = ucwords( strtolower( $raw ) );
        if ( empty( $raw ) && ! empty( $lead['website'] ) ) {
            $host = preg_replace( '/^https?:\/\/(www\.)?/', '', $lead['website'] );
            $host = explode( '.', $host )[0];
            $raw  = ucfirst( strtolower( str_replace( [ '_', '-' ], ' ', $host ) ) );
        }
        return ! empty( $raw ) ? $raw : 'Your Business';
    }

    private static function opp_text( array $ps ): string {
        $out = '';
        foreach ( $ps['opportunities'] ?? [] as $o ) {
            $out .= "- {$o['label']}" . ( $o['savings'] ? " (saves: {$o['savings']})" : '' ) . "\n";
        }
        return $out ?: "- General performance improvements needed\n";
    }

    private static function seo_issues( array $ps ): string {
        $seo  = (int)( $ps['seo_score'] ?? 70 );
        $perf = (int)( $ps['performance_score'] ?? 70 );
        $lcp  = (float)( $ps['lcp_sec'] ?? 0 );
        $cls  = (float)( $ps['cls'] ?? 0 );
        $i    = [];
        if ( $seo < 80 )   $i[] = "the site isn\u2019t showing up when someone in the area searches for their service";
        if ( $seo < 70 )   $i[] = "pages don\u2019t give Google enough to trust the site over competitors";
        if ( $seo < 65 )   $i[] = "Google can\u2019t fully crawl the site, so whole sections stay invisible in search";
        if ( $seo < 55 )   $i[] = "not appearing in AI search results like ChatGPT or Google AI at all";
        if ( $perf < 70 )  $i[] = $lcp > 0
            ? "the site loads in {$lcp}s — most visitors leave before the page even opens"
            : "the site loads too slowly — most visitors leave before the page even opens";
        if ( $cls > 0.1 )  $i[] = "the page jumps around as it loads, which makes visitors click away immediately";
        if ( empty( $i ) ) $i[] = "not ranking for any of the search terms customers actually type in";
        $i[] = "phone calls that should be coming in are going to whoever shows up first instead";
        return implode( ', ', $i );
    }

    private static function services_line( array $lead ): string {
        $s = trim( $lead['services'] ?? '' );
        return ! empty( $s )
            ? "Services they offer: {$s}"
            : "Services: not specified — infer from business name, industry, and website if available";
    }

    // ── SEO prompt ────────────────────────────────────────────────────────────

    private static function build_seo_prompt( array $lead, array $ps, string $cal_link ): string {
        $biz      = self::clean_name( $lead );
        $city     = ! empty( trim( $lead['location'] ?? '' ) ) ? trim( $lead['location'] ) : 'your area';
        $website  = $lead['website'] ?? '';
        $perf     = $ps['performance_score'];
        $seo      = $ps['seo_score'];
        $lcp_sec  = $ps['lcp_sec'];
        $opp      = self::opp_text( $ps );
        $issues   = self::seo_issues( $ps );
        $services = self::services_line( $lead );

        return <<<PROMPT
You are an elite cold email copywriter. Write punchy, human cold emails that get replies. No fluff. Every sentence earns its place.

Write ONE cold outreach email from Haris Farooq (AI Driven SEO Expert) to the owner of {$biz}.

════════════════════════
BUSINESS DATA
════════════════════════
Business: {$biz}
Website: {$website}
City: {$city}
{$services}
SEO Score: {$seo}/100
Performance Score: {$perf}/100
Page Load: {$lcp_sec}s
Real problems found: {$issues}
Technical issues: {$opp}

════════════════════════
EXACT REFERENCE EMAIL — YOUR OUTPUT MUST MATCH THIS FORMAT
════════════════════════
Study this carefully. Match the paragraph count, paragraph length, spacing, and tone exactly.

---
Subject: Your competitors in Ohio are stealing your calls

Hi [Owner's Name],

Most HVAC businesses in Ohio lose 60% of their website visitors before they ever see your phone number — and never know why.

I checked RichmondAir's site and found exactly that. Slow load time, missing from AI search results, and pages targeting the wrong keywords. Together these are quietly sending ready-to-book customers to whoever ranks faster and shows up first.

These are not big problems to fix, but right now they are costing you jobs every single day.

You can check the basic audit — let's take 15 minutes to walk through it and show you exactly how to turn those lost visitors into qualified customers.

Haris Farooq | SEO Specialist | hello@harisfarooqseo.online
---

COUNT THE PARAGRAPHS: That email has EXACTLY 4 paragraphs separated by blank lines.
- Paragraph 1: 2 sentences — the hook/stat
- Paragraph 2: 3 sentences — "I checked [Business]'s site and found..." with specific issues
- Paragraph 3: 1–2 sentences — quiet urgency, fixable but costing jobs daily
- Paragraph 4: 2 sentences — audit reference + 15-min meeting CTA

THIS IS THE STRUCTURE YOUR EMAIL MUST FOLLOW. 4 paragraphs. Blank line between each.

════════════════════════
NOW WRITE THE REAL EMAIL FOR {$biz}
════════════════════════

SUBJECT LINE (5–7 words):
- Reference {$city} OR {$biz} or both
- Angle: competitors stealing customers, being invisible, losing calls
- No question marks
- Examples: "Your competitors in {$city} are stealing your calls" / "{$biz} — {$city} customers cannot find you"

PARAGRAPH 1 — HOOK (2–3 sentences):
- Open with a bold stat or observation: most [service] businesses in {$city} lose X% of visitors / customers before [outcome]
- Make the owner think "that's exactly me"
- Do NOT mention speed or load time here
- Do NOT start with "I"

PARAGRAPH 2 — PROOF (3–4 sentences):
- Start with: "I checked {$biz}'s site and found [specific things]"
- Name 3 real problems from: {$issues}
- Weave in the page load time naturally — e.g. "takes {$lcp_sec}s to load, which means most visitors leave before the phone number even appears" — do NOT present it as a raw stat, fold it into a consequence sentence
- Show how they connect — quietly sending {$city} customers to competitors
- End with: who those customers go to instead

PARAGRAPH 3 — URGENCY (1–2 sentences):
- These problems are fixable
- But right now they are costing real jobs / customers every single day
- Calm tone — no panic, no hype

PARAGRAPH 4 — CTA (2 sentences):
- Sentence 1: reference the audit they can check
- Sentence 2: invite a 15-minute meeting to walk through turning lost visitors into customers
- Do NOT use: "I would love", "reach out", "touch base", "feel free", "leverage", "synergy"

SIGN OFF:
Haris Farooq | SEO Specialist | hello@harisfarooqseo.online

════════════════════════
NON-NEGOTIABLE FORMATTING RULES
════════════════════════
- EXACTLY 4 paragraphs. Each separated by a blank line. No exceptions.
- Each paragraph is 2–4 sentences. No single-wall of text.
- ZERO bullet points. ZERO dashes in body. ZERO lists. Plain prose ONLY.
- Short sentences. Max 18 words each.
- Plain English. No "digital presence", "online visibility", "leverage", "synergy", "optimize".
- Never say "I hope", "I came across", "I noticed", "I specialize", "I offer".
- No bold, no asterisks, no markdown formatting.
- Total email body: 160–200 words. Count carefully. Do NOT go below 160 words.
- City "{$city}" must appear at least 2 times in the body.
- Use the real service name from the data — NEVER say "your services" or "what you offer".
- Output ONLY the final email. No labels. No explanation. No preamble.

════════════════════════
AUDIT REPORT (for PDF)
════════════════════════
1. Executive summary: what {$biz} does (mention their service), what the audit found, impact on {$city} customers
2. Two technical issues: page speed ({$lcp_sec}s) + worst item from: {$opp}
3. Two SEO/content gaps from: {$issues} — with business impact for their service in {$city}
4. Action plan: 4–5 sentences on outcomes (more clients in {$city}, AI visibility). Soft CTA: {$cal_link}

RESPOND ONLY WITH VALID JSON. No markdown, no backticks, no explanation before or after:
{
  "email_subject": "...",
  "email_body": "...",
  "report": {
    "executive_summary": "...",
    "technical_issues": [
      {"title": "...", "description": "..."},
      {"title": "...", "description": "..."}
    ],
    "content_gaps": [
      {"title": "...", "description": "..."},
      {"title": "...", "description": "..."}
    ],
    "action_plan": "..."
  }
}
PROMPT;
    }

    // ── ADS prompt ────────────────────────────────────────────────────────────

    private static function build_ads_prompt( array $lead, array $ps, string $cal_link ): string {
        $biz      = self::clean_name( $lead );
        $city     = ! empty( trim( $lead['location'] ?? '' ) ) ? trim( $lead['location'] ) : 'your area';
        $website  = $lead['website'] ?? '';
        $perf     = $ps['performance_score'];
        $seo      = $ps['seo_score'];
        $lcp_sec  = $ps['lcp_sec'];
        $opp      = self::opp_text( $ps );
        $issues   = self::seo_issues( $ps );
        $services = self::services_line( $lead );

        return <<<PROMPT
You are an elite cold email copywriter. Write punchy, human cold emails that get replies. No fluff. Every sentence earns its place.

Write ONE cold outreach email from Haris Farooq (AI Driven SEO Expert) to the owner of {$biz}, who is currently running paid ads.

════════════════════════
BUSINESS DATA
════════════════════════
Business: {$biz}
Website: {$website}
City: {$city}
{$services}
SEO Score: {$seo}/100
Performance Score: {$perf}/100
Page Load: {$lcp_sec}s
SEO problems found: {$issues}
Technical issues: {$opp}

════════════════════════
EXACT REFERENCE EMAIL — YOUR OUTPUT MUST MATCH THIS FORMAT
════════════════════════
Study this carefully. Match the paragraph count, paragraph length, spacing, and tone exactly.

---
Subject: RichmondAir — your ad budget could work harder than this

Hi [Owner's Name],

Running ads in Ohio's HVAC market is expensive, and the moment you stop paying, the calls stop too. Most businesses in your space are spending hundreds every month just to stay visible — while their competitors are getting the same calls for free through search.

I checked RichmondAir's site and found a few things that are leaving organic traffic on the table. Slow load time, weak local keyword targeting, and pages that do not show up in AI search results. With those fixed, you build visibility that keeps generating calls without paying for every single click.

These are not huge changes. But every month without them is another month paying for customers that should be coming in for free.

You can check the basic audit here — let's take 15 minutes to walk through it and show you how to reduce ad dependency and bring in more customers on autopilot.

Haris Farooq | SEO Specialist | hello@harisfarooqseo.online
---

COUNT THE PARAGRAPHS: That email has EXACTLY 4 paragraphs separated by blank lines.
- Paragraph 1: 2 sentences — the ad spend pain + competitors getting it free
- Paragraph 2: 3 sentences — "I checked [Business]'s site and found..." with specific issues + the fix
- Paragraph 3: 2 sentences — quiet urgency around monthly ad waste
- Paragraph 4: 2 sentences — audit reference + 15-min meeting CTA about reducing ad dependency

THIS IS THE STRUCTURE YOUR EMAIL MUST FOLLOW. 4 paragraphs. Blank line between each.

════════════════════════
NOW WRITE THE REAL EMAIL FOR {$biz}
════════════════════════

SUBJECT LINE (5–7 words):
- Angle: ad budget wasted, paying for every click, renting vs owning traffic
- Can include {$biz} or {$city}
- No question marks
- Examples: "{$biz} — your ad budget could work harder" / "Paying for {$city} calls that should be free"

PARAGRAPH 1 — HOOK (2–3 sentences):
- Open with the cost of running ads for their service in {$city} — paying for every customer
- "The moment you stop paying, the calls stop" — use this logic in your own words
- Pivot to: competitors in {$city} getting those same customers FREE through search
- Do NOT start with "I"

PARAGRAPH 2 — PROOF (3–4 sentences):
- Start with: "I checked {$biz}'s site and found a few things leaving organic traffic on the table"
- Name 3 real problems from: {$issues}
- If load time is slow, fold it naturally into one of the issues — e.g. "the site takes {$lcp_sec}s to load, so even the visitors who do click your ad are leaving before they call"
- End with: once fixed, visibility that generates calls without paying per click

PARAGRAPH 3 — URGENCY (2 sentences):
- Not huge changes to make
- But every month without them is another month paying for customers that organic would send for free
- Calm, matter-of-fact tone

PARAGRAPH 4 — CTA (2 sentences):
- Sentence 1: reference the audit they can check
- Sentence 2: invite a 15-minute call specifically about reducing ad dependency
- Do NOT use: "I would love", "reach out", "touch base", "feel free", "synergy", "ROI"

SIGN OFF:
Haris Farooq | SEO Specialist | hello@harisfarooqseo.online

════════════════════════
NON-NEGOTIABLE FORMATTING RULES
════════════════════════
- EXACTLY 4 paragraphs. Each separated by a blank line. No exceptions.
- Each paragraph is 2–4 sentences. No single wall of text.
- ZERO bullet points. ZERO dashes in body. ZERO lists. Plain prose ONLY.
- Short sentences. Max 18 words each.
- Plain English. No "ROI", "digital presence", "online visibility", "leverage", "synergy".
- Never say "I hope", "I came across", "I noticed", "I specialize", "I offer".
- No bold, no asterisks, no markdown formatting.
- Total email body: 160–200 words. Count carefully. Do NOT go below 160 words.
- City "{$city}" must appear at least 2 times in the body.
- Use the real service name — NEVER say "your services" or "what you offer".
- Output ONLY the final email. No labels. No explanation. No preamble.

════════════════════════
AUDIT REPORT (for PDF)
════════════════════════
1. Executive summary: {$biz} runs ads for their service but organic presence is weak — monthly cost in {$city}
2. Two issues: ad landing page speed ({$lcp_sec}s killing conversions) + worst SEO gap from: {$issues}
3. Two strategy gaps: not ranking organically in {$city} for service keywords + no AI search visibility
4. Action plan: how SEO reduces ad dependency, compounds monthly, builds owned visibility in {$city}. Soft CTA: {$cal_link}

RESPOND ONLY WITH VALID JSON. No markdown, no backticks, no explanation before or after:
{
  "email_subject": "...",
  "email_body": "...",
  "report": {
    "executive_summary": "...",
    "technical_issues": [
      {"title": "...", "description": "..."},
      {"title": "...", "description": "..."}
    ],
    "content_gaps": [
      {"title": "...", "description": "..."},
      {"title": "...", "description": "..."}
    ],
    "action_plan": "..."
  }
}
PROMPT;
    }

    // ── NO WEBSITE prompt ─────────────────────────────────────────────────────

    private static function build_no_website_prompt( array $lead, string $cal_link ): string {
        $biz      = self::clean_name( $lead );
        $city     = ! empty( trim( $lead['location'] ?? '' ) ) ? trim( $lead['location'] ) : 'your area';
        $industry = ! empty( trim( $lead['position'] ?? '' ) ) ? trim( $lead['position'] ) : 'your industry';
        $services = self::services_line( $lead );

        return <<<PROMPT
You are an elite cold email copywriter. Write punchy, human cold emails that get replies. No fluff. Every sentence earns its place.

Write ONE cold outreach email from Haris Farooq (Website & SEO Specialist) to the owner of {$biz}.
This business has NO website. We are offering to build them a website AND rank it on Google so they get customers.

════════════════════════
BUSINESS DATA
════════════════════════
Business: {$biz}
City: {$city}
Industry: {$industry}
{$services}
CRITICAL FACT: {$biz} has NO website. They are completely invisible online.
Service being sold: Website creation + local SEO so they rank on Google and appear in AI results like ChatGPT.

════════════════════════
EXACT REFERENCE EMAIL — YOUR OUTPUT MUST MATCH THIS FORMAT
════════════════════════
Study this carefully. Match the paragraph count, paragraph length, spacing, and tone exactly.

---
Subject: RichmondAir — your competitors are getting your customers online

Hi [Owner's Name],

Every day customers in Ohio search for HVAC services on Google and pick whoever shows up first. Without a website, RichmondAir is not even in that conversation — and those calls are going straight to competitors who are.

A professional website does more than just look good. It builds trust before a customer even picks up the phone, ranks for local searches like "HVAC repair near me," and keeps generating leads around the clock without you lifting a finger.

Getting online the right way from the start saves you months of lost business and sets you up to compete with companies twice your size.

Reply to this email and let's set up a quick call to talk about getting RichmondAir online and in front of customers who are already searching for you.

Haris Farooq | Website & SEO Specialist | hello@harisfarooqseo.online
---

COUNT THE PARAGRAPHS: That email has EXACTLY 4 paragraphs separated by blank lines.
- Paragraph 1: 2 sentences — daily reality, customers search, {$biz} is not there, calls go to competitors
- Paragraph 2: 3 sentences — what a website built RIGHT does: trust, local search ranking, 24/7 leads
- Paragraph 3: 2 sentences — getting online right saves months of lost business, compete with bigger players
- Paragraph 4: 2 sentences — warm personal CTA: "Reply to this email" + quick call to get them online

THIS IS THE STRUCTURE YOUR EMAIL MUST FOLLOW. 4 paragraphs. Blank line between each.

════════════════════════
NOW WRITE THE REAL EMAIL FOR {$biz}
════════════════════════

SUBJECT LINE (5–7 words):
- About {$biz} missing customers in {$city} OR competitors getting their calls
- No question marks
- Examples: "{$biz} — your competitors are getting your customers" / "{$city} customers cannot find {$biz} online"

PARAGRAPH 1 — HOOK (2–3 sentences):
- Every day customers in {$city} search for their specific service and pick whoever shows up first
- Without a website, {$biz} is not in that conversation at all
- Those calls go straight to competitors who ARE online
- Matter-of-fact, not condescending. Do NOT start with "I".

PARAGRAPH 2 — THE VALUE (3–4 sentences):
- A website built right does more than look good
- Builds trust before the customer even calls
- Ranks for "[service] near me" searches in {$city}
- Generates leads around the clock without extra work
- Use their specific service name

PARAGRAPH 3 — THE OPPORTUNITY (2 sentences):
- Getting online the right way from the start saves months of lost business
- Sets {$biz} up to compete with larger companies in {$city} who already have this

PARAGRAPH 4 — CTA (2 sentences):
- Ask them to REPLY TO THIS EMAIL — personal, warm, no booking link
- Invite a quick call to talk about getting {$biz} online and in front of customers in {$city} already searching for them
- Warm and conversational tone — not salesy

SIGN OFF:
Haris Farooq | Website & SEO Specialist | hello@harisfarooqseo.online

════════════════════════
NON-NEGOTIABLE FORMATTING RULES
════════════════════════
- EXACTLY 4 paragraphs. Each separated by a blank line. No exceptions.
- Each paragraph is 2–4 sentences. No single wall of text.
- ZERO bullet points. ZERO dashes in body. ZERO lists. Plain prose ONLY.
- Short sentences. Max 18 words each.
- Plain English. ZERO jargon. ZERO technical terms.
- NEVER mention: SEO audits, PageSpeed scores, load times, performance scores, AI search optimisation, structured data.
- The email is ONLY about: building a website that ranks in {$city} and brings real customers for their service.
- Never say "I hope", "I came across", "digital presence", "online visibility", "leverage", "reach out".
- No bold, no asterisks, no markdown formatting.
- Total email body: 160–200 words. Count carefully. Do NOT go below 160 words.
- City "{$city}" must appear at least 3 times in the body.
- Use the real service name — NEVER say "your services" or "what you offer".
- Output ONLY the final email. No labels. No explanation. No preamble.

RESPOND ONLY WITH VALID JSON. No markdown, no backticks, no explanation before or after. Exact structure:
{
  "email_subject": "...",
  "email_body": "...",
  "report": {}
}
PROMPT;
    }
}
