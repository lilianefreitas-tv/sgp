<?php

namespace App\Enums;

enum FinancialManagementMode: string
{
    case NotApplicable = 'not_applicable';
    case InternalCost = 'internal_cost';
    case FixedPrice = 'fixed_price';
    case Subscription = 'subscription';
    case HourBank = 'hour_bank';
    case TechnicalHour = 'technical_hour';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::NotApplicable => 'Não aplicável',
            self::InternalCost => 'Custo interno',
            self::FixedPrice => 'Valor fixo',
            self::Subscription => 'Mensalidade',
            self::HourBank => 'Banco de horas',
            self::TechnicalHour => 'Hora técnica',
            self::Hybrid => 'Híbrido',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NotApplicable => 'O projeto não exige controle monetário nesta etapa.',
            self::InternalCost => 'O esforço é acompanhado como custo da própria organização.',
            self::FixedPrice => 'A entrega possui preço previamente definido.',
            self::Subscription => 'A execução está associada a uma cobrança periódica.',
            self::HourBank => 'O consumo é controlado por uma quantidade contratada de horas.',
            self::TechnicalHour => 'A cobrança considera as horas técnicas efetivamente realizadas.',
            self::Hybrid => 'Combina duas ou mais formas de tratamento financeiro.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $mode) => [$mode->value => $mode->label()])
            ->all();
    }
}
