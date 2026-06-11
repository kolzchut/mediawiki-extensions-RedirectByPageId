<?php
/**
 * @file
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\RedirectByPageId\Tests\Integration;

use MediaWiki\Extension\RedirectByPageId\SpecialRedirectByPageId;
use SpecialPageTestBase;
use Wikimedia\TestingAccessWrapper;

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
		// header for 301/303, so the default temporary redirect leaves the
		// response code at 0. A regression to 301 would surface here.
		$this->assertSame( 0, $response->getStatusCode() );
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

	/**
	 * @dataProvider provideStatusCodes
	 */
	public function testRedirectStatusCodeIsValidated( $configured, string $expected ): void {
		$this->overrideConfigValue( 'RedirectByPageIdStatusCode', $configured );

		$wrapper = TestingAccessWrapper::newFromObject( $this->newSpecialPage() );

		$this->assertSame( $expected, $wrapper->redirectStatusCode() );
	}

	public static function provideStatusCodes(): array {
		return [
			'permanent 301 honoured' => [ 301, '301' ],
			'temporary 302 honoured' => [ 302, '302' ],
			'numeric string honoured' => [ '301', '301' ],
			'unsupported 303 falls back' => [ 303, '302' ],
			'unsupported 308 falls back' => [ 308, '302' ],
			'non-numeric falls back' => [ 'nonsense', '302' ],
			'zero falls back' => [ 0, '302' ],
		];
	}

	public function testUnknownIdShowsNotFoundInsteadOfRedirecting(): void {
		[ $html, $response ] = $this->executeSpecialPage( '999999999' );

		$this->assertSame( '', $response->getHeader( 'Location' ), 'Must not redirect.' );
		// Pages render in qqx here, so messages appear as their keys.
		$this->assertStringContainsString( 'redirectbypageid-notfound', $html );
	}
}
