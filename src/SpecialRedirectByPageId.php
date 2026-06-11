<?php
/**
 * RedirectByPageId — redirect to a page's canonical URL by numeric page ID.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * @file
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\RedirectByPageId;

use MediaWiki\SpecialPage\RedirectSpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;

/**
 * Special:RedirectByPageId/<id>
 *
 * Resolves a numeric page ID to its Title and issues an HTTP redirect to the
 * page's *canonical* (pretty) URL.
 *
 * This differs from MediaWiki core's Special:Redirect/page/<id>, whose page
 * handler returns an `index.php?curid=<id>` URL and therefore serves the
 * article *at* that non-canonical URL — fragmenting analytics and crawl
 * signals between the permalink and the real article URL. By returning a
 * Title from getRedirect(), this special page routes through
 * Title::getFullUrlForRedirect() and lands the visitor on the clean URL.
 *
 * The redirect status (301 vs 302) is configurable via
 * $wgRedirectByPageIdStatusCode and defaults to 302, because a page's title —
 * and hence its canonical URL — can change when it is renamed.
 */
class SpecialRedirectByPageId extends RedirectSpecialPage {

	private TitleFactory $titleFactory;

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		parent::__construct( 'RedirectByPageId' );
		$this->titleFactory = $titleFactory;

		// Query parameters preserved across the redirect, on top of the base set
		// (uselang, useskin, variant, debug, safemode) that RedirectSpecialPage
		// always forwards. 'action' lets e.g. ?action=history pass through.
		$this->mAllowedRedirectParams = [ 'action' ];
	}

	/**
	 * Resolve the subpage (a numeric page ID) to its Title.
	 *
	 * Returning a Title (rather than the boolean true) is precisely what makes
	 * the parent redirect to the canonical article URL via
	 * Title::getFullUrlForRedirect(), instead of to an index.php URL.
	 *
	 * @param string|null $subpage
	 * @return Title|false
	 */
	public function getRedirect( $subpage ) {
		if ( $subpage === null || $subpage === '' || !ctype_digit( (string)$subpage ) ) {
			return false;
		}
		$id = (int)$subpage;
		if ( $id === 0 ) {
			return false;
		}
		$title = $this->titleFactory->newFromID( $id );
		if ( $title && $title->exists() ) {
			return $title;
		}
		return false;
	}

	/**
	 * Override execute() purely to make the HTTP status code configurable.
	 *
	 * The parent reproduces the same Title/query handling but hardcodes 302
	 * (it calls OutputPage::redirect() with no status argument). We mirror its
	 * logic and pass an explicit, validated status code instead.
	 *
	 * @param string|null $subpage
	 */
	public function execute( $subpage ) {
		$target = $this->getRedirect( $subpage );
		$query = $this->getRedirectQuery( $subpage );

		if ( $target instanceof Title ) {
			$this->getOutput()->redirect(
				$target->getFullUrlForRedirect( $query ),
				$this->redirectStatusCode()
			);
			return;
		}

		$this->showNoRedirectPage();
	}

	/**
	 * The configured HTTP redirect status, validated.
	 *
	 * Only 301 (permanent) and 302 (temporary) are accepted; any other value
	 * falls back to 302.
	 *
	 * @return string '301' or '302'
	 */
	protected function redirectStatusCode(): string {
		$code = (string)$this->getConfig()->get( 'RedirectByPageIdStatusCode' );
		return ( $code === '301' || $code === '302' ) ? $code : '302';
	}

	/**
	 * Show a 404 page when the ID resolves to no existing page, rather than the
	 * LogicException the parent throws: a permalink to a deleted or unknown page
	 * is a not-found condition, not a server error.
	 */
	protected function showNoRedirectPage() {
		$out = $this->getOutput();
		$out->setStatusCode( 404 );
		$out->showErrorPage( 'redirectbypageid-notfound-title', 'redirectbypageid-notfound-text' );
	}
}
