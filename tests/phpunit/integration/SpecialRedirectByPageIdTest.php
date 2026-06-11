<?php
/**
 * @file
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\RedirectByPageId\Tests\Integration;

use MediaWiki\Extension\RedirectByPageId\SpecialRedirectByPageId;
use SpecialPageTestBase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\RedirectByPageId\SpecialRedirectByPageId
 */
class SpecialRedirectByPageIdTest extends SpecialPageTestBase {

	protected function newSpecialPage(): SpecialRedirectByPageId {
		return new SpecialRedirectByPageId(
			$this->getServiceContainer()->getTitleFactory()
		);
	}

	public function testResolvesExistingPageIdToItsTitle(): void {
		$page = $this->getExistingTestPage( 'RedirectByPageId target' );

		$title = $this->newSpecialPage()->getRedirect( (string)$page->getId() );

		$this->assertNotFalse( $title, 'A live page ID should resolve to a Title.' );
		$this->assertTrue(
			$page->getTitle()->equals( $title ),
			'Resolved Title should be the page that owns the ID.'
		);
	}

	/**
	 * @dataProvider provideInvalidSubpages
	 */
	public function testGetRedirectRejectsInvalidSubpages( ?string $subpage ): void {
		$this->assertFalse( $this->newSpecialPage()->getRedirect( $subpage ) );
	}

	public static function provideInvalidSubpages(): array {
		return [
			'null' => [ null ],
			'empty string' => [ '' ],
			'zero' => [ '0' ],
			'non-numeric' => [ 'Foobar' ],
			'mixed digits/letters' => [ '12x' ],
		];
	}

	public function testGetRedirectReturnsFalseForUnknownId(): void {
		// An ID extremely unlikely to exist in the freshly cloned test DB.
		$this->assertFalse( $this->newSpecialPage()->getRedirect( '999999999' ) );
	}

	public function testRedirectsToCanonicalUrlWithTemporaryStatusByDefault(): void {
		$page = $this->getExistingTestPage( 'RedirectByPageId target' );

		[ , $response ] = $this->executeSpecialPage( (string)$page->getId() );

		$location = $response->getHeader( 'Location' );
		$this->assertSame(
			$page->getTitle()->getFullUrlForRedirect(),
			$location,
			'Must redirect to the canonical article URL.'
		);
		$this->assertStringNotContainsString(
			'curid=',
			(string)$location,
			'Must not redirect to an index.php?curid= URL (the core behaviour we replace).'
		);
		// 302 is emitted implicitly: OutputPage only sets an explicit status
		// header for 301/303, so a temporary redirect leaves the code at 0.
		$this->assertNotSame( 301, $response->getStatusCode() );
	}

	public function testPermanentRedirectWhenConfigured(): void {
		$this->overrideConfigValue( 'RedirectByPageIdStatusCode', 301 );
		$page = $this->getExistingTestPage( 'RedirectByPageId target' );

		[ , $response ] = $this->executeSpecialPage( (string)$page->getId() );

		$this->assertSame( 301, $response->getStatusCode() );
		$this->assertSame(
			$page->getTitle()->getFullUrlForRedirect(),
			$response->getHeader( 'Location' )
		);
	}

	public function testInvalidStatusCodeFallsBackToTemporary(): void {
		$this->overrideConfigValue( 'RedirectByPageIdStatusCode', 308 );
		$page = $this->getExistingTestPage( 'RedirectByPageId target' );

		[ , $response ] = $this->executeSpecialPage( (string)$page->getId() );

		$this->assertNotSame( 301, $response->getStatusCode() );
		$this->assertNotSame( '', $response->getHeader( 'Location' ) );
	}

	public function testUnknownIdShowsNotFoundInsteadOfRedirecting(): void {
		[ $html, $response ] = $this->executeSpecialPage( '999999999' );

		$this->assertSame( '', $response->getHeader( 'Location' ), 'Must not redirect.' );
		// Pages render in qqx here, so messages appear as their keys.
		$this->assertStringContainsString( 'redirectbypageid-notfound', $html );
	}
}
