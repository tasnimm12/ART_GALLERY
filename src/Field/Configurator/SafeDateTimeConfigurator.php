<?php

namespace App\Field\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Intl\IntlFormatter;

final class SafeDateTimeConfigurator implements FieldConfiguratorInterface
{
    public function __construct(private ?IntlFormatter $intlFormatter = null)
    {
    }

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return \in_array($field->getFieldFqcn(), [DateTimeField::class, DateField::class, TimeField::class], true);
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $value = $field->getValue();
        if ($value === null) {
            return;
        }

        if (!extension_loaded('intl')) {
            // Safe fallback formatting without requiring PHP ext-intl
            if ($value instanceof \DateTimeInterface) {
                if (DateField::class === $field->getFieldFqcn()) {
                    $formattedValue = $value->format('d/m/Y');
                } elseif (TimeField::class === $field->getFieldFqcn()) {
                    $formattedValue = $value->format('H:i:s');
                } else {
                    $formattedValue = $value->format('d/m/Y H:i:s');
                }
            } else {
                $formattedValue = (string) $value;
            }

            $field->setFormattedValue($formattedValue);
            return;
        }

        // Standard EasyAdmin intl formatting when ext-intl is available
        $crud = $context->getCrud();
        $defaultTimezone = $crud?->getTimezone() ?? date_default_timezone_get();
        $timezone = $field->getCustomOption(DateTimeField::OPTION_TIMEZONE) ?? $defaultTimezone;

        $dateFormat = null;
        $timeFormat = null;
        $icuDateTimePattern = '';

        if (DateTimeField::class === $field->getFieldFqcn()) {
            [$defaultDatePattern, $defaultTimePattern] = $crud?->getDateTimePattern() ?? [DateTimeField::FORMAT_MEDIUM, DateTimeField::FORMAT_MEDIUM];
            $datePattern = $field->getCustomOption(DateTimeField::OPTION_DATE_PATTERN) ?? $defaultDatePattern;
            $timePattern = $field->getCustomOption(DateTimeField::OPTION_TIME_PATTERN) ?? $defaultTimePattern;
            if (\in_array($datePattern, DateTimeField::VALID_DATE_FORMATS, true)) {
                $dateFormat = $datePattern;
                $timeFormat = $timePattern;
            } else {
                $icuDateTimePattern = $datePattern;
            }

            $formattedValue = $this->intlFormatter?->formatDateTime($field->getValue(), $dateFormat, $timeFormat, $icuDateTimePattern, $timezone) ?? $value->format('d/m/Y H:i');
        } elseif (DateField::class === $field->getFieldFqcn()) {
            $datePattern = $field->getCustomOption(DateField::OPTION_DATE_PATTERN) ?? ($crud?->getDatePattern() ?? DateField::FORMAT_MEDIUM);
            if (\in_array($datePattern, DateField::VALID_DATE_FORMATS, true)) {
                $dateFormat = $datePattern;
            } else {
                $icuDateTimePattern = $datePattern;
            }

            $formattedValue = $this->intlFormatter?->formatDate($field->getValue(), $dateFormat, $icuDateTimePattern, $timezone) ?? $value->format('d/m/Y');
        } elseif (TimeField::class === $field->getFieldFqcn()) {
            $timePattern = $field->getCustomOption(TimeField::OPTION_TIME_PATTERN) ?? ($crud?->getTimePattern() ?? TimeField::FORMAT_MEDIUM);
            if (\in_array($timePattern, TimeField::VALID_TIME_FORMATS, true)) {
                $timeFormat = $timePattern;
            } else {
                $icuDateTimePattern = $timePattern;
            }

            $formattedValue = $this->intlFormatter?->formatTime($field->getValue(), $timeFormat, $icuDateTimePattern, $timezone) ?? $value->format('H:i:s');
        } else {
            $formattedValue = (string) $value;
        }

        $field->setFormattedValue($formattedValue);
    }
}
