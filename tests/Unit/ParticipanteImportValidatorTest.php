<?php

namespace Tests\Unit;

use App\Services\InscricaoImportValidator;
use App\Services\PresencaImportValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParticipanteImportValidatorTest extends TestCase
{
    #[DataProvider('validBirthDates')]
    public function test_normaliza_nascimento_preservando_o_ano_completo(string $validatorClass, string $today, mixed $value, ?string $expected): void
    {
        $this->travelTo($today);

        $validator = app($validatorClass);
        $rows = $validator->validate([[
            'nome' => 'Ana',
            'email' => 'ana@example.com',
            'data_nascimento' => $value,
        ]]);

        $this->assertSame($expected, $rows[0]['data_nascimento']);
        $this->assertSame($rows, $validator->validate($rows));
    }

    public static function validBirthDates(): iterable
    {
        foreach ([InscricaoImportValidator::class, PresencaImportValidator::class] as $validatorClass) {
            foreach ([
                'ausente' => ['2026-11-04', null, null],
                'vazio' => ['2026-11-04', '', null],
                'ano 90' => ['2026-11-04', '25/03/90', '1990-03-25'],
                'ano 05' => ['2026-11-04', '25/03/05', '2005-03-25'],
                'ano 00 bissexto' => ['2026-11-04', '29/02/00', '2000-02-29'],
                'ano atual' => ['2026-11-04', '04/11/26', '2026-11-04'],
                'ano acima do corte' => ['2026-11-04', '25/03/27', '1927-03-25'],
                'ano 69' => ['2026-11-04', '25/03/69', '1969-03-25'],
                'ano 70' => ['2026-11-04', '25/03/70', '1970-03-25'],
                'espacos externos' => ['2026-11-04', ' 25/03/90 ', '1990-03-25'],
                'centenario com ano completo' => ['2026-11-04', '20/10/1926', '1926-10-20'],
                'formato ISO' => ['2026-11-04', '1926-10-20', '1926-10-20'],
                'corte no ano seguinte' => ['2027-01-01', '01/01/27', '2027-01-01'],
                'acima do corte no ano seguinte' => ['2027-01-01', '01/01/28', '1928-01-01'],
                'corte em outro seculo' => ['2101-11-04', '25/03/00', '2100-03-25'],
            ] as $name => $case) {
                yield $validatorClass.' '.$name => [$validatorClass, ...$case];
            }
        }
    }

    #[DataProvider('invalidBirthDates')]
    public function test_rejeita_nascimento_invalido_ou_futuro(string $validatorClass, string $today, mixed $value): void
    {
        $this->travelTo($today);
        $this->expectException(ValidationException::class);

        app($validatorClass)->validate([[
            'nome' => 'Ana',
            'email' => 'ana@example.com',
            'data_nascimento' => $value,
        ]]);
    }

    public static function invalidBirthDates(): iterable
    {
        foreach ([InscricaoImportValidator::class, PresencaImportValidator::class] as $validatorClass) {
            foreach ([
                'dia inexistente' => ['2026-11-04', '31/02/90'],
                'ano nao bissexto' => ['2026-11-04', '29/02/26'],
                'mes inexistente' => ['2026-11-04', '25/13/90'],
                'mes com um digito' => ['2026-11-04', '25/3/90'],
                'ano com um digito' => ['2026-11-04', '25/03/9'],
                'ano com tres digitos' => ['2026-11-04', '25/03/990'],
                'futuro dentro do ano atual' => ['2026-11-04', '05/11/26'],
                'futuro com ano completo' => ['2026-11-04', '25/03/2027'],
                'seculo nao bissexto' => ['2101-11-04', '29/02/00'],
            ] as $name => $case) {
                yield $validatorClass.' '.$name => [$validatorClass, ...$case];
            }
        }
    }
}
