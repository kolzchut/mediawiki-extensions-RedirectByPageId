<?php
/**
 * Aliases for the RedirectByPageId special page.
 *
 * ENGLISH ONLY, ON PURPOSE — please do not add localized aliases.
 *
 * Nothing links to this page by name. It exists to resolve the
 * /page/<lang>/<id> permalinks that KZChangeRequest and MarkMajorChanges stamp
 * into Jira tickets, and the edge redirects those straight to it; no menu,
 * message or user-facing string ever shows the title. A translated name would
 * buy no readability.
 *
 * THIS FILE DEFINES A NAME THE EDGE HARDCODES. MediaWiki canonicalizes a
 * special-page title to the content language's preferred alias and 301s
 * anything else to it, so the /page/ redirect in kz-infrastructure
 * (docker/nginx/mediawiki.conf.template) has to name that title exactly or the
 * permalink pays an extra hop for the difference. Whatever this file makes
 * preferred IS that title.
 *
 * To be clear about what English-only does and does not buy: it does NOT save a
 * redirect. A localized alias is equally canonical, and the edge could name it
 * just as directly — measured identical, one hop either way. What it buys is
 * that the name the edge hardcodes is now this extension's own class name,
 * which changes only if the extension is renamed. A translation string is a
 * more reasonable thing for someone to edit, and editing it would cost that
 * redirect silently — nothing errors, the link still works, it is just slower.
 *
 * So: adding a localized alias is not breaking, and not by itself a
 * regression — but it is only free if the nginx target is updated in the SAME
 * change. That coupling is the reason this file stays English.
 *
 * The namespace prefix localizes regardless (`מיוחד:`, `خاص:`, `Служебная:`) —
 * core behaviour, not ours to avoid — which is why that nginx rule is three
 * per-language locations rather than one.
 *
 * Note for anyone adding one later: our non-Hebrew wikis fall back to Hebrew
 * rather than English (a deliberate core patch), so a `he` alias does not stay
 * on the Hebrew wiki — it becomes the preferred title on the Arabic and Russian
 * wikis too. That is how these URLs used to read `/ar/خاص:הפניה_לפי_מזהה_דף/1`.
 *
 * @file
 * @license GPL-2.0-or-later
 */

$specialPageAliases = [];

/** English (English) */
$specialPageAliases['en'] = [
	'RedirectByPageId' => [ 'RedirectByPageId' ],
];
