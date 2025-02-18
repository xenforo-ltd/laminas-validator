<?php

namespace LaminasTest\Validator;

use ArrayObject;
use Laminas\Validator\Barcode;
use Laminas\Validator\Barcode\AdapterInterface;
use Laminas\Validator\Barcode\Ean13;
use Laminas\Validator\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function extension_loaded;

/**
 * \Laminas\Barcode
 *
 * @group      Laminas_Validator
 */
class BarcodeTest extends TestCase
{
    /**
     * @psalm-return array<string, array{0: null|array, 1: string}>
     */
    public function provideBarcodeConstructor(): array
    {
        return [
            'null'        => [null, Barcode\Ean13::class],
            'empty-array' => [[], Barcode\Ean13::class],
        ];
    }

    /**
     * @dataProvider provideBarcodeConstructor
     */
    public function testBarcodeConstructor(?array $options, string $expectedInstance): void
    {
        $barcode = new Barcode($options);
        $this->assertInstanceOf($expectedInstance, $barcode->getAdapter());
    }

    public function testNoneExisting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');
        new Barcode('\Laminas\Validate\BarcodeTest\NonExistentClassName');
    }

    public function testSetAdapter(): void
    {
        $barcode = new Barcode('upca');
        $this->assertTrue($barcode->isValid('065100004327'));

        $barcode->setAdapter('ean13');
        $this->assertTrue($barcode->isValid('0075678164125'));
    }

    public function testSetCustomAdapter(): void
    {
        $barcode = new Barcode([
            'adapter' => $this->createMock(AdapterInterface::class),
        ]);

        $this->assertInstanceOf(AdapterInterface::class, $barcode->getAdapter());
    }

    /**
     * @Laminas-4352
     */
    public function testNonStringValidation(): void
    {
        $barcode = new Barcode('upca');
        $this->assertFalse($barcode->isValid(106510000.4327));
        $this->assertFalse($barcode->isValid(['065100004327']));

        $barcode = new Barcode('ean13');
        $this->assertFalse($barcode->isValid(06510000.4327));
        $this->assertFalse($barcode->isValid(['065100004327']));
    }

    public function testInvalidChecksumAdapter(): void
    {
        require_once __DIR__ . '/_files/MyBarcode1.php';
        $barcode = new Barcode('MyBarcode1');
        $this->assertFalse($barcode->isValid('0000000'));
        $this->assertArrayHasKey('barcodeFailed', $barcode->getMessages());
        $this->assertFalse($barcode->getAdapter()->hasValidChecksum('0000000'));
    }

    public function testInvalidCharAdapter(): void
    {
        require_once __DIR__ . '/_files/MyBarcode1.php';
        $barcode = new Barcode('MyBarcode1');
        $this->assertFalse($barcode->getAdapter()->hasValidCharacters(123));
    }

    public function testAscii128CharacterAdapter(): void
    {
        require_once __DIR__ . '/_files/MyBarcode2.php';
        $barcode = new Barcode('MyBarcode2');
        $this->assertTrue($barcode->getAdapter()->hasValidCharacters('1234QW!"'));
    }

    public function testInvalidLengthAdapter(): void
    {
        require_once __DIR__ . '/_files/MyBarcode2.php';
        $barcode = new Barcode('MyBarcode2');
        $this->assertFalse($barcode->getAdapter()->hasValidLength(123));
    }

    public function testArrayLengthAdapter(): void
    {
        require_once __DIR__ . '/_files/MyBarcode2.php';
        $barcode = new Barcode('MyBarcode2');
        $this->assertTrue($barcode->getAdapter()->hasValidLength('1'));
        $this->assertFalse($barcode->getAdapter()->hasValidLength('12'));
        $this->assertTrue($barcode->getAdapter()->hasValidLength('123'));
        $this->assertFalse($barcode->getAdapter()->hasValidLength('1234'));
    }

    public function testArrayLengthAdapter2(): void
    {
        require_once __DIR__ . '/_files/MyBarcode3.php';
        $barcode = new Barcode('MyBarcode3');
        $this->assertTrue($barcode->getAdapter()->hasValidLength('1'));
        $this->assertTrue($barcode->getAdapter()->hasValidLength('12'));
        $this->assertTrue($barcode->getAdapter()->hasValidLength('123'));
        $this->assertTrue($barcode->getAdapter()->hasValidLength('1234'));
    }

    public function testOddLengthAdapter(): void
    {
        require_once __DIR__ . '/_files/MyBarcode4.php';
        $barcode = new Barcode('MyBarcode4');
        $this->assertTrue($barcode->getAdapter()->hasValidLength('1'));
        $this->assertFalse($barcode->getAdapter()->hasValidLength('12'));
        $this->assertTrue($barcode->getAdapter()->hasValidLength('123'));
        $this->assertFalse($barcode->getAdapter()->hasValidLength('1234'));
    }

    public function testInvalidAdapter(): void
    {
        $barcode = new Barcode('Ean13');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not implement');
        require_once __DIR__ . '/_files/MyBarcode5.php';
        $barcode->setAdapter('MyBarcode5');
    }

    public function testArrayConstructAdapter(): void
    {
        $barcode = new Barcode(['adapter' => 'Ean13', 'options' => 'unknown', 'useChecksum' => false]);
        $this->assertInstanceOf(Ean13::class, $barcode->getAdapter());
        $this->assertFalse($barcode->useChecksum());
    }

    public function testDefaultArrayConstructWithMissingAdapter(): void
    {
        $barcode = new Barcode(['options' => 'unknown', 'checksum' => false]);
        $this->assertTrue($barcode->isValid('0075678164125'));
    }

    public function testTraversableConstructAdapter(): void
    {
        /** @psalm-suppress InvalidArgument we do use ArrayObject on purpose: checks compatibility with older config */
        $barcode = new Barcode(new ArrayObject(['adapter' => 'Ean13', 'options' => 'unknown', 'useChecksum' => false]));
        $this->assertTrue($barcode->isValid('0075678164125'));
    }

    public function testRoyalmailIsValid(): void
    {
        $barcode = new Barcode(['adapter' => 'Royalmail', 'useChecksum' => true]);
        $this->assertTrue($barcode->isValid('1234562'));
    }

    public function testCODE25(): void
    {
        $barcode = new Barcode('code25');
        $this->assertTrue($barcode->isValid('0123456789101213'));
        $this->assertTrue($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('123a'));

        $barcode->useChecksum(true);
        $this->assertTrue($barcode->isValid('0123456789101214'));
        $this->assertFalse($barcode->isValid('0123456789101213'));
    }

    public function testCODE25INTERLEAVED(): void
    {
        $barcode = new Barcode('code25interleaved');
        $this->assertTrue($barcode->isValid('0123456789101213'));
        $this->assertFalse($barcode->isValid('123'));

        $barcode->useChecksum(true);
        $this->assertTrue($barcode->isValid('0123456789101214'));
        $this->assertFalse($barcode->isValid('0123456789101213'));
    }

    public function testCODE39(): void
    {
        $barcode = new Barcode('code39');
        $this->assertTrue($barcode->isValid('TEST93TEST93TEST93TEST93Y+'));
        $this->assertTrue($barcode->isValid('00075678164124'));
        $this->assertFalse($barcode->isValid('Test93Test93Test'));

        $barcode->useChecksum(true);
        $this->assertTrue($barcode->isValid('159AZH'));
        $this->assertFalse($barcode->isValid('159AZG'));
    }

    public function testCODE39EXT(): void
    {
        $barcode = new Barcode('code39ext');
        $this->assertTrue($barcode->isValid('TEST93TEST93TEST93TEST93Y+'));
        $this->assertTrue($barcode->isValid('00075678164124'));
        $this->assertTrue($barcode->isValid('Test93Test93Test'));

// @TODO: CODE39 EXTENDED CHECKSUM VALIDATION MISSING
//        $barcode->useChecksum(true);
//        $this->assertTrue($barcode->isValid('159AZH'));
//        $this->assertFalse($barcode->isValid('159AZG'));
    }

    public function testCODE93(): void
    {
        $barcode = new Barcode('code93');
        $this->assertTrue($barcode->isValid('TEST93+'));
        $this->assertFalse($barcode->isValid('Test93+'));

        $barcode->useChecksum(true);
        $this->assertTrue($barcode->isValid('CODE 93E0'));
        $this->assertFalse($barcode->isValid('CODE 93E1'));
    }

    public function testCODE93EXT(): void
    {
        $barcode = new Barcode('code93ext');
        $this->assertTrue($barcode->isValid('TEST93+'));
        $this->assertTrue($barcode->isValid('Test93+'));

// @TODO: CODE93 EXTENDED CHECKSUM VALIDATION MISSING
//        $barcode->useChecksum(true);
//        $this->assertTrue($barcode->isValid('CODE 93E0'));
//        $this->assertFalse($barcode->isValid('CODE 93E1'));
    }

    public function testEAN2(): void
    {
        $barcode = new Barcode('ean2');
        $this->assertTrue($barcode->isValid('12'));
        $this->assertFalse($barcode->isValid('1'));
        $this->assertFalse($barcode->isValid('123'));
    }

    public function testEAN5(): void
    {
        $barcode = new Barcode('ean5');
        $this->assertTrue($barcode->isValid('12345'));
        $this->assertFalse($barcode->isValid('1234'));
        $this->assertFalse($barcode->isValid('123456'));
    }

    public function testEAN8(): void
    {
        $barcode = new Barcode('ean8');
        $this->assertTrue($barcode->isValid('12345670'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('12345671'));
        $this->assertTrue($barcode->isValid('1234567'));
    }

    public function testEAN12(): void
    {
        $barcode = new Barcode('ean12');
        $this->assertTrue($barcode->isValid('123456789012'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('123456789013'));
    }

    public function testEAN13(): void
    {
        $barcode = new Barcode('ean13');
        $this->assertTrue($barcode->isValid('1234567890128'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('1234567890127'));
    }

    public function testEAN14(): void
    {
        $barcode = new Barcode('ean14');
        $this->assertTrue($barcode->isValid('12345678901231'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('12345678901232'));
    }

    public function testEAN18(): void
    {
        $barcode = new Barcode('ean18');
        $this->assertTrue($barcode->isValid('123456789012345675'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('123456789012345676'));
    }

    public function testGTIN12(): void
    {
        $barcode = new Barcode('gtin12');
        $this->assertTrue($barcode->isValid('123456789012'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('123456789013'));
    }

    public function testGTIN13(): void
    {
        $barcode = new Barcode('gtin13');
        $this->assertTrue($barcode->isValid('1234567890128'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('1234567890127'));
    }

    public function testGTIN14(): void
    {
        $barcode = new Barcode('gtin14');
        $this->assertTrue($barcode->isValid('12345678901231'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('12345678901232'));
    }

    public function testIDENTCODE(): void
    {
        $barcode = new Barcode('identcode');
        $this->assertTrue($barcode->isValid('564000000050'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('0563102430313'));
        $this->assertFalse($barcode->isValid('564000000051'));
    }

    public function testINTELLIGENTMAIL(): void
    {
        $barcode = new Barcode('intelligentmail');
        $this->assertTrue($barcode->isValid('01234567094987654321'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('5555512371'));
    }

    public function testISSN(): void
    {
        $barcode = new Barcode('issn');
        $this->assertTrue($barcode->isValid('1144875X'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('1144874X'));

        $this->assertTrue($barcode->isValid('9771144875007'));
        $this->assertFalse($barcode->isValid('97711448750X7'));
    }

    public function testITF14(): void
    {
        $barcode = new Barcode('itf14');
        $this->assertTrue($barcode->isValid('00075678164125'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('00075678164124'));
    }

    public function testLEITCODE(): void
    {
        $barcode = new Barcode('leitcode');
        $this->assertTrue($barcode->isValid('21348075016401'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('021348075016401'));
        $this->assertFalse($barcode->isValid('21348075016402'));
    }

    public function testPLANET(): void
    {
        $barcode = new Barcode('planet');
        $this->assertTrue($barcode->isValid('401234567891'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('401234567892'));
    }

    public function testPOSTNET(): void
    {
        $barcode = new Barcode('postnet');
        $this->assertTrue($barcode->isValid('5555512372'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('5555512371'));
    }

    public function testROYALMAIL(): void
    {
        $barcode = new Barcode('royalmail');
        $this->assertTrue($barcode->isValid('SN34RD1AK'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('SN34RD1AW'));

        $this->assertTrue($barcode->isValid('012345W'));
        $this->assertTrue($barcode->isValid('06CIOUH'));
    }

    public function testSSCC(): void
    {
        $barcode = new Barcode('sscc');
        $this->assertTrue($barcode->isValid('123456789012345675'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('123456789012345676'));
    }

    public function testUPCA(): void
    {
        $barcode = new Barcode('upca');
        $this->assertTrue($barcode->isValid('123456789012'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertFalse($barcode->isValid('123456789013'));
    }

    public function testUPCE(): void
    {
        $barcode = new Barcode('upce');
        $this->assertTrue($barcode->isValid('02345673'));
        $this->assertFalse($barcode->isValid('02345672'));
        $this->assertFalse($barcode->isValid('123'));
        $this->assertTrue($barcode->isValid('123456'));
        $this->assertTrue($barcode->isValid('0234567'));
    }

    /**
     * @group Laminas-10116
     */
    public function testArrayLengthMessage(): void
    {
        $barcode = new Barcode('ean8');
        $this->assertFalse($barcode->isValid('123'));
        $message = $barcode->getMessages();
        $this->assertArrayHasKey('barcodeInvalidLength', $message);
        $this->assertStringContainsString('length of 7/8 characters', $message['barcodeInvalidLength']);
    }

    /**
     * @group Laminas-8673
     */
    public function testCODABAR(): void
    {
        $barcode = new Barcode('codabar');
        $this->assertTrue($barcode->isValid('123456789'));
        $this->assertTrue($barcode->isValid('A123A'));
        $this->assertTrue($barcode->isValid('A123C'));
        $this->assertFalse($barcode->isValid('A123E'));
        $this->assertFalse($barcode->isValid('A1A23C'));
        $this->assertTrue($barcode->isValid('T123*'));
        $this->assertFalse($barcode->isValid('*123A'));
    }

    /**
     * @group Laminas-11532
     */
    public function testIssnWithMod0(): void
    {
        $barcode = new Barcode('issn');
        $this->assertTrue($barcode->isValid('18710360'));
    }

    /**
     * @group Laminas-8674
     */
    public function testCODE128(): void
    {
        if (! extension_loaded('iconv')) {
            $this->markTestSkipped('Missing ext/iconv');
        }

        $barcode = new Barcode('code128');
        $this->assertTrue($barcode->isValid('ˆCODE128:Š'));
        $this->assertTrue($barcode->isValid('‡01231[Š'));

        $barcode->useChecksum(false);
        $this->assertTrue($barcode->isValid('012345'));
        $this->assertTrue($barcode->isValid('ABCDEF'));
        $this->assertFalse($barcode->isValid('01234Ê'));
    }

    /**
     * Test if EAN-13 contains only numeric characters
     *
     * @group Laminas-3297
     */
    public function testEan13ContainsOnlyNumeric(): void
    {
        $barcode = new Barcode('ean13');
        $this->assertFalse($barcode->isValid('3RH1131-1BB40'));
    }

    public function testEqualsMessageTemplates(): void
    {
        $validator = new Barcode('code25');
        $this->assertSame(
            [
                Barcode::FAILED,
                Barcode::INVALID_CHARS,
                Barcode::INVALID_LENGTH,
                Barcode::INVALID,
            ],
            array_keys($validator->getMessageTemplates())
        );
        $this->assertEquals($validator->getOption('messageTemplates'), $validator->getMessageTemplates());
    }

    public function testEqualsMessageVariables(): void
    {
        $validator        = new Barcode('code25');
        $messageVariables = [
            'length' => ['options' => 'length'],
        ];
        $this->assertSame($messageVariables, $validator->getOption('messageVariables'));
        $this->assertEquals(array_keys($messageVariables), $validator->getMessageVariables());
    }
}
