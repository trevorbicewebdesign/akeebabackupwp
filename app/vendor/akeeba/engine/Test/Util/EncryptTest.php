<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License version 3, or later
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program. If not, see
 * <https://www.gnu.org/licenses/>.
 */

namespace Akeeba\Engine\Test\Util;

use Akeeba\Engine\Util\Encrypt;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Akeeba\Engine\Util\Encrypt
 *
 * KeyExpansion() and Cipher() are exercised indirectly through the AES round-trip tests below.
 * PBKDF2 vectors are validated against RFC 6070 known-answer vectors.
 */
final class EncryptTest extends TestCase
{
	private Encrypt $encrypt;

	protected function setUp(): void
	{
		$this->encrypt = new Encrypt();
	}

	// -------------------------------------------------------------------------
	// AES-CTR round-trip
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\EncryptProvider::ctrRoundTripProvider()
	 */
	public function testAESCtrRoundTrip(string $plaintext, string $password, int $nBits): void
	{
		$ciphertext = $this->encrypt->AESEncryptCtr($plaintext, $password, $nBits);
		$decrypted  = $this->encrypt->AESDecryptCtr($ciphertext, $password, $nBits);

		$this->assertSame($plaintext, $decrypted);
	}

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\EncryptProvider::ctrInvalidKeyBitsProvider()
	 */
	public function testAESCtrInvalidKeyBitsReturnsEmpty(string $plaintext, string $password, int $nBits): void
	{
		$result = $this->encrypt->AESEncryptCtr($plaintext, $password, $nBits);

		$this->assertSame('', $result);
	}

	public function testAESDecryptCtrInvalidKeyBitsReturnsEmpty(): void
	{
		$result = $this->encrypt->AESDecryptCtr('somebase64==', 'password', 64);

		$this->assertSame('', $result);
	}

	// -------------------------------------------------------------------------
	// AES-CBC round-trip (requires OpenSSL adapter)
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\EncryptProvider::cbcRoundTripProvider()
	 */
	public function testAESCbcRoundTrip(string $plaintext, string $password): void
	{
		$adapter = $this->encrypt->getAdapter();

		if (!$adapter->isSupported())
		{
			$this->markTestSkipped('No supported AES-CBC adapter available (OpenSSL or Mcrypt required).');
		}

		$ciphertext = $this->encrypt->AESEncryptCBC($plaintext, $password);
		$decrypted  = $this->encrypt->AESDecryptCBC($ciphertext, $password);

		$this->assertSame($plaintext, $decrypted);
	}

	public function testAESCbcEncryptReturnsString(): void
	{
		$adapter = $this->encrypt->getAdapter();

		if (!$adapter->isSupported())
		{
			$this->markTestSkipped('No supported AES-CBC adapter available.');
		}

		$result = $this->encrypt->AESEncryptCBC('hello', 'password');

		$this->assertIsString($result);
		$this->assertNotEmpty($result);
	}

	public function testAESCbcWithStaticSaltRoundTrip(): void
	{
		$adapter = $this->encrypt->getAdapter();

		if (!$adapter->isSupported())
		{
			$this->markTestSkipped('No supported AES-CBC adapter available.');
		}

		$encrypt = new Encrypt();
		$encrypt->setPbkdf2UseStaticSalt(1);

		$plaintext  = 'Test data for static salt encryption';
		$password   = 'my_static_salt_password';
		$ciphertext = $encrypt->AESEncryptCBC($plaintext, $password);
		$decrypted  = $encrypt->AESDecryptCBC($ciphertext, $password);

		$this->assertSame($plaintext, $decrypted);
	}

	// -------------------------------------------------------------------------
	// PBKDF2 known-answer vectors (RFC 6070)
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\EncryptProvider::pbkdf2KnownAnswerProvider()
	 */
	public function testPbkdf2KnownAnswer(
		string $password,
		string $salt,
		string $algorithm,
		int $count,
		int $key_length,
		string $expectedHex
	): void
	{
		$result = $this->encrypt->pbkdf2($password, $salt, $algorithm, $count, $key_length);

		$this->assertSame($expectedHex, bin2hex($result));
	}

	public function testPbkdf2ReturnsCorrectLength(): void
	{
		$result = $this->encrypt->pbkdf2('password', 'salt', 'sha1', 1000, 32);

		$this->assertSame(32, strlen($result));
	}

	public function testPbkdf2DifferentPasswordsProduceDifferentKeys(): void
	{
		$key1 = $this->encrypt->pbkdf2('password1', 'salt', 'sha1', 1000, 16);
		$key2 = $this->encrypt->pbkdf2('password2', 'salt', 'sha1', 1000, 16);

		$this->assertNotSame($key1, $key2);
	}

	public function testPbkdf2DifferentSaltsProduceDifferentKeys(): void
	{
		$key1 = $this->encrypt->pbkdf2('password', 'salt1', 'sha1', 1000, 16);
		$key2 = $this->encrypt->pbkdf2('password', 'salt2', 'sha1', 1000, 16);

		$this->assertNotSame($key1, $key2);
	}

	// -------------------------------------------------------------------------
	// stringLength() — byte-aware length
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\EncryptProvider::stringLengthProvider()
	 */
	public function testStringLength(string $string, int $expectedByteLength): void
	{
		$this->assertSame($expectedByteLength, $this->encrypt->stringLength($string));
	}

	// -------------------------------------------------------------------------
	// subString() — byte-aware slicing
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider \Akeeba\Engine\Test\Util\EncryptProvider::subStringProvider()
	 */
	public function testSubString(string $string, int $start, ?int $length, string $expected): void
	{
		$this->assertSame($expected, $this->encrypt->subString($string, $start, $length));
	}

	// -------------------------------------------------------------------------
	// getKeyDerivationParameters() defaults
	// -------------------------------------------------------------------------

	public function testGetKeyDerivationParametersDefaults(): void
	{
		$params = $this->encrypt->getKeyDerivationParameters();

		$this->assertSame(16, $params['keySize']);
		$this->assertSame('sha1', $params['algorithm']);
		$this->assertSame(1000, $params['iterations']);
		$this->assertSame(0, $params['useStaticSalt']);
		$this->assertSame("\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0", $params['staticSalt']);
	}

	// -------------------------------------------------------------------------
	// Fluent setters
	// -------------------------------------------------------------------------

	public function testSettersReturnEncryptInstance(): void
	{
		$result = $this->encrypt->setPbkdf2Algorithm('sha256');
		$this->assertSame($this->encrypt, $result);

		$result = $this->encrypt->setPbkdf2Iterations(2000);
		$this->assertSame($this->encrypt, $result);

		$result = $this->encrypt->setPbkdf2UseStaticSalt(1);
		$this->assertSame($this->encrypt, $result);

		$result = $this->encrypt->setPbkdf2StaticSalt('mysalt');
		$this->assertSame($this->encrypt, $result);
	}

	public function testGettersReflectSetters(): void
	{
		$this->encrypt->setPbkdf2Algorithm('sha256');
		$this->assertSame('sha256', $this->encrypt->getPbkdf2Algorithm());

		$this->encrypt->setPbkdf2Iterations(5000);
		$this->assertSame(5000, $this->encrypt->getPbkdf2Iterations());

		$this->encrypt->setPbkdf2UseStaticSalt(1);
		$this->assertSame(1, $this->encrypt->getPbkdf2UseStaticSalt());

		$this->encrypt->setPbkdf2StaticSalt('custom_salt');
		$this->assertSame('custom_salt', $this->encrypt->getPbkdf2StaticSalt());
	}

	// -------------------------------------------------------------------------
	// getStaticSaltExpandedKey() — deterministic with same params
	// -------------------------------------------------------------------------

	public function testStaticSaltExpandedKeyIsDeterministic(): void
	{
		$key1 = $this->encrypt->getStaticSaltExpandedKey('my_password');
		$key2 = $this->encrypt->getStaticSaltExpandedKey('my_password');

		$this->assertSame($key1, $key2);
	}

	public function testStaticSaltExpandedKeyDiffersForDifferentPasswords(): void
	{
		$key1 = $this->encrypt->getStaticSaltExpandedKey('password_a');
		$key2 = $this->encrypt->getStaticSaltExpandedKey('password_b');

		$this->assertNotSame($key1, $key2);
	}

	public function testStaticSaltExpandedKeyReturns16Bytes(): void
	{
		$key = $this->encrypt->getStaticSaltExpandedKey('any_password');

		$this->assertSame(16, strlen($key));
	}

	// -------------------------------------------------------------------------
	// expandKey() — legacy key derivation, deterministic
	// -------------------------------------------------------------------------

	public function testExpandKeyIsDeterministic(): void
	{
		$key1 = $this->encrypt->expandKey('my_password');
		$key2 = $this->encrypt->expandKey('my_password');

		$this->assertSame($key1, $key2);
	}

	public function testExpandKeyDiffersForDifferentPasswords(): void
	{
		$key1 = $this->encrypt->expandKey('password_a');
		$key2 = $this->encrypt->expandKey('password_b');

		$this->assertNotSame($key1, $key2);
	}

	public function testExpandKeyReturns16Bytes(): void
	{
		$key = $this->encrypt->expandKey('any_password');

		$this->assertSame(16, strlen($key));
	}
}
