<?php

namespace App\Tests\Service;

use App\Exception\RegleMetierException;
use App\Service\IsbnNormalizer;
use PHPUnit\Framework\TestCase;

class IsbnNormalizerTest extends TestCase
{
    private IsbnNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new IsbnNormalizer();
    }

    public function testNormaliserIsbn13ValideAvecTirets(): void
    {
        // 978-2-07-036002-4 -> 9782070360024
        $result = $this->normalizer->normaliser('978-2-07-036002-4');
        $this->assertSame('9782070360024', $result);
    }

    public function testNormaliserIsbn13ValideSansTirets(): void
    {
        $result = $this->normalizer->normaliser('9782070360024');
        $this->assertSame('9782070360024', $result);
    }

    public function testNormaliserIsbn10ValideVersIsbn13(): void
    {
        // 0-306-40615-2 -> 9780306406157
        $result = $this->normalizer->normaliser('0-306-40615-2');
        $this->assertSame('9780306406157', $result);
    }

    public function testNormaliserIsbn10AvecCleX(): void
    {
        // 0-8044-2957-X -> 9780804429573
        $result = $this->normalizer->normaliser('0-8044-2957-X');
        $this->assertSame('9780804429573', $result);
    }

    public function testRefuseIsbn10CleInvalide(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('clé de contrôle incorrecte');
        $this->normalizer->normaliser('0-306-40615-9');
    }

    public function testRefuseIsbn13CleInvalide(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('clé de contrôle incorrecte');
        $this->normalizer->normaliser('978-2-07-036002-9');
    }

    public function testRefuseLongueurInvalide(): void
    {
        $this->expectException(RegleMetierException::class);
        $this->expectExceptionMessage('doit comporter 10 ou 13 caractères');
        $this->normalizer->normaliser('123456');
    }

    public function testEstValide(): void
    {
        $this->assertTrue($this->normalizer->estValide('978-2-07-036002-4'));
        $this->assertTrue($this->normalizer->estValide('0-8044-2957-X'));
        $this->assertFalse($this->normalizer->estValide('978-2-07-036002-9'));
        $this->assertFalse($this->normalizer->estValide('invalide'));
    }
}
