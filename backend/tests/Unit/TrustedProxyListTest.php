<?php

namespace Tests\Unit;

use App\Http\TrustedProxyList;
use PHPUnit\Framework\TestCase;

/**
 * Lecture de TRUSTED_PROXIES : liste d'adresses ou de réseaux séparés par des virgules ;
 * les jokers « * » et « ** » sont refusés (13.06, jamais « * »).
 */
class TrustedProxyListTest extends TestCase
{
	public function test_an_empty_or_missing_value_trusts_no_proxy(): void
	{
		$this->assertSame([], TrustedProxyList::fromString(null));
		$this->assertSame([], TrustedProxyList::fromString(''));
		$this->assertSame([], TrustedProxyList::fromString(' , '));
	}

	public function test_addresses_and_networks_are_split_and_trimmed(): void
	{
		$this->assertSame(
			['172.31.250.1', '10.0.0.0/8', '2001:db8::1'],
			TrustedProxyList::fromString(' 172.31.250.1, 10.0.0.0/8 ,2001:db8::1 '),
		);
	}

	public function test_wildcards_are_never_trusted(): void
	{
		$this->assertSame([], TrustedProxyList::fromString('*'));
		$this->assertSame([], TrustedProxyList::fromString('**'));
		$this->assertSame(['10.0.0.1'], TrustedProxyList::fromString('*, 10.0.0.1'));
	}

	public function test_values_equivalent_to_a_wildcard_are_never_trusted(): void
	{
		// REMOTE_ADDR fait confiance au pair, quel qu'il soit ; les réseaux /0 couvrent tout.
		$this->assertSame([], TrustedProxyList::fromString('REMOTE_ADDR, 0.0.0.0/0, ::/0'));
		$this->assertTrue(TrustedProxyList::containsWildcard('10.0.0.1, ::/0'));
	}

	public function test_rejected_wildcards_are_reported(): void
	{
		$this->assertTrue(TrustedProxyList::containsWildcard('10.0.0.1, *'));
		$this->assertFalse(TrustedProxyList::containsWildcard('10.0.0.1'));
		$this->assertFalse(TrustedProxyList::containsWildcard(null));
	}
}
